<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Contracts;

use App\Domain\Relayer\Enums\SupportedNetwork;

/**
 * Interface for Wallet Balance Providers.
 *
 * Provides balance checking capabilities for ERC-20 tokens across supported networks.
 * Implementations may use different RPC providers (Alchemy, Infura, custom nodes).
 */
interface WalletBalanceProviderInterface
{
    /**
     * Get the balance of a token for a wallet address.
     *
     * @param  string  $walletAddress  The wallet address (0x prefixed)
     * @param  string  $token  Token symbol (e.g., 'USDC', 'USDT')
     * @param  SupportedNetwork  $network  The blockchain network
     * @return string Balance in token's decimal format (e.g., "100.000000" for USDC)
     */
    public function getBalance(
        string $walletAddress,
        string $token,
        SupportedNetwork $network
    ): string;

    /**
     * Check if a wallet has sufficient balance for a specific amount.
     *
     * @param  string  $walletAddress  The wallet address (0x prefixed)
     * @param  string  $token  Token symbol (e.g., 'USDC', 'USDT')
     * @param  float  $amount  Required amount in token decimals
     * @param  SupportedNetwork  $network  The blockchain network
     * @return bool True if balance >= amount
     */
    public function hasBalance(
        string $walletAddress,
        string $token,
        float $amount,
        SupportedNetwork $network
    ): bool;

    /**
     * Get the token contract address for a specific network.
     *
     * @param  string  $token  Token symbol (e.g., 'USDC', 'USDT')
     * @param  SupportedNetwork  $network  The blockchain network
     * @return string|null Token contract address or null if not supported
     */
    public function getTokenAddress(string $token, SupportedNetwork $network): ?string;

    /**
     * Get the decimals for a token.
     *
     * @param  string  $token  Token symbol
     * @return int Number of decimals (typically 6 for USDC/USDT, 18 for most tokens)
     */
    public function getTokenDecimals(string $token): int;

    /**
     * Check if a token is supported on a network.
     *
     * @param  string  $token  Token symbol
     * @param  SupportedNetwork  $network  The blockchain network
     * @return bool True if token is supported on the network
     */
    public function isTokenSupported(string $token, SupportedNetwork $network): bool;

    /**
     * Invalidate cached balance for a wallet.
     *
     * @param  string  $walletAddress  The wallet address
     * @param  string  $token  Token symbol
     * @param  SupportedNetwork  $network  The blockchain network
     */
    public function invalidateCache(
        string $walletAddress,
        string $token,
        SupportedNetwork $network
    ): void;

    /**
     * Get the provider name (e.g., 'demo', 'alchemy', 'infura').
     */
    public function getProviderName(): string;
}
