<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\IpBlockingService;
use App\Traits\HasApiScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use HasApiScopes;

    public function __construct(
        private readonly IpBlockingService $ipBlockingService
    ) {
    }

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
     * @OA\Property(property="message",           type="string", example="The provided credentials are incorrect."),
     * @OA\Property(property="errors",            type="object",
     * @OA\Property(property="email",             type="array",
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

        // Regenerate session to prevent session fixation attacks (only for web)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Create token with abilities based on user role
        $abilities = $this->getDefaultScopesForUser($user);
        $token = $user->createToken($request->device_name ?? 'web', $abilities);

        // Check and enforce concurrent session limits
        $this->enforceSessionLimits($user);

        return response()->json(
            [
                'user'         => $user,
                'access_token' => $token->plainTextToken,
                'token_type'   => 'Bearer',
                'expires_in'   => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
            ]
        );
    }

    /**
     * Logout user and revoke tokens.
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout user",
     *     description="Logout the authenticated user and revoke all their tokens",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
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
     * Get current user.
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
     *         description="Unauthenticated",
     *
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
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

    /**
     * Enforce concurrent session limits by removing oldest tokens.
     */
    private function enforceSessionLimits(User $user): void
    {
        $maxSessions = config('auth.max_concurrent_sessions', 5);
        $tokenCount = $user->tokens()->count();

        if ($tokenCount > $maxSessions) {
            // Delete oldest tokens
            $tokensToDelete = $tokenCount - $maxSessions;
            $user->tokens()
                ->orderBy('created_at', 'asc')
                ->limit($tokensToDelete)
                ->delete();
        }
    }
}
