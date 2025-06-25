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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $token = $request->user()->currentAccessToken();
            
            // Check if token has an expiration date and if it has expired
            if ($token->expires_at && $token->expires_at->isPast()) {
                $token->delete();
                
                return response()->json([
                    'message' => 'Token has expired'
                ], 401);
            }
        }
        
        return $next($request);
    }
}