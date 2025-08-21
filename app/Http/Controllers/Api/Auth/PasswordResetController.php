<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Send a password reset link to the given user.
     *
     * @OA\Post(
     *     path="/api/auth/forgot-password",
     *     operationId="forgotPassword",
     *     tags={"Authentication"},
     *     summary="Request password reset link",
     *     description="Send a password reset link to the user's email address",
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"email"},
     *
     * @OA\Property(property="email",                              type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Password reset link sent",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",                            type="string", example="If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     * @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *
     * @OA\Response(
     *         response=429,
     *         description="Too many requests",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",                            type="string", example="Too many requests. Please try again later.")
     *         )
     *     )
     * )
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Implement rate limiting to prevent abuse
        $key = 'password-reset:' . $request->ip();
        $maxAttempts = 5; // 5 attempts
        $decayMinutes = 60; // per hour

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many password reset attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        // Check if user exists but don't reveal this information
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Only send reset link if user exists
            $status = Password::sendResetLink(
                $request->only('email')
            );

            // Log the attempt for security monitoring
            Log::info('Password reset requested', [
                'email'      => $request->email,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status'     => $status,
            ]);
        } else {
            // Log failed attempt for security monitoring
            Log::warning('Password reset requested for non-existent email', [
                'email'      => $request->email,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Add a small delay to prevent timing attacks
            usleep(random_int(100000, 500000)); // 100-500ms random delay
        }

        // Always return the same success message to prevent user enumeration
        return response()->json([
            'message' => 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.',
        ]);
    }

    /**
     * Reset the given user's password.
     *
     * @OA\Post(
     *     path="/api/auth/reset-password",
     *     operationId="resetPassword",
     *     tags={"Authentication"},
     *     summary="Reset password",
     *     description="Reset user password using token received via email",
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"email", "password", "password_confirmation", "token"},
     *
     * @OA\Property(property="email",                              type="string", format="email", example="user@example.com"),
     * @OA\Property(property="password",                           type="string", format="password", example="newpassword123"),
     * @OA\Property(property="password_confirmation",              type="string", format="password", example="newpassword123"),
     * @OA\Property(property="token",                              type="string", example="reset-token-here")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Password reset successful",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",                            type="string", example="Your password has been reset.")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error or invalid token",
     *
     * @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate(
            [
                'token'    => 'required',
                'email'    => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]
        );

        // Implement rate limiting for password reset attempts
        $key = 'password-reset-attempt:' . $request->ip();
        $maxAttempts = 5; // 5 attempts
        $decayMinutes = 60; // per hour

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => ["Too many reset attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(
                    [
                        'password' => Hash::make($password),
                    ]
                )->setRememberToken(Str::random(60));

                $user->save();

                // Revoke all existing tokens for security
                $user->tokens()->delete();

                event(new PasswordReset($user));

                // Log successful password reset
                Log::info('Password successfully reset', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            // Clear rate limiter on successful reset
            RateLimiter::clear($key);

            return response()->json(['message' => __($status)]);
        }

        // Log failed reset attempt
        Log::warning('Failed password reset attempt', [
            'email'  => $request->email,
            'ip'     => $request->ip(),
            'status' => $status,
        ]);

        throw ValidationException::withMessages(
            [
                'email' => [__($status)],
            ]
        );
    }
}
