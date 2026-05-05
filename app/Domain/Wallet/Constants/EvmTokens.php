<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Constants;

/**
 * Canonical stablecoin contract addresses + chain metadata for the EVM
 * non-custodial send pipeline.
 *
 * Addresses are the canonical native-issued versions (Circle USDC native,
 * Tether USDT) on each network's mainnet — NOT bridged variants. All
 * addresses are stored lowercase (EVM addresses are case-insensitive but
 * lowercasing is the convention for keying).
 *
 * USDT is intentionally absent on Base mainnet — there is no canonical
 * Tether-issued USDT contract there as of the time of writing. Callers
 * must check {@see contractFor()} for null and reject the request via
 * {@see \App\Domain\Wallet\Exceptions\InvalidAssetException}.
 */
final class EvmTokens
{
    /**
     * Network key → USDC contract address (lowercase hex).
     */
    public const USDC = [
        'polygon'  => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359', // USDC native (Circle, post-2023 migration)
        'base'     => '0x833589fcd6edb6e08f4c7c32d4f71b54bda02913',
        'arbitrum' => '0xaf88d065e77c8cc2239327c5edb3a432268e5831', // USDC native
        'ethereum' => '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48',
    ];

    /**
     * Network key → USDT contract address (lowercase hex). No USDT on Base.
     */
    public const USDT = [
        'polygon'  => '0xc2132d05d31c914a87c6611c10748aeb04b58e8f',
        'arbitrum' => '0xfd086bc7cd5c481dcc9c85ebe478a1c0b69fcbb9',
        'ethereum' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
        // No canonical USDT on Base mainnet.
    ];

    /**
     * Asset symbol → token decimals. USDC and USDT are both 6-decimal
     * across all supported networks.
     */
    public const DECIMALS = [
        'USDC' => 6,
        'USDT' => 6,
    ];

    /**
     * Network key → EVM chain id. Used for v0.6 userOpHash construction
     * (final keccak input includes chainId).
     */
    public const NETWORK_CHAIN_IDS = [
        'polygon'  => 137,
        'base'     => 8453,
        'arbitrum' => 42161,
        'ethereum' => 1,
    ];

    /**
     * ERC-4337 EntryPoint v0.6 — same address on every chain Pimlico supports.
     * v0.7 lives at a different address; this pipeline targets v0.6 because
     * Privy's smart-wallet signer produces v0.6-shaped signatures.
     */
    public const ENTRY_POINT_V06 = '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789';

    /**
     * Resolve the token contract for a (symbol, network) pair.
     *
     * @return string|null Lowercase 0x-prefixed contract address, or null if
     *                    the asset isn't deployed on that network (e.g. USDT
     *                    on Base).
     */
    public static function contractFor(string $assetSymbol, string $networkKey): ?string
    {
        $symbol = strtoupper($assetSymbol);
        $network = strtolower($networkKey);

        return match ($symbol) {
            'USDC'  => self::USDC[$network] ?? null,
            'USDT'  => self::USDT[$network] ?? null,
            default => null,
        };
    }
}
