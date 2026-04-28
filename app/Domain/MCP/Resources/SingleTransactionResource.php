<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\MCP\Resources\Contracts\McpResourceInterface;
use App\Models\User;

/**
 * `transaction://{id}` — single TransactionProjection lookup. Refuses to
 * return rows that belong to other users (returns "not found" rather than
 * leaking existence).
 */
final class SingleTransactionResource implements McpResourceInterface
{
    public function uriTemplate(): string
    {
        return 'transaction://{id}';
    }

    public function name(): string
    {
        return 'Single transaction';
    }

    public function description(): string
    {
        return 'Look up a single transaction by uuid. Restricted to the caller\'s own accounts.';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function scope(): string
    {
        return 'transactions:read';
    }

    /**
     * @param array<string, string> $params
     */
    public function read(array $params, ?int $userId): string
    {
        $id = (string) ($params['id'] ?? '');
        if ($id === '' || $userId === null) {
            return $this->json(['error' => 'id required']);
        }

        $tx = TransactionProjection::where('uuid', $id)->first();
        if (! $tx instanceof TransactionProjection) {
            return $this->json(['error' => 'not found']);
        }

        $user = User::find($userId);
        if (! $user instanceof User) {
            return $this->json(['error' => 'not found']);
        }

        $ownsAccount = Account::where('user_uuid', $user->uuid)
            ->where('uuid', $tx->account_uuid)
            ->exists();

        if (! $ownsAccount) {
            return $this->json(['error' => 'not found']);
        }

        return $this->json([
            'id'           => $tx->uuid,
            'account_id'   => $tx->account_uuid,
            'asset_code'   => $tx->asset_code,
            'amount_minor' => $tx->amount,
            'type'         => $tx->type,
            'status'       => $tx->status,
            'description'  => $tx->description,
            'reference'    => $tx->reference,
            'created_at'   => $tx->created_at?->toIso8601String(),
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
