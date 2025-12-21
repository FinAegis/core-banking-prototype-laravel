<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Aggregates\AssetTransactionAggregate;
use App\Domain\Account\Models\Account;
use App\Domain\Shared\Contracts\AccountOperationsInterface;
use Illuminate\Support\Str;

/**
 * Adapter implementing AccountOperationsInterface for domain decoupling.
 *
 * This adapter bridges the shared interface with the concrete Account domain
 * implementation, enabling other domains to depend on the abstraction.
 */
class AccountOperationsAdapter implements AccountOperationsInterface
{
    /**
     * In-memory storage for balance locks (would use Redis/DB in production).
     *
     * @var array<string, array{account_id: string, asset_code: string, amount: string}>
     */
    private array $locks = [];

    /**
     * {@inheritDoc}
     */
    public function getBalance(string $accountId, string $assetCode): string
    {
        $account = Account::where('uuid', $accountId)->first();

        if (! $account) {
            return '0';
        }

        return (string) $account->getBalance($assetCode);
    }

    /**
     * {@inheritDoc}
     */
    public function hasSufficientBalance(string $accountId, string $assetCode, string $amount): bool
    {
        $account = Account::where('uuid', $accountId)->first();

        if (! $account) {
            return false;
        }

        return $account->hasSufficientBalance($assetCode, (int) $amount);
    }

    /**
     * {@inheritDoc}
     */
    public function credit(
        string $accountId,
        string $assetCode,
        string $amount,
        string $reference,
        array $metadata = []
    ): string {
        $account = Account::where('uuid', $accountId)->firstOrFail();

        // Use the event-sourced aggregate for the transaction
        $transactionId = (string) Str::uuid();
        $aggregate = AssetTransactionAggregate::retrieve($accountId);
        $aggregate->credit($assetCode, (int) $amount);
        $aggregate->persist();

        // Also update the read model (AccountBalance)
        $balance = $account->getBalanceForAsset($assetCode);
        if ($balance) {
            $balance->credit((int) $amount);
        }

        return $transactionId;
    }

    /**
     * {@inheritDoc}
     */
    public function debit(
        string $accountId,
        string $assetCode,
        string $amount,
        string $reference,
        array $metadata = []
    ): string {
        $account = Account::where('uuid', $accountId)->firstOrFail();

        // Check sufficient balance first
        if (! $account->hasSufficientBalance($assetCode, (int) $amount)) {
            throw new \App\Domain\Account\Exceptions\NotEnoughFunds(
                "Insufficient {$assetCode} balance for debit"
            );
        }

        // Use the event-sourced aggregate for the transaction
        $transactionId = (string) Str::uuid();
        $aggregate = AssetTransactionAggregate::retrieve($accountId);
        $aggregate->debit($assetCode, (int) $amount);
        $aggregate->persist();

        // Also update the read model (AccountBalance)
        $balance = $account->getBalanceForAsset($assetCode);
        if ($balance) {
            $balance->debit((int) $amount);
        }

        return $transactionId;
    }

    /**
     * {@inheritDoc}
     */
    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        string $assetCode,
        string $amount,
        string $reference,
        array $metadata = []
    ): string {
        $transferId = (string) Str::uuid();

        // Debit source
        $this->debit($fromAccountId, $assetCode, $amount, $reference, $metadata);

        // Credit destination
        $this->credit($toAccountId, $assetCode, $amount, $reference, $metadata);

        return $transferId;
    }

    /**
     * {@inheritDoc}
     */
    public function lockBalance(
        string $accountId,
        string $assetCode,
        string $amount,
        string $reason
    ): string {
        $account = Account::where('uuid', $accountId)->firstOrFail();

        if (! $account->hasSufficientBalance($assetCode, (int) $amount)) {
            throw new \App\Domain\Account\Exceptions\NotEnoughFunds(
                "Insufficient {$assetCode} balance for lock"
            );
        }

        $lockId = 'lock_' . Str::uuid();

        // Store lock information
        $this->locks[$lockId] = [
            'account_id' => $accountId,
            'asset_code' => $assetCode,
            'amount'     => $amount,
            'reason'     => $reason,
            'created_at' => now()->toIso8601String(),
        ];

        // Debit the amount to "lock" it
        $this->debit($accountId, $assetCode, $amount, "Lock: {$reason}");

        return $lockId;
    }

    /**
     * {@inheritDoc}
     */
    public function unlockBalance(string $lockId): bool
    {
        if (! isset($this->locks[$lockId])) {
            return false;
        }

        $lock = $this->locks[$lockId];

        // Credit back the locked amount
        $this->credit(
            $lock['account_id'],
            $lock['asset_code'],
            $lock['amount'],
            "Unlock: {$lockId}"
        );

        unset($this->locks[$lockId]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccount(string $accountId): ?array
    {
        $account = Account::where('uuid', $accountId)->first();

        if (! $account) {
            return null;
        }

        return [
            'id'         => (string) $account->id,
            'uuid'       => $account->uuid,
            'user_id'    => (string) $account->user_id,
            'type'       => $account->type ?? 'standard',
            'status'     => $account->status ?? 'active',
            'created_at' => $account->created_at?->toIso8601String() ?? '',
        ];
    }
}
