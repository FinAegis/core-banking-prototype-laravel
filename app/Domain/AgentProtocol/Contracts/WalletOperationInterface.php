<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Contracts;

/**
 * Interface for wallet operations within the Agent Protocol.
 *
 * This interface defines the contract for services that handle
 * agent wallet operations including fund transfers, balance checks,
 * and fund holds for escrow.
 */
interface WalletOperationInterface
{
    /**
     * Get the current balance for a wallet.
     *
     * @param string $walletId The wallet identifier
     * @param string|null $currency Optional currency filter (returns all if null)
     * @return array{
     *     wallet_id: string,
     *     balances: array<string, float>,
     *     available: array<string, float>,
     *     held: array<string, float>,
     *     updated_at: string
     * } Wallet balance information
     */
    public function getBalance(string $walletId, ?string $currency = null): array;

    /**
     * Transfer funds between wallets.
     *
     * @param string $fromWalletId Source wallet identifier
     * @param string $toWalletId Destination wallet identifier
     * @param float $amount Amount to transfer
     * @param string $currency Currency code (e.g., 'USD', 'EUR')
     * @param array<string, mixed> $metadata Optional transfer metadata
     * @return array{
     *     success: bool,
     *     transaction_id: string,
     *     from_wallet: string,
     *     to_wallet: string,
     *     amount: float,
     *     currency: string,
     *     fee: float,
     *     timestamp: string
     * } Transfer result
     */
    public function transfer(
        string $fromWalletId,
        string $toWalletId,
        float $amount,
        string $currency,
        array $metadata = []
    ): array;

    /**
     * Hold funds in a wallet for escrow or pending transactions.
     *
     * @param string $walletId The wallet to hold funds from
     * @param float $amount Amount to hold
     * @param string $currency Currency code
     * @param string $reason Reason for the hold (e.g., 'escrow', 'pending_verification')
     * @param int|null $expiresInSeconds Optional expiration time in seconds
     * @return array{
     *     success: bool,
     *     hold_id: string,
     *     wallet_id: string,
     *     amount: float,
     *     currency: string,
     *     reason: string,
     *     expires_at: string|null
     * } Hold result
     */
    public function holdFunds(
        string $walletId,
        float $amount,
        string $currency,
        string $reason,
        ?int $expiresInSeconds = null
    ): array;

    /**
     * Release previously held funds.
     *
     * @param string $holdId The hold identifier to release
     * @param string|null $releaseToWalletId Optional wallet to release funds to (original if null)
     * @return array{
     *     success: bool,
     *     hold_id: string,
     *     released_amount: float,
     *     currency: string,
     *     released_to: string
     * } Release result
     */
    public function releaseFunds(string $holdId, ?string $releaseToWalletId = null): array;

    /**
     * Check if a wallet has sufficient balance for an operation.
     *
     * @param string $walletId The wallet to check
     * @param float $amount Required amount
     * @param string $currency Currency code
     * @param bool $includeHeld Whether to include held funds in available balance
     * @return bool True if sufficient balance exists
     */
    public function hasSufficientBalance(
        string $walletId,
        float $amount,
        string $currency,
        bool $includeHeld = false
    ): bool;
}
