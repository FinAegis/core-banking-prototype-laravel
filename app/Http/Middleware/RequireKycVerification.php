<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks financial operations for unverified (Level 0) users.
 *
 * Apply to route groups that require KYC verification.
 * KYC payment endpoints and read-only routes should NOT have this middleware.
 */
class RequireKycVerification
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if no authenticated user (auth middleware handles this separately)
        if ($user === null) {
            return $next($request);
        }

        if (! $user->hasCompletedKyc()) {
            return response()->json([
                'error'   => 'ERR_KYC_REQUIRED',
                'message' => 'Identity verification required. Please complete KYC to unlock this feature.',
            ], 403);
        }

        return $next($request);
    }
}
