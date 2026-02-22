<?php

declare(strict_types=1);

namespace App\Domain\X402\Enums;

/**
 * CAIP-2 network identifiers for x402 payment processing.
 *
 * Each case maps to a blockchain network identified by its CAIP-2 string
 * (e.g. "eip155:8453" for Base mainnet). Networks carry metadata such as
 * chain ID, USDC contract address, and block explorer URL.
 */
enum X402Network: string
{
    case BASE_MAINNET = 'eip155:8453';
    case BASE_SEPOLIA = 'eip155:84532';
    case ETHEREUM_MAINNET = 'eip155:1';
    case SEPOLIA = 'eip155:11155111';
    case AVALANCHE = 'eip155:43114';
    case AVALANCHE_FUJI = 'eip155:43113';

    /**
     * Get the numeric chain ID for this network.
     */
    public function chainId(): int
    {
        return match ($this) {
            self::BASE_MAINNET     => 8453,
            self::BASE_SEPOLIA     => 84532,
            self::ETHEREUM_MAINNET => 1,
            self::SEPOLIA          => 11155111,
            self::AVALANCHE        => 43114,
            self::AVALANCHE_FUJI   => 43113,
        };
    }

    /**
     * Determine whether this network is a testnet.
     */
    public function isTestnet(): bool
    {
        return match ($this) {
            self::BASE_SEPOLIA, self::SEPOLIA, self::AVALANCHE_FUJI => true,
            self::BASE_MAINNET, self::ETHEREUM_MAINNET, self::AVALANCHE => false,
        };
    }

    /**
     * Get the USDC contract address for this network.
     *
     * Reads from config('x402.assets.<caip2_id>.USDC').
     */
    public function usdcAddress(): string
    {
        return (string) config("x402.assets.{$this->value}.USDC", '');
    }

    /**
     * Get the USDC token decimals (always 6 for USDC).
     */
    public function usdcDecimals(): int
    {
        return 6;
    }

    /**
     * Get a human-readable label for this network.
     */
    public function label(): string
    {
        return match ($this) {
            self::BASE_MAINNET     => 'Base Mainnet',
            self::BASE_SEPOLIA     => 'Base Sepolia',
            self::ETHEREUM_MAINNET => 'Ethereum Mainnet',
            self::SEPOLIA          => 'Sepolia',
            self::AVALANCHE        => 'Avalanche C-Chain',
            self::AVALANCHE_FUJI   => 'Avalanche Fuji',
        };
    }

    /**
     * Get the block explorer base URL for this network.
     */
    public function explorerUrl(): string
    {
        return match ($this) {
            self::BASE_MAINNET     => 'https://basescan.org',
            self::BASE_SEPOLIA     => 'https://sepolia.basescan.org',
            self::ETHEREUM_MAINNET => 'https://etherscan.io',
            self::SEPOLIA          => 'https://sepolia.etherscan.io',
            self::AVALANCHE        => 'https://snowtrace.io',
            self::AVALANCHE_FUJI   => 'https://testnet.snowtrace.io',
        };
    }
}
