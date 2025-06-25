<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ApiRateLimitMiddleware
{
    /**
     * Rate limit configurations for different endpoint types
     */
    private const RATE_LIMITS = [
        'auth' => [
            'limit' => 5,      // 5 requests
            'window' => 60,    // per minute
            'block_duration' => 300, // 5 minute lockout after limit exceeded
        ],
        'transaction' => [
            'limit' => 30,     // 30 requests
            'window' => 60,    // per minute
            'block_duration' => 60,
        ],
        'query' => [
            'limit' => 100,    // 100 requests
            'window' => 60,    // per minute
            'block_duration' => 30,
        ],
        'admin' => [
            'limit' => 200,    // 200 requests
            'window' => 60,    // per minute
            'block_duration' => 60,
        ],
        'public' => [
            'limit' => 60,     // 60 requests
            'window' => 60,    // per minute
            'block_duration' => 30,
        ],
        'webhook' => [
            'limit' => 1000,   // 1000 requests
            'window' => 60,    // per minute
            'block_duration' => 0, // No lockout for webhooks
        ]
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $rateLimitType = 'query'): SymfonyResponse
    {
        // Skip rate limiting if disabled
        if (!config('rate_limiting.enabled', true)) {
            return $next($request);
        }
        
        // Skip rate limiting in testing environment unless explicitly enabled
        if (app()->environment('testing') && !config('rate_limiting.force_in_tests', false)) {
            return $next($request);
        }
        
        // Get rate limit configuration
        $config = self::RATE_LIMITS[$rateLimitType] ?? self::RATE_LIMITS['query'];
        
        // Generate unique key for this client/endpoint combination
        $key = $this->generateRateLimitKey($request, $rateLimitType);
        $blockKey = $key . ':blocked';
        
        // Check if client is currently blocked
        if (Cache::has($blockKey)) {
            $blockedUntil = Cache::get($blockKey);
            return $this->rateLimitExceededResponse($request, $config, $blockedUntil);
        }
        
        // Get current request count
        $currentCount = Cache::get($key, 0);
        
        // Check if limit exceeded
        if ($currentCount >= $config['limit']) {
            // Block the client if block duration is set
            if ($config['block_duration'] > 0) {
                $blockedUntil = now()->addSeconds($config['block_duration']);
                Cache::put($blockKey, $blockedUntil, $config['block_duration']);
                
                // Log rate limit violation
                Log::warning('Rate limit exceeded with blocking', [
                    'ip' => $request->ip(),
                    'user_id' => $request->user()?->id,
                    'endpoint' => $request->path(),
                    'rate_limit_type' => $rateLimitType,
                    'blocked_until' => $blockedUntil,
                ]);
            }
            
            return $this->rateLimitExceededResponse($request, $config);
        }
        
        // Increment request count
        Cache::put($key, $currentCount + 1, $config['window']);
        
        // Add rate limit headers to response
        $response = $next($request);
        
        return $this->addRateLimitHeaders($response, $config, $currentCount + 1, $key);
    }

    /**
     * Generate unique rate limit key for the request
     */
    private function generateRateLimitKey(Request $request, string $rateLimitType): string
    {
        $identifier = $this->getClientIdentifier($request);
        $endpoint = $this->normalizeEndpoint($request->path());
        
        return "rate_limit:{$rateLimitType}:{$identifier}:{$endpoint}";
    }

    /**
     * Get unique client identifier (prefer user ID, fallback to IP)
     */
    private function getClientIdentifier(Request $request): string
    {
        if ($user = $request->user()) {
            return "user:{$user->id}";
        }
        
        // For anonymous requests, use IP with more specific tracking
        $ip = $request->ip();
        $userAgent = substr(md5($request->userAgent() ?? ''), 0, 8);
        
        return "ip:{$ip}:{$userAgent}";
    }

    /**
     * Normalize endpoint path for consistent rate limiting
     */
    private function normalizeEndpoint(string $path): string
    {
        // Replace dynamic segments with placeholders
        $normalized = preg_replace('/\/\d+/', '/{id}', $path);
        $normalized = preg_replace('/\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/', '/{uuid}', $normalized);
        
        return $normalized;
    }

    /**
     * Create rate limit exceeded response
     */
    private function rateLimitExceededResponse(Request $request, array $config, ?\Carbon\Carbon $blockedUntil = null): JsonResponse
    {
        $retryAfter = $blockedUntil ? $blockedUntil->diffInSeconds(now()) : $config['window'];
        
        $headers = [
            'X-RateLimit-Limit' => $config['limit'],
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($config['window'])->timestamp,
            'Retry-After' => $retryAfter,
        ];

        if ($blockedUntil) {
            $headers['X-RateLimit-Blocked-Until'] = $blockedUntil->toISOString();
        }

        $message = $blockedUntil 
            ? "Rate limit exceeded. Access blocked until {$blockedUntil->toDateTimeString()}."
            : "Rate limit exceeded. Try again in {$retryAfter} seconds.";

        return response()->json([
            'error' => 'Rate limit exceeded',
            'message' => $message,
            'retry_after' => $retryAfter,
            'limit' => $config['limit'],
            'window' => $config['window'],
        ], 429, $headers);
    }

    /**
     * Add rate limit headers to successful response
     */
    private function addRateLimitHeaders(SymfonyResponse $response, array $config, int $currentCount, string $key): SymfonyResponse
    {
        $remaining = max(0, $config['limit'] - $currentCount);
        $resetTime = now()->addSeconds($config['window'])->timestamp;
        
        $response->headers->set('X-RateLimit-Limit', $config['limit']);
        $response->headers->set('X-RateLimit-Remaining', $remaining);
        $response->headers->set('X-RateLimit-Reset', $resetTime);
        $response->headers->set('X-RateLimit-Window', $config['window']);
        
        return $response;
    }

    /**
     * Get rate limit configuration for a specific type
     */
    public static function getRateLimitConfig(string $type): array
    {
        return self::RATE_LIMITS[$type] ?? self::RATE_LIMITS['query'];
    }

    /**
     * Get all available rate limit types
     */
    public static function getAvailableTypes(): array
    {
        return array_keys(self::RATE_LIMITS);
    }
}