<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;
use App\Models\ApiKeyLog;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = 'read'): Response
    {
        $startTime = microtime(true);
        
        // Extract API key from Authorization header
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API key is required',
            ], 401);
        }

        $apiKeyString = substr($authHeader, 7); // Remove 'Bearer ' prefix
        
        // Verify API key
        $apiKey = ApiKey::verify($apiKeyString);
        if (!$apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API key',
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
        
        // Add API key and user to request
        $request->merge(['api_key' => $apiKey]);
        $request->setUserResolver(function () use ($apiKey) {
            return $apiKey->user;
        });

        // Process request
        $response = $next($request);
        
        // Log API request (async in production)
        $this->logApiRequest($apiKey, $request, $response, $startTime);

        return $response;
    }

    /**
     * Log the API request
     */
    protected function logApiRequest(ApiKey $apiKey, Request $request, Response $response, float $startTime): void
    {
        $responseTime = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds
        
        // Determine what to log based on environment
        $logHeaders = config('app.debug', false);
        $logBody = config('app.debug', false);
        
        $logData = [
            'api_key_id' => $apiKey->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'response_code' => $response->getStatusCode(),
            'response_time' => $responseTime,
        ];

        if ($logHeaders) {
            $logData['request_headers'] = $request->headers->all();
        }

        if ($logBody && $request->getContent()) {
            $logData['request_body'] = $request->all();
        }

        // In production, this should be queued
        ApiKeyLog::create($logData);
    }
}