<?php

declare(strict_types=1);

namespace App\Domain\MCP\Policy;

use App\Domain\MCP\Exceptions\IdempotencyKeyReusedException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class IdempotencyCache
{
    /**
     * Run $execute and cache its result keyed by (token, tool, idempotency_key).
     * On retry with the same key + args_hash, return the cached result without
     * re-executing. On retry with the same key but different args_hash, throw —
     * the client is reusing a key they shouldn't.
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

        $result = $execute();
        $store->put(
            $cacheKey,
            ['args_hash' => $argsHash, 'result' => $result],
            (int) config('mcp.idempotency.ttl_seconds', 86400),
        );

        return $result;
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
