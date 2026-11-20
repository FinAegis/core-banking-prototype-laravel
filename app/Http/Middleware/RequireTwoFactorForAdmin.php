<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\StaffRoles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorForAdmin
{
    /**
     * Handle an incoming request.
     *
     * Require 2FA for admin users.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Administrators are `admin` and `super_admin` (StaffRoles::ADMINS).
        $isAdmin = StaffRoles::userHasAny($user, StaffRoles::ADMINS);

        if ($isAdmin) {
            // Check if 2FA is enabled and confirmed
            if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
                return response()->json([
                    'error'           => 'TWO_FACTOR_REQUIRED',
                    'message'         => 'Two-factor authentication is required for admin accounts.',
                    'action_required' => 'enable_2fa',
                    'instructions'    => 'Please enable two-factor authentication at /api/auth/2fa/enable',
                    'setup_required'  => true,
                ], 403);
            }

            // For production, we might also want to verify 2FA was recently confirmed
            // This prevents session hijacking after initial 2FA setup
            $sessionKey = 'two_factor_verified_' . $user->id;
            $lastConfirmedAt = session('2fa_confirmed_at');
            $maxAge = config('auth.two_factor_reconfirm_minutes', 120); // 2 hours

            // Check both session methods for compatibility
            if (! session($sessionKey) && (! $lastConfirmedAt || now()->diffInMinutes($lastConfirmedAt) > $maxAge)) {
                // In a full implementation, we'd redirect to 2FA verification
                // For API, we return an error requiring 2FA verification
                return response()->json([
                    'error'                 => 'TWO_FACTOR_VERIFICATION_REQUIRED',
                    'message'               => 'Please verify your two-factor authentication code.',
                    'action_required'       => 'verify_2fa',
                    'instructions'          => 'Submit your 2FA code to /api/auth/2fa/verify',
                    'verification_required' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}
