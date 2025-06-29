<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class AuthenticateApiOrSanctum
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = 'read'): Response
    {
        $bearerToken = $request->bearerToken();
        
        // Check if it's an API key (starts with 'fak_')
        if ($bearerToken && str_starts_with($bearerToken, 'fak_')) {
            return $this->handleApiKeyAuth($request, $next, $permission);
        }
        
        // Otherwise, try Sanctum authentication
        return $this->handleSanctumAuth($request, $next);
    }
    
    /**
     * Handle API key authentication
     */
    protected function handleApiKeyAuth(Request $request, Closure $next, string $permission): Response
    {
        $apiKeyString = $request->bearerToken();
        
        // Verify API key
        $apiKey = ApiKey::verify($apiKeyString);
        if (!$apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API key',
            ], 401);
        }

        // Check expiration
        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API key expired',
            ], 401);
        }

        // Check IP restrictions
        if (!$apiKey->isIpAllowed($request->ip())) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Access denied from this IP address',
            ], 403);
        }

        // Check permissions
        if (!$apiKey->hasPermission($permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Insufficient permissions',
            ], 403);
        }

        // Record usage
        $apiKey->recordUsage($request->ip());
        
        // Set the user resolver
        $request->setUserResolver(function () use ($apiKey) {
            return $apiKey->user;
        });

        return $next($request);
    }
    
    /**
     * Handle Sanctum authentication
     */
    protected function handleSanctumAuth(Request $request, Closure $next): Response
    {
        // Apply Sanctum's stateful middleware for SPA requests
        $ensureStateful = app(EnsureFrontendRequestsAreStateful::class);
        
        return $ensureStateful->handle($request, function ($request) use ($next) {
            // Check if user is authenticated via Sanctum
            if (!auth('sanctum')->check()) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required',
                ], 401);
            }
            
            return $next($request);
        });
    }
}