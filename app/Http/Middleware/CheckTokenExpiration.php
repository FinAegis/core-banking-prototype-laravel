<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $token = $request->user()->currentAccessToken();

            // Skip expiration check for TransientToken (used in testing)
            if ($token instanceof \Laravel\Sanctum\TransientToken) {
                return $next($request);
            }

            // Check if token has an expiration date and if it has expired
            // For PersonalAccessToken models, expires_at is an Eloquent attribute, not a property
            if (isset($token->expires_at) && $token->expires_at && $token->expires_at->isPast()) {
                $token->delete();

                return response()->json(
                    [
                        'message' => 'Token has expired',
                    ],
                    401
                );
            }
        }

        return $next($request);
    }
}
