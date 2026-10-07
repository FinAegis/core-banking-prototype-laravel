<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\User\Exceptions\RegistrationNotAllowedException;
use App\Domain\User\Services\ApiRegistrationGate;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\HasApiScopes;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use OpenApi\Attributes as OA;

class SocialAuthController extends Controller
{
    use HasApiScopes;

    /**
     * Get the OAuth redirect URL for a provider.
     */
    #[OA\Get(
        path: '/api/auth/social/{provider}',
        operationId: 'socialRedirect',
        tags: ['Authentication'],
        summary: 'Get OAuth redirect URL',
        description: 'Get the OAuth redirect URL for social login',
        parameters: [
        new OA\Parameter(name: 'provider', in: 'path', required: true, description: 'OAuth provider (google, facebook, github)', schema: new OA\Schema(type: 'string', enum: ['google', 'facebook', 'github'])),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'OAuth redirect URL',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'url', type: 'string', example: 'https://accounts.google.com/o/oauth2/auth?...'),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid provider'
    )]
    public function redirect($provider)
    {
        $validProviders = ['google', 'facebook', 'github'];

        if (! in_array($provider, $validProviders)) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }

        try {
            $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();

            return response()->json(['url' => $url]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Provider not configured'], 500);
        }
    }

    /**
     * Handle OAuth callback and authenticate user.
     */
    #[OA\Post(
        path: '/api/auth/social/{provider}/callback',
        operationId: 'socialCallback',
        tags: ['Authentication'],
        summary: 'Handle OAuth callback',
        description: 'Process OAuth callback and authenticate user',
        parameters: [
        new OA\Parameter(name: 'provider', in: 'path', required: true, description: 'OAuth provider', schema: new OA\Schema(type: 'string', enum: ['google', 'facebook', 'github'])),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['code'], properties: [
        new OA\Property(property: 'code', type: 'string', description: 'OAuth authorization code'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'User authenticated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'user', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
        ]),
        new OA\Property(property: 'token', type: 'string', example: '1|laravel_sanctum_token...'),
        new OA\Property(property: 'message', type: 'string', example: 'Authenticated successfully'),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid provider or authentication failed'
    )]
    #[OA\Response(
        response: 403,
        description: 'First social login of a new user while API registration is closed (ERR_REG_001) or invite-only without an invitation (ERR_REG_002)'
    )]
    public function callback(Request $request, $provider, ApiRegistrationGate $registrationGate)
    {
        $validProviders = ['google', 'facebook', 'github'];

        if (! in_array($provider, $validProviders)) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }

        $request->validate(['code' => 'required|string']);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Check if user exists with this provider
            $user = User::where('email', $socialUser->getEmail())
                ->orWhere(
                    function ($query) use ($provider, $socialUser) {
                        $query->where('oauth_provider', $provider)
                            ->where('oauth_id', $socialUser->getId());
                    }
                )
                ->first();

            if ($user) {
                // Update OAuth info if email matched but OAuth info is different
                if (! $user->oauth_provider) {
                    $user->update(
                        [
                            'oauth_provider' => $provider,
                            'oauth_id'       => $socialUser->getId(),
                            'avatar'         => $socialUser->getAvatar(),
                        ]
                    );
                }
            } else {
                // New account: REGISTRATION_API_ENABLED / REGISTRATION_API_INVITE_ONLY.
                $invitation = $registrationGate->admit((string) $socialUser->getEmail());

                // Create new user
                $user = User::create(
                    [
                        'name'              => $socialUser->getName(),
                        'email'             => $socialUser->getEmail(),
                        'password'          => Hash::make(Str::random(32)), // Random password for OAuth users
                        'oauth_provider'    => $provider,
                        'oauth_id'          => $socialUser->getId(),
                        'avatar'            => $socialUser->getAvatar(),
                        'email_verified_at' => now(), // Auto-verify OAuth users
                    ]
                );

                $registrationGate->accepted($invitation, $user);
            }

            // Generate token with proper expiration
            $token = $this->createTokenWithScopes($user, 'social-auth-token');

            return response()->json(
                [
                    'user'    => $user,
                    'token'   => $token,
                    'message' => 'Authenticated successfully',
                ]
            );
        } catch (RegistrationNotAllowedException $e) {
            return $e->toResponse();
        } catch (Exception $e) {
            return response()->json(
                [
                    'message' => 'Authentication failed',
                    'error'   => config('app.debug') ? $e->getMessage() : null,
                ],
                400
            );
        }
    }
}
