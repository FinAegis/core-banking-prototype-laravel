<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\HasApiScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use HasApiScopes;

    /**
     * Login user and create token.
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login user",
     *     description="Authenticate user with email and password to receive an access token",
     *     operationId="login",
     *     tags={"Authentication"},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"email","password"},
     *
     * @OA\Property(property="email",             type="string", format="email", example="john@example.com"),
     * @OA\Property(property="password",          type="string", format="password", example="password123"),
     * @OA\Property(property="device_name",       type="string", example="iPhone 12", description="Optional device name for token")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Login successful",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="user",
     *                 type="object",
     * @OA\Property(property="id",                type="integer", example=1),
     * @OA\Property(property="name",              type="string", example="John Doe"),
     * @OA\Property(property="email",             type="string", example="john@example.com"),
     * @OA\Property(property="email_verified_at", type="string", nullable=true)
     *             ),
     * @OA\Property(property="access_token",      type="string", example="2|VVGVrIVokPBXkWLOi2yK13eHlQwQtQQONX5GCngZ..."),
     * @OA\Property(property="token_type",        type="string", example="Bearer"),
     * @OA\Property(property="expires_in",        type="integer", nullable=true, example=null, description="Token expiration time in seconds")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Invalid credentials",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",           type="string", example="The given data was invalid."),
     * @OA\Property(
     *                 property="errors",
     *                 type="object",
     * @OA\Property(
     *                     property="email",
     *                     type="array",
     *
     * @OA\Items(type="string",                   example="The provided credentials are incorrect.")
     *                 )
     *             )
     *         )
     *     )
     * )
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate(
            [
                'email'       => 'required|email',
                'password'    => 'required',
                'device_name' => 'string',
            ]
        );

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(
                [
                    'email' => ['The provided credentials are incorrect.'],
                ]
            );
        }

        // Regenerate session to prevent session fixation attacks
        // Only applicable when sessions are available (e.g., SPA with Sanctum)
        if ($request->hasSession() && $request->session()) {
            $request->session()->regenerate();
        }

        // Revoke all tokens if requested
        if ($request->has('revoke_tokens') && $request->revoke_tokens) {
            $user->tokens()->delete();
        }

        // Implement concurrent session limit
        $maxSessions = config('auth.max_concurrent_sessions', 5);
        $currentTokenCount = $user->tokens()->count();

        if ($currentTokenCount >= $maxSessions) {
            // Delete oldest tokens to maintain the limit
            $tokensToDelete = $currentTokenCount - $maxSessions + 1;
            $user->tokens()
                ->orderBy('created_at', 'asc')
                ->limit($tokensToDelete)
                ->delete();
        }

        // Create token with appropriate scopes based on user role
        $requestedScopes = $request->input('scopes', null);
        $token = $this->createTokenWithScopes(
            $user,
            $request->device_name ?? 'api-token',
            $requestedScopes
        );

        return response()->json(
            [
                'user' => [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'email'             => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'access_token' => $token,
                'token'        => $token, // For backward compatibility
                'token_type'   => 'Bearer',
                'expires_in'   => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
            ]
        );
    }

    /**
     * Logout user (revoke current token).
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout user",
     *     description="Revoke the current access token",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(
            [
                'message' => 'Successfully logged out',
            ]
        );
    }

    /**
     * Logout from all devices.
     *
     * @OA\Post(
     *     path="/api/auth/logout-all",
     *     summary="Logout from all devices",
     *     description="Revoke all access tokens for the user",
     *     operationId="logoutAll",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="Successfully logged out from all devices",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="Successfully logged out from all devices")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(
            [
                'message' => 'Successfully logged out from all devices',
            ]
        );
    }

    /**
     * Refresh access token.
     *
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     summary="Refresh access token",
     *     description="Get a new access token by revoking the current one",
     *     operationId="refreshToken",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="access_token", type="string", example="3|newTokenHere..."),
     * @OA\Property(property="token_type",   type="string", example="Bearer"),
     * @OA\Property(property="expires_in",   type="integer", nullable=true)
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokenName = $request->user()->currentAccessToken()->name;

        // Get current token's abilities/scopes before deleting it
        $currentScopes = $request->user()->currentAccessToken()->abilities ?? [];

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        // Create new token with same scopes
        $token = $this->createTokenWithScopes($user, $tokenName, $currentScopes);

        return response()->json(
            [
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'expires_in'   => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
            ]
        );
    }

    /**
     * Get authenticated user.
     *
     * @OA\Get(
     *     path="/api/auth/user",
     *     summary="Get current user",
     *     description="Get the authenticated user's information",
     *     operationId="getUser",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="User information retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="user",
     *                 type="object",
     * @OA\Property(property="id",                type="integer", example=1),
     * @OA\Property(property="name",              type="string", example="John Doe"),
     * @OA\Property(property="email",             type="string", format="email", example="john@example.com"),
     * @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
     * @OA\Property(property="created_at",        type="string", format="date-time"),
     * @OA\Property(property="updated_at",        type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json(
            [
                'user' => $request->user(),
            ]
        );
    }
}
