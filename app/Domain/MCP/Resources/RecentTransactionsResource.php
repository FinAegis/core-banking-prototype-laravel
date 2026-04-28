<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\MCP\Resources\Contracts\McpResourceInterface;
use App\Models\User;

/**
 * `transactions://recent` — last N TransactionProjection rows for the
 * authenticated user, joined via Account.user_uuid. Default 25, max 100.
 *
 * Reads the read-side projection (TransactionProjection), not the event
 * store, so this is cheap and consistent with what the user sees in their
 * statement view.
 */
final class RecentTransactionsResource implements McpResourceInterface
{
    public function uriTemplate(): string
    {
        return 'transactions://recent';
    }

    public function name(): string
    {
        return 'Recent transactions';
    }

    public function description(): string
    {
        return 'Last N transactions for the authenticated user across all accounts. Default 25, max 100.';
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
        if ($userId === null) {
            return $this->json(['error' => 'no user context']);
        }

        $limit = max(1, min(100, (int) ($params['limit'] ?? 25)));

        $user = User::find($userId);
        if (! $user instanceof User) {
            return $this->json(['transactions' => []]);
        }

        $accountUuids = Account::where('user_uuid', $user->uuid)->pluck('uuid');
        if ($accountUuids->isEmpty()) {
            return $this->json(['transactions' => []]);
        }

        $rows = TransactionProjection::whereIn('account_uuid', $accountUuids)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['uuid', 'account_uuid', 'asset_code', 'amount', 'type', 'status', 'description', 'created_at']);

        return $this->json([
            'transactions' => $rows->map(fn (TransactionProjection $t) => [
                'id'           => $t->uuid,
                'account_id'   => $t->account_uuid,
                'asset_code'   => $t->asset_code,
                'amount_minor' => $t->amount,
                'type'         => $t->type,
                'status'       => $t->status,
                'description'  => $t->description,
                'created_at'   => $t->created_at?->toIso8601String(),
            ])->all(),
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
