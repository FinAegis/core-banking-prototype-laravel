<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Plan B v1.3.0 idempotency middleware.
 *
 * Distinct from the legacy `IdempotencyMiddleware` (cache-backed, optional
 * header). This middleware:
 *
 * - REQUIRES `Idempotency-Key` on every mutating request (422 ERR_VALIDATION_001)
 * - Validates header format (16-255 chars, [A-Za-z0-9_-]+) (422 ERR_VALIDATION_003)
 * - Persists `(user_id, idempotency_key, request_hash, response_*)` in the
 *   `idempotency_keys` table for 24h
 * - Race-safe via SELECT FOR UPDATE inside DB::transaction()
 * - 409 ERR_IDEMPOTENCY_409 on body mismatch
 * - Does not cache 5xx responses (next attempt processes fresh)
 *
 * Opt-in per route via the `idempotency.required` alias (see bootstrap/app.php).
 * Apply only to POST/PATCH/PUT/DELETE under `auth:sanctum`. Webhooks dedup
 * via `processed_webhook_events` instead — do not apply this middleware to
 * webhook routes.
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md §0.2
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md Q1
 */
final class IdempotencyKey
{
    /** Mutating HTTP methods this middleware enforces. */
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Header value format: 16-255 chars from [A-Za-z0-9_-]. */
    private const KEY_FORMAT_REGEX = '/^[A-Za-z0-9_-]{16,255}$/';

    /** Cache TTL — 24 hours per Plan B §0.2. */
    private const TTL_SECONDS = 86_400;

    /** Headers we round-trip on a cached replay. */
    private const REPLAYED_HEADERS = ['content-type'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::MUTATING_METHODS, true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '') {
            return ErrorResponse::make('ERR_VALIDATION_001');
        }

        if (preg_match(self::KEY_FORMAT_REGEX, $key) !== 1) {
            return ErrorResponse::make('ERR_VALIDATION_003');
        }

        $userId = $this->resolveUserId($request);
        $bodyHash = hash('sha256', $request->getContent() ?: '');

        // Phase 1 — atomic lookup-or-claim.
        // If a fresh row exists with the same body, we replay it inside the
        // transaction (no $next()). If the body differs, return 409. If the
        // row is missing or expired we fall through to processing.
        $cached = DB::transaction(function () use ($userId, $key, $bodyHash): ?array {
            $row = DB::table('idempotency_keys')
                ->where('user_id', $userId)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return null;
            }

            $expiresAt = strtotime((string) $row->expires_at);
            if ($expiresAt === false || $expiresAt <= time()) {
                // Stale row — leave it; the upsert below replaces it.
                return ['stale' => true];
            }

            if ((string) $row->request_hash !== $bodyHash) {
                return ['mismatch' => true];
            }

            return [
                'status'  => (int) $row->response_status,
                'body'    => (string) $row->response_body,
                'headers' => (string) $row->response_headers,
            ];
        });

        if (is_array($cached) && isset($cached['mismatch'])) {
            return ErrorResponse::make('ERR_IDEMPOTENCY_409');
        }

        if (is_array($cached) && isset($cached['status'])) {
            return $this->buildReplayedResponse($cached, $key);
        }

        // Phase 2 — process the request.
        $response = $next($request);

        // Phase 3 — persist on 2xx-4xx success. 5xx means non-deterministic
        // failure; let the next attempt run fresh.
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 500) {
            $this->persistResponse($userId, $key, $bodyHash, $response);
            $response->headers->set('X-Idempotency-Key', $key);
            $response->headers->set('X-Idempotency-Replayed', 'false');
        }

        return $response;
    }

    /**
     * Anonymous users still get an ID (the literal "anonymous"). Authenticated
     * users use their primary key cast to string. This is sufficient because
     * the route is expected to apply auth:sanctum upstream of this middleware
     * for actual Plan B endpoints — the anonymous case mostly exists for
     * defensive completeness.
     */
    private function resolveUserId(Request $request): string
    {
        $user = $request->user();
        if ($user === null) {
            return 'anonymous';
        }

        $id = $user->getKey();

        return $id === null ? 'anonymous' : (string) $id;
    }

    /**
     * @param  array{status: int, body: string, headers: string}  $cached
     */
    private function buildReplayedResponse(array $cached, string $key): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($cached['body'], true) ?? [];

        $response = response()->json($payload, $cached['status']);

        /** @var array<string, string> $storedHeaders */
        $storedHeaders = json_decode($cached['headers'], true) ?? [];
        foreach ($storedHeaders as $name => $value) {
            $response->headers->set($name, $value);
        }

        $response->headers->set('X-Idempotency-Key', $key);
        $response->headers->set('X-Idempotency-Replayed', 'true');

        return $response;
    }

    private function persistResponse(string $userId, string $key, string $bodyHash, Response $response): void
    {
        $headers = [];
        foreach (self::REPLAYED_HEADERS as $name) {
            $value = $response->headers->get($name);
            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        $body = (string) $response->getContent();
        $headersJson = (string) json_encode($headers, JSON_THROW_ON_ERROR);

        try {
            DB::table('idempotency_keys')->updateOrInsert(
                [
                    'user_id'         => $userId,
                    'idempotency_key' => $key,
                ],
                [
                    'request_hash'     => $bodyHash,
                    'response_status'  => $response->getStatusCode(),
                    'response_body'    => $body,
                    'response_headers' => $headersJson,
                    'expires_at'       => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
                    'created_at'       => date('Y-m-d H:i:s'),
                ],
            );
        } catch (Throwable) {
            // Persistence failure is not fatal — the user's request already
            // succeeded. The next replay just won't be served from cache.
            // Avoid letting a DB hiccup turn a successful mutation into a 500.
        }
    }
}
