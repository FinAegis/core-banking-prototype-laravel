<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Interface for account operations used by external domains.
 *
 * This interface enables domain decoupling by allowing domains like
 * Exchange, Basket, Lending, etc. to depend on an abstraction rather
 * than the concrete Account domain implementation.
 *
 * @see \App\Domain\Account\Services\AccountService for implementation
 */
interface AccountOperationsInterface
{
    /**
     * Get the balance for a specific asset in an account.
     *
     * @param string $accountId Account UUID
     * @param string $assetCode Currency/asset code (e.g., 'USD', 'GCU', 'BTC')
     * @return string Balance as string for precision (use bcmath for calculations)
     */
    public function getBalance(string $accountId, string $assetCode): string;

    /**
     * Check if account has sufficient balance for an operation.
     *
     * @param string $accountId Account UUID
     * @param string $assetCode Currency/asset code
     * @param string $amount Amount to check (as string for precision)
     * @return bool True if balance >= amount
     */
    public function hasSufficientBalance(string $accountId, string $assetCode, string $amount): bool;

    /**
     * Credit (add) funds to an account.
     *
     * @param string $accountId Account UUID
     * @param string $assetCode Currency/asset code
     * @param string $amount Amount to credit (as string for precision)
     * @param string $reference Transaction reference/description
     * @param array<string, mixed> $metadata Additional transaction metadata
     * @return string Transaction ID
     */
    public function credit(
        string $accountId,
        string $assetCode,
        string $amount,
        string $reference,
        array $metadata = []
    ): string;

    /**
     * Debit (subtract) funds from an account.
     *
     * @param string $accountId Account UUID
     * @param string $assetCode Currency/asset code
     * @param string $amount Amount to debit (as string for precision)
     * @param string $reference Transaction reference/description
     * @param array<string, mixed> $metadata Additional transaction metadata
     * @return string Transaction ID
     *
     * @throws \App\Domain\Account\Exceptions\InsufficientBalanceException
     */
    public function debit(
        string $accountId,
        string $assetCode,
        string $amount,
        string $reference,
        array $metadata = []
    ): string;

    /**
     * Transfer funds between two accounts.
     *
     * @param string $fromAccountId Source account UUID
     * @param string $toAccountId Destination account UUID
     * @param string $assetCode Currency/asset code
     * @param string $amount Amount to transfer (as string for precision)
     * @param string $reference Transaction reference/description
     * @param array<string, mixed> $metadata Additional transaction metadata
     * @return string Transfer ID
     *
     * @throws \App\Domain\Account\Exceptions\InsufficientBalanceException
     */
    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        string $assetCode,
        string $amount,
        string $reference,
        array $metadata = []
    ): string;

    /**
     * Lock (reserve) funds in an account for pending operations.
     *
     * @param string $accountId Account UUID
     * @param string $assetCode Currency/asset code
     * @param string $amount Amount to lock (as string for precision)
     * @param string $reason Reason for the lock
     * @return string Lock ID (use to unlock later)
     *
     * @throws \App\Domain\Account\Exceptions\InsufficientBalanceException
     */
    public function lockBalance(
        string $accountId,
        string $assetCode,
        string $amount,
        string $reason
    ): string;

    /**
     * Unlock previously locked funds.
     *
     * @param string $lockId The lock ID returned from lockBalance()
     * @return bool True if unlocked successfully
     */
    public function unlockBalance(string $lockId): bool;

    /**
     * Get account details by UUID.
     *
     * @param string $accountId Account UUID
     * @return array{
     *     id: string,
     *     uuid: string,
     *     user_id: string,
     *     type: string,
     *     status: string,
     *     created_at: string
     * }|null Account details or null if not found
     */
    public function getAccount(string $accountId): ?array;
}
