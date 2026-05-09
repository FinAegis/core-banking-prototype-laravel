<?php

/**
 * IdempotencyKey middleware — enforces Idempotency-Key header presence
 * on mutating endpoints AND replays cached responses on duplicate requests.
 *
 * Distinct from `IdempotencyMiddleware` (alias `idempotency`) which only
 * caches when the caller opts in. This one (alias `idempotency.required`)
 * REJECTS missing keys with ERR_VALIDATION_001 — required for the Plan B
 * commercial endpoints where a missing key is a contract violation.
 *
 * Keyed cache shape mirrors IdempotencyMiddleware so existing replay
 * semantics still apply.
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md (Q1 acceptance criterion)
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyKey
{
    private const CACHE_TTL_SECONDS = 86_400; // 24h

    public function handle(Request $request, Closure $next): Response
    {
        // Only mutating verbs require an idempotency key.
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');

        if ($idempotencyKey === '') {
            return ErrorResponse::make('ERR_VALIDATION_001');
        }

        if (! $this->isValidKeyFormat($idempotencyKey)) {
            return ErrorResponse::make('ERR_VALIDATION_001', [
                'message' => 'Idempotency-Key must be a UUID or 16-64 char alphanumeric string.',
            ], 422);
        }

        $cacheKey = $this->generateCacheKey($request, $idempotencyKey);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            $cachedRequest = is_array($cached['request'] ?? null) ? $cached['request'] : [];
            if (! $this->requestMatches($request, $cachedRequest)) {
                return ErrorResponse::make('ERR_VALIDATION_001', [
                    'message' => 'Idempotency-Key already used with a different request body.',
                ], 409);
            }

            return $this->buildReplayResponse($cached, $idempotencyKey);
        }

        $lock = Cache::lock($cacheKey . ':lock', 30);

        if (! $lock->get()) {
            return ErrorResponse::make('ERR_VALIDATION_001', [
                'message' => 'A request with the same Idempotency-Key is currently in flight.',
            ], 409);
        }

        try {
            $response = $next($request);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->cacheResponse($cacheKey, $request, $response);
                $response->headers->set('X-Idempotency-Key', $idempotencyKey);
                $response->headers->set('X-Idempotency-Replayed', 'false');
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function isValidKeyFormat(string $key): bool
    {
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $key) === 1) {
            return true;
        }

        return preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $key) === 1;
    }

    private function generateCacheKey(Request $request, string $idempotencyKey): string
    {
        $userId = $request->user() !== null ? (string) $request->user()->getAuthIdentifier() : 'anonymous';

        return 'idempotency:' . $userId . ':' . $request->path() . ':' . $idempotencyKey;
    }

    /**
     * @param array<string, mixed> $cachedRequest
     */
    private function requestMatches(Request $current, array $cachedRequest): bool
    {
        $cachedMethod = is_string($cachedRequest['method'] ?? null) ? (string) $cachedRequest['method'] : '';
        if ($current->method() !== $cachedMethod) {
            return false;
        }

        $currentBody = $current->all();
        $cachedBody = is_array($cachedRequest['body'] ?? null) ? $cachedRequest['body'] : [];

        ksort($currentBody);
        ksort($cachedBody);

        return (string) json_encode($currentBody) === (string) json_encode($cachedBody);
    }

    /**
     * @param array<string, mixed> $cached
     */
    private function buildReplayResponse(array $cached, string $idempotencyKey): Response
    {
        $cachedResponse = is_array($cached['response'] ?? null) ? $cached['response'] : [];
        $content = $cachedResponse['content'] ?? null;
        $status = isset($cachedResponse['status']) && is_int($cachedResponse['status'])
            ? $cachedResponse['status']
            : 200;
        $cachedHeaders = is_array($cachedResponse['headers'] ?? null) ? $cachedResponse['headers'] : [];

        $response = response()->json($content, $status);

        $response->headers->set('X-Idempotency-Key', $idempotencyKey);
        $response->headers->set('X-Idempotency-Replayed', 'true');

        $reservedHeaders = ['x-idempotency-key', 'x-idempotency-replayed'];
        foreach ($cachedHeaders as $header => $values) {
            if (in_array(strtolower((string) $header), $reservedHeaders, true)) {
                continue;
            }

            $valuesList = is_array($values) ? $values : [$values];
            foreach ($valuesList as $value) {
                $response->headers->set((string) $header, (string) $value, false);
            }
        }

        return $response;
    }

    private function cacheResponse(string $cacheKey, Request $request, Response $response): void
    {
        $content = $response->getContent();
        $decoded = is_string($content) ? json_decode($content, true) : null;

        $payload = [
            'request' => [
                'method'    => $request->method(),
                'body'      => $request->all(),
                'timestamp' => now()->toIso8601String(),
            ],
            'response' => [
                'content' => $decoded,
                'status'  => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ],
        ];

        Cache::put($cacheKey, $payload, self::CACHE_TTL_SECONDS);
    }
}
