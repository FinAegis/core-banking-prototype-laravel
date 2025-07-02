<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

class TwoFactorAuthController extends Controller
{
    /**
     * Enable two-factor authentication for the user.
     *
     * @OA\Post(
     *     path="/api/auth/2fa/enable",
     *     operationId="enable2FA",
     *     tags={"Authentication"},
     *     summary="Enable two-factor authentication",
     *     description="Enable 2FA for the authenticated user",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="2FA enabled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication enabled successfully."),
     *             @OA\Property(property="secret", type="string", example="JBSWY3DPEHPK3PXP"),
     *             @OA\Property(property="qr_code", type="string", example="data:image/png;base64,..."),
     *             @OA\Property(property="recovery_codes", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function enable(Request $request, EnableTwoFactorAuthentication $enable)
    {
        $enable($request->user());
        
        $user = $request->user()->fresh();
        
        return response()->json([
            'message' => 'Two-factor authentication enabled successfully.',
            'secret' => decrypt($user->two_factor_secret),
            'qr_code' => $user->twoFactorQrCodeSvg(),
            'recovery_codes' => json_decode(decrypt($user->two_factor_recovery_codes), true)
        ]);
    }

    /**
     * Confirm and finalize enabling two-factor authentication.
     *
     * @OA\Post(
     *     path="/api/auth/2fa/confirm",
     *     operationId="confirm2FA",
     *     tags={"Authentication"},
     *     summary="Confirm two-factor authentication",
     *     description="Confirm 2FA setup with verification code",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="2FA confirmed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication confirmed successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid verification code"
     *     )
     * )
     */
    public function confirm(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        $request->validate(['code' => 'required|string']);
        
        $user = $request->user();
        
        if (!$user->two_factor_secret ||
            !$provider->verify(decrypt($user->two_factor_secret), $request->code)) {
            return response()->json([
                'message' => 'The provided two factor authentication code was invalid.'
            ], 422);
        }
        
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
        
        return response()->json([
            'message' => 'Two-factor authentication confirmed successfully.'
        ]);
    }

    /**
     * Disable two-factor authentication for the user.
     *
     * @OA\Post(
     *     path="/api/auth/2fa/disable",
     *     operationId="disable2FA",
     *     tags={"Authentication"},
     *     summary="Disable two-factor authentication",
     *     description="Disable 2FA for the authenticated user",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", format="password", example="current-password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="2FA disabled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication disabled successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid password"
     *     )
     * )
     */
    public function disable(Request $request, DisableTwoFactorAuthentication $disable)
    {
        $request->validate([
            'password' => 'required|string|current_password:sanctum'
        ]);
        
        $disable($request->user());
        
        return response()->json([
            'message' => 'Two-factor authentication disabled successfully.'
        ]);
    }

    /**
     * Verify two-factor authentication code.
     *
     * @OA\Post(
     *     path="/api/auth/2fa/verify",
     *     operationId="verify2FA",
     *     tags={"Authentication"},
     *     summary="Verify two-factor authentication code",
     *     description="Verify 2FA code during login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="123456"),
     *             @OA\Property(property="recovery_code", type="string", example="recovery-code", description="Use recovery code instead of 2FA code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="2FA verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication verified successfully."),
     *             @OA\Property(property="token", type="string", example="1|laravel_sanctum_token...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid code"
     *     )
     * )
     */
    public function verify(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        $request->validate([
            'code' => 'required_without:recovery_code|string',
            'recovery_code' => 'required_without:code|string'
        ]);
        
        $user = $request->user();
        
        if ($request->has('recovery_code')) {
            $codes = $user->recoveryCodes();
            
            if (!in_array($request->recovery_code, $codes)) {
                return response()->json([
                    'message' => 'The provided recovery code was invalid.'
                ], 422);
            }
            
            $user->replaceRecoveryCode($request->recovery_code);
        } else {
            if (!$provider->verify(decrypt($user->two_factor_secret), $request->code)) {
                return response()->json([
                    'message' => 'The provided two factor authentication code was invalid.'
                ], 422);
            }
        }
        
        // Generate new token after successful 2FA
        $token = $user->createToken('api-token')->plainTextToken;
        
        return response()->json([
            'message' => 'Two-factor authentication verified successfully.',
            'token' => $token
        ]);
    }

    /**
     * Get new recovery codes.
     *
     * @OA\Post(
     *     path="/api/auth/2fa/recovery-codes",
     *     operationId="regenerateRecoveryCodes",
     *     tags={"Authentication"},
     *     summary="Regenerate recovery codes",
     *     description="Generate new set of recovery codes",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Recovery codes regenerated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Recovery codes regenerated successfully."),
     *             @OA\Property(property="recovery_codes", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();
        
        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(RecoveryCode::generate())),
        ])->save();
        
        return response()->json([
            'message' => 'Recovery codes regenerated successfully.',
            'recovery_codes' => json_decode(decrypt($user->two_factor_recovery_codes), true)
        ]);
    }
}