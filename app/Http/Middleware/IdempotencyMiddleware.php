<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * The cache duration for idempotency keys (in seconds).
     */
    private const CACHE_DURATION = 86400; // 24 hours

    /**
     * How long a running request holds its key (in seconds). The SDKs time a
     * request out after 30 seconds and then retry it with the same key, so the
     * lock must outlive that. A finished request releases the lock at once.
     */
    private const LOCK_DURATION = 120;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply idempotency to POST/PUT/PATCH requests
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // If no idempotency key provided, proceed normally
        if (! $idempotencyKey) {
            return $next($request);
        }

        // Validate idempotency key format (UUID or similar)
        if (! $this->isValidIdempotencyKey($idempotencyKey)) {
            return response()->json([
                'error'   => 'Invalid idempotency key format',
                'message' => 'Idempotency-Key must be a valid UUID or string between 16-64 characters',
            ], 400);
        }

        // Generate cache key based on user, endpoint, and idempotency key
        $cacheKey = $this->generateCacheKey($request, $idempotencyKey);

        // Check if we have a cached response
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $this->replayOrReject($request, $cachedData, $idempotencyKey);
        }

        // Lock the idempotency key to prevent race conditions
        $lockKey = $cacheKey . ':lock';
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);

        if (! $lock->get()) {
            return response()->json([
                'error'   => 'Request in progress',
                'message' => 'Another request with the same idempotency key is currently being processed',
            ], 409);
        }

        try {
            // Read again now that we hold the lock. Another request with this
            // key may have finished and released the lock after the read
            // above; running this one as well would repeat its effect.
            $cachedData = Cache::get($cacheKey);

            if ($cachedData) {
                return $this->replayOrReject($request, $cachedData, $idempotencyKey);
            }

            // Process the request
            $response = $next($request);

            // Only cache successful responses
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $this->cacheResponse($cacheKey, $request, $response);

                // Add idempotency headers
                $response->headers->set('X-Idempotency-Key', $idempotencyKey);
                $response->headers->set('X-Idempotency-Replayed', 'false');
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Replay the stored response when the request matches the one that was
     * stored under this key, or refuse a different request with the same key.
     *
     * @param  array<string, mixed>  $cachedData
     */
    private function replayOrReject(Request $request, array $cachedData, string $idempotencyKey): Response
    {
        if (! $this->requestMatches($request, $cachedData['request'])) {
            // Different request with same idempotency key
            return response()->json([
                'error'   => 'Idempotency key already used',
                'message' => 'The provided idempotency key has already been used with different request parameters',
            ], 409);
        }

        // Return the cached response
        $response = response()->json(
            $cachedData['response']['content'],
            $cachedData['response']['status']
        );

        // Add idempotency headers
        $response->headers->set('X-Idempotency-Key', $idempotencyKey);
        $response->headers->set('X-Idempotency-Replayed', 'true');

        // Restore original headers
        foreach ($cachedData['response']['headers'] as $key => $value) {
            if (! in_array($key, ['X-Idempotency-Key', 'X-Idempotency-Replayed'])) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }

    /**
     * Validate the idempotency key format.
     */
    private function isValidIdempotencyKey(string $key): bool
    {
        // Accept UUIDs or alphanumeric strings between 16-64 characters
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $key)) {
            return true;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $key)) {
            return true;
        }

        return false;
    }

    /**
     * Generate cache key for the idempotency check.
     */
    private function generateCacheKey(Request $request, string $idempotencyKey): string
    {
        $userId = $request->user() ? $request->user()->id : 'anonymous';
        $endpoint = $request->path();

        return "idempotency:{$userId}:{$endpoint}:{$idempotencyKey}";
    }

    /**
     * Check if the current request matches the cached request.
     */
    private function requestMatches(Request $currentRequest, array $cachedRequest): bool
    {
        // Compare method
        if ($currentRequest->method() !== $cachedRequest['method']) {
            return false;
        }

        // Compare request body
        $currentBody = $currentRequest->all();
        $cachedBody = $cachedRequest['body'];

        // Sort arrays to ensure consistent comparison
        ksort($currentBody);
        ksort($cachedBody);

        return json_encode($currentBody) === json_encode($cachedBody);
    }

    /**
     * Cache the response for future idempotent requests.
     */
    private function cacheResponse(string $cacheKey, Request $request, Response $response): void
    {
        $data = [
            'request' => [
                'method'    => $request->method(),
                'body'      => $request->all(),
                'timestamp' => now()->toIso8601String(),
            ],
            'response' => [
                'content' => json_decode($response->getContent(), true),
                'status'  => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ],
        ];

        Cache::put($cacheKey, $data, self::CACHE_DURATION);

        // Log idempotency key usage
        Log::info('Idempotency key stored', [
            'key'        => $cacheKey,
            'user_id'    => $request->user()?->id,
            'endpoint'   => $request->path(),
            'expires_at' => now()->addSeconds(self::CACHE_DURATION)->toIso8601String(),
        ]);
    }
}
