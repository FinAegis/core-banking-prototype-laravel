<?php

namespace App\Http\Middleware;

use App\Domain\User\Values\UserRoles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorForAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole(UserRoles::ADMIN->value)) {
            // Check if 2FA is enabled and confirmed
            if (! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
                return response()->json([
                    'error'          => 'Two-Factor Authentication Required',
                    'message'        => 'Administrator accounts must have two-factor authentication enabled.',
                    'setup_required' => true,
                ], 403);
            }

            // Check if 2FA has been verified for this session
            $sessionKey = 'two_factor_verified_' . $user->id;
            if (! session($sessionKey)) {
                return response()->json([
                    'error'                 => 'Two-Factor Verification Required',
                    'message'               => 'Please verify your two-factor authentication code.',
                    'verification_required' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}
