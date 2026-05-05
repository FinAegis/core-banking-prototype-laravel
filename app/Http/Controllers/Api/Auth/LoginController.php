<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Auth\Exceptions\PrivyJwtException;
use App\Domain\Auth\Services\PrivyJwtVerifier;
use App\Domain\Mobile\Services\BiometricJWTService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\IpBlockingService;
use App\Traits\HasApiScopes;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Attributes as OA;

class LoginController extends Controller
{
    use HasApiScopes;

    public function __construct(
        private readonly IpBlockingService $ipBlockingService,
        private readonly BiometricJWTService $biometricJWTService,
    ) {
    }

    /**
     * Login user and create token.
     *
     *
     * @throws ValidationException
     */
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Login user',
        description: 'Authenticate user with email and password to receive an access/refresh token pair',
        operationId: 'login',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'password'], properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
        new OA\Property(property: 'device_name', type: 'string', example: 'iPhone 12', description: 'Optional device name for token'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Login successful',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'user', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
        new OA\Property(property: 'email_verified_at', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'access_token', type: 'string', example: '2|VVGVrIVokPBXkWLOi2yK13eHlQwQtQQONX5GCngZ...'),
        new OA\Property(property: 'refresh_token', type: 'string', example: '3|rEfReShToKeNhErE...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', nullable: true, example: 86400, description: 'Access token expiration time in seconds'),
        new OA\Property(property: 'refresh_expires_in', type: 'integer', nullable: true, example: 2592000, description: 'Refresh token expiration time in seconds'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Invalid credentials',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The provided credentials are incorrect.'),
        new OA\Property(property: 'errors', type: 'object', properties: [
        new OA\Property(property: 'email', type: 'array', items: new OA\Items(type: 'string', example: 'The provided credentials are incorrect.')),
        ]),
        ])
    )]
    public function login(Request $request): JsonResponse
    {
        $request->validate(
            [
                'email'              => 'required|email',
                'password'           => 'required',
                'device_name'        => 'string',
                'device_attestation' => ['nullable', 'string', 'max:4096'],
                'device_type'        => ['nullable', 'string', 'in:ios,android'],
            ]
        );

        // Check if IP is blocked
        $ip = $request->ip();
        if ($this->ipBlockingService->isBlocked($ip)) {
            $blockInfo = $this->ipBlockingService->getBlockInfo($ip);
            throw ValidationException::withMessages([
                'email' => ['Your IP address has been temporarily blocked. Please try again later.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // Record failed attempt
            $this->ipBlockingService->recordFailedAttempt($ip, $request->email);

            throw ValidationException::withMessages(
                [
                    'email' => ['The provided credentials are incorrect.'],
                ]
            );
        }

        // Verify device attestation if provided and enabled
        if ($request->filled('device_attestation') && config('mobile.attestation.enabled')) {
            $verified = $this->biometricJWTService->verifyDeviceAttestationForUser(
                $user,
                $request->input('device_attestation'),
                $request->input('device_type', 'android')
            );
            if (! $verified) {
                return response()->json(['success' => false, 'message' => 'Device attestation failed'], 403);
            }
        }

        // Regenerate session to prevent session fixation attacks (only for web)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Create access/refresh token pair
        $tokenPair = $this->createTokenPair($user, $request->device_name ?? 'web');

        // Check and enforce concurrent session limits
        $this->enforceSessionLimits($user);

        return response()->json(
            [
                'success' => true,
                'data'    => [
                    'user'               => $user,
                    'access_token'       => $tokenPair['access_token'],
                    'refresh_token'      => $tokenPair['refresh_token'],
                    'token_type'         => 'Bearer',
                    'expires_in'         => $tokenPair['expires_in'],
                    'refresh_expires_in' => $tokenPair['refresh_expires_in'],
                ],
            ]
        );
    }

    /**
     * Exchange a Privy session JWT for a Sanctum token.
     *
     * The mobile app authenticates with Privy (non-custodial wallet provider)
     * and forwards the resulting JWT here. We verify the signature against
     * Privy's JWKS, look up or create a backing User keyed on the Privy user
     * id, and issue a standard Sanctum token so the rest of the API stack
     * works unchanged.
     */
    #[OA\Post(
        path: '/api/v1/auth/privy-login',
        summary: 'Exchange a Privy session JWT for a Sanctum token',
        description: 'Verifies the Privy-issued session JWT against Privy\'s JWKS, finds or creates the backing user, and returns a Sanctum bearer token with read/write/delete abilities.',
        operationId: 'privyLogin',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['privy_token'], properties: [
        new OA\Property(property: 'privy_token', type: 'string', description: 'Privy-issued session JWT (RS256)', example: 'eyJhbGciOiJSUzI1NiIs...'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Privy login successful',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'user', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'email', type: 'string', example: 'privy_abcdef1234567890@placeholder.zelta.app'),
        new OA\Property(property: 'privy_user_id', type: 'string', example: 'did:privy:cl9...'),
        ]),
        new OA\Property(property: 'token', type: 'string', example: '7|VVGVrIVokPBXkWLOi2yK...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid or expired Privy token',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'INVALID_PRIVY_TOKEN'),
        new OA\Property(property: 'message', type: 'string', example: 'Privy JWT signature is invalid.'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation failed',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The privy token field is required.'),
        new OA\Property(property: 'errors', type: 'object'),
        ])
    )]
    public function privyLogin(Request $request, PrivyJwtVerifier $verifier): JsonResponse
    {
        $validated = $request->validate([
            'privy_token' => ['required', 'string'],
        ]);

        try {
            $claims = $verifier->verify($validated['privy_token']);
        } catch (PrivyJwtException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_PRIVY_TOKEN',
                    'message' => $e->getMessage(),
                ],
            ], 401);
        }

        $user = User::where('privy_user_id', $claims->privyUserId)->first();

        if (! $user instanceof User) {
            $placeholderEmail = 'privy_' . substr($claims->privyUserId, -16) . '@placeholder.zelta.app';

            $user = User::create([
                'name'              => 'Privy User',
                'email'             => $placeholderEmail,
                'password'          => Str::random(64),
                'email_verified_at' => now(),
                'privy_user_id'     => $claims->privyUserId,
                'privy_linked_at'   => now(),
            ]);
        }

        $token = $user->createToken('privy', ['read', 'write', 'delete'])->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'user'       => $user,
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Logout user and revoke tokens.
     */
    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Logout user',
        description: 'Logout the authenticated user and revoke all their tokens',
        operationId: 'logout',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Logout successful',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully'),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
        ])
    )]
    public function logout(Request $request): JsonResponse
    {
        // Revoke all tokens for the user
        $request->user()->tokens()->delete();

        // Invalidate session (only for web)
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Refresh the access token using a refresh token.
     *
     * Accepts a refresh token (via body or Authorization header), validates it,
     * revokes the old token pair, and issues a new access/refresh pair.
     */
    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Refresh access token',
        description: 'Uses a refresh token to obtain a new access/refresh token pair. Does not require auth:sanctum middleware.',
        operationId: 'refreshToken',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'refresh_token', type: 'string', example: '2|xyz...', description: 'Refresh token (alternatively send via Authorization: Bearer header)'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Token refreshed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'access_token', type: 'string', example: '3|newTokenHere...'),
        new OA\Property(property: 'refresh_token', type: 'string', example: '4|newRefreshHere...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', nullable: true, example: 86400, description: 'Access token expiration time in seconds'),
        new OA\Property(property: 'refresh_expires_in', type: 'integer', nullable: true, example: 2592000, description: 'Refresh token expiration time in seconds'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid or expired refresh token',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Invalid or expired refresh token.'),
        ])
    )]
    public function refresh(Request $request): JsonResponse
    {
        // Extract refresh token from body or Authorization header
        $rawToken = $request->input('refresh_token');
        if (! $rawToken) {
            $rawToken = $request->bearerToken();
        }

        if (! $rawToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token is required.',
            ], 401);
        }

        // Look up the token in the database
        $accessToken = PersonalAccessToken::findToken($rawToken);

        if (! $accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        // Verify it has the 'refresh' ability
        if (! $accessToken->can('refresh')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token.',
            ], 401);
        }

        // Check expiration
        if ($accessToken->expires_at && Carbon::now()->greaterThan($accessToken->expires_at)) {
            $accessToken->delete();

            return response()->json([
                'success' => false,
                'message' => 'Refresh token has expired.',
            ], 401);
        }

        /** @var User $user */
        $user = $accessToken->tokenable;

        // Derive the base token name (strip '-refresh' suffix)
        $refreshTokenName = $accessToken->name;
        $baseName = str_ends_with($refreshTokenName, '-refresh')
            ? substr($refreshTokenName, 0, -8)
            : $refreshTokenName;

        // Revoke the old token pair
        $this->revokeTokenPairByName($user, $baseName);

        // Issue a new token pair
        $tokenPair = $this->createTokenPair($user, $baseName);

        return response()->json(
            [
                'success' => true,
                'data'    => [
                    'access_token'       => $tokenPair['access_token'],
                    'refresh_token'      => $tokenPair['refresh_token'],
                    'token_type'         => 'Bearer',
                    'expires_in'         => $tokenPair['expires_in'],
                    'refresh_expires_in' => $tokenPair['refresh_expires_in'],
                ],
            ]
        );
    }

    /**
     * Logout from all devices by revoking all tokens.
     */
    #[OA\Post(
        path: '/api/auth/logout-all',
        summary: 'Logout from all devices',
        description: 'Revokes all tokens for the authenticated user across all devices',
        operationId: 'logoutAll',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'All sessions terminated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'message', type: 'string', example: 'All sessions terminated successfully'),
        new OA\Property(property: 'revoked_count', type: 'integer', example: 3),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
        ])
    )]
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $revokedCount = $user->tokens()->count();

        // Revoke all tokens for the user
        $user->tokens()->delete();

        // Invalidate session (only for web)
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'message'       => 'All sessions terminated successfully',
                'revoked_count' => $revokedCount,
            ],
        ]);
    }

    /**
     * Get current user.
     */
    #[OA\Get(
        path: '/api/auth/user',
        summary: 'Get current user',
        description: 'Get the authenticated user\'s information',
        operationId: 'getUser',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'User information retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
        ])
    )]
    public function user(Request $request): JsonResponse
    {
        return response()->json(
            [
                'success' => true,
                'data'    => $request->user(),
            ]
        );
    }

    /**
     * Enforce concurrent session limits by removing oldest access tokens.
     *
     * Refresh tokens (abilities = ['refresh']) are excluded from the count
     * since they are not active sessions.
     */
    private function enforceSessionLimits(User $user): void
    {
        $maxSessions = config('auth.max_concurrent_sessions', 5);

        // Count only access tokens (exclude refresh tokens)
        $accessTokenCount = $user->tokens()
            ->where('abilities', '!=', '["refresh"]')
            ->count();

        if ($accessTokenCount > $maxSessions) {
            $tokensToDelete = $accessTokenCount - $maxSessions;
            $user->tokens()
                ->where('abilities', '!=', '["refresh"]')
                ->orderBy('created_at', 'asc')
                ->limit($tokensToDelete)
                ->delete();
        }
    }

    /**
     * Revoke both access and refresh tokens for a given base name.
     */
    private function revokeTokenPairByName(User $user, string $baseName): void
    {
        $user->tokens()
            ->whereIn('name', [$baseName, $baseName . '-refresh'])
            ->delete();
    }
}
