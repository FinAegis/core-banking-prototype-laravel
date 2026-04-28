<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\Account\Models\Account;
use App\Domain\MCP\Resources\Contracts\McpResourceInterface;
use App\Models\User;

/**
 * `account://balance/{currency}` — live balance for the requested asset code,
 * in minor units. Account model exposes `getBalance(asset_code)` which already
 * returns minor units, so we forward directly.
 */
final class AccountBalanceResource implements McpResourceInterface
{
    public function uriTemplate(): string
    {
        return 'account://balance/{currency}';
    }

    public function name(): string
    {
        return 'Account balance by currency';
    }

    public function description(): string
    {
        return 'Live balance in the requested asset code, in minor units (cents).';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function scope(): string
    {
        return 'accounts:read';
    }

    /**
     * @param array<string, string> $params
     */
    public function read(array $params, ?int $userId): string
    {
        $currency = strtoupper((string) ($params['currency'] ?? 'USD'));

        if ($userId === null) {
            return $this->json(['error' => 'no user context']);
        }

        $user = User::find($userId);
        if (! $user instanceof User) {
            return $this->json(['error' => 'user not found']);
        }

        $account = Account::where('user_uuid', $user->uuid)
            ->orderBy('created_at')
            ->first();

        $balanceMinor = $account instanceof Account ? $account->getBalance($currency) : 0;

        return $this->json([
            'currency'      => $currency,
            'balance_minor' => $balanceMinor,
            'account_id'    => $account?->uuid,
            'as_of'         => now()->toIso8601String(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
