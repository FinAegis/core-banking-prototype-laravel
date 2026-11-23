<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use App\Domain\MCP\Policy\ConsentSpendingPolicy;

/**
 * Per-request authentication and authorization context for MCP JSON-RPC dispatch.
 *
 * Built by the OAuth perimeter middleware after a token has been verified, then
 * passed into the JsonRpcRouter so individual method handlers can make scope
 * decisions without re-parsing the bearer token.
 */
final class McpRequestContext
{
    /**
     * @param array<int, string> $scopes
     */
    public function __construct(
        public readonly string $tokenId,
        public readonly string $clientId,
        public readonly ?int $userId,
        public readonly array $scopes,
    ) {
    }

    /**
     * The key of the daily spending limit for this request: the user's
     * approval of the app, so every token of that approval shares one limit.
     * A token without a user (client credentials) has no approval to key on.
     */
    public function spendingPolicyKey(): string
    {
        return $this->userId === null
            ? "token:{$this->tokenId}"
            : ConsentSpendingPolicy::key($this->clientId, $this->userId);
    }

    /**
     * Check whether the request token authorises the given scope.
     *
     * - `null` scope => the resource is public (always allowed).
     * - `'*'` in token scopes => superuser (allows any scope).
     */
    public function hasScope(?string $scope): bool
    {
        if ($scope === null) {
            return true;
        }

        return in_array($scope, $this->scopes, true) || in_array('*', $this->scopes, true);
    }
}
