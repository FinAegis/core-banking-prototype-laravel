<?php

declare(strict_types=1);

namespace App\Domain\MCP\Policy;

use App\Domain\MCP\Exceptions\IdempotencyKeyInFlightException;
use App\Domain\MCP\Exceptions\IdempotencyKeyReusedException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class IdempotencyCache
{
    /**
     * Default in-flight lock TTL when neither caller nor config specifies one.
     * Conservative because the wrong-direction error is duplicate payment,
     * not stuck retry: a too-short TTL on a slow rail (Lightning HTLC,
     * congested chain) lets a second caller acquire the lock while the first
     * call is still in flight = double-charge. Operators tune this via
     * `mcp.idempotency.lock_ttl_seconds` (default 300s = 5 minutes — covers
     * the slowest rails we currently care about).
     */
    private const DEFAULT_LOCK_TTL_SECONDS = 300;

    /**
     * Run $execute and cache its result keyed by (token, tool, idempotency_key).
     *
     * Three states a retry can land in:
     *   - cache hit, args_hash matches → return cached result (replay)
     *   - cache hit, args_hash differs → throw IdempotencyKeyReusedException (-32002)
     *   - cache miss → acquire an in-flight lock atomically (Cache::add =
     *     Redis SET NX), execute, write, release. A concurrent first-call
     *     that loses the race throws IdempotencyKeyInFlightException so the
     *     client retries instead of executing the payment a second time.
     *
     * The atomic lock fixes the TOCTOU race where two requests with the same
     * fresh key both saw a cache miss and both executed (= duplicate payment).
     *
     * @return mixed
     */
    public function remember(
        string $tokenId,
        string $toolName,
        string $idempotencyKey,
        string $argsHash,
        callable $execute,
    ): mixed {
        $store = $this->store();
        $cacheKey = $this->key($tokenId, $toolName, $idempotencyKey);
        $lockKey = $cacheKey . ':lock';

        $existing = $store->get($cacheKey);
        if (is_array($existing)) {
            $cachedHash = (string) ($existing['args_hash'] ?? '');
            if ($cachedHash !== $argsHash) {
                throw new IdempotencyKeyReusedException(
                    "Idempotency key {$idempotencyKey} reused with different arguments",
                );
            }

            return $existing['result'] ?? null;
        }

        // Atomic SET-NX. Whoever wins the lock executes; everyone else gets
        // told to retry, at which point the cached result will be present.
        $lockTtl = (int) config('mcp.idempotency.lock_ttl_seconds', self::DEFAULT_LOCK_TTL_SECONDS);
        if (! $store->add($lockKey, '1', $lockTtl)) {
            throw new IdempotencyKeyInFlightException(
                "Idempotency key {$idempotencyKey} is currently being processed; retry after a short delay",
            );
        }

        try {
            $result = $execute();
            $store->put(
                $cacheKey,
                ['args_hash' => $argsHash, 'result' => $result],
                (int) config('mcp.idempotency.ttl_seconds', 86400),
            );

            return $result;
        } finally {
            $store->forget($lockKey);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function peek(string $tokenId, string $toolName, string $idempotencyKey): ?array
    {
        $value = $this->store()->get($this->key($tokenId, $toolName, $idempotencyKey));

        return is_array($value) ? $value : null;
    }

    private function key(string $tokenId, string $toolName, string $idempotencyKey): string
    {
        return "mcp:idem:{$tokenId}:{$toolName}:{$idempotencyKey}";
    }

    private function store(): Repository
    {
        return Cache::store((string) config('mcp.idempotency.cache_store', 'redis'));
    }
}
