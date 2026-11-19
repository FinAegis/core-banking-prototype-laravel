<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Constants;

/**
 * Addresses that can never be a user's wallet: system programs, sysvars,
 * token programs and mints on Solana; the zero/burn addresses, the ERC-4337
 * EntryPoints and the stablecoin contracts on EVM.
 *
 * They are refused at address registration (POST /api/v1/wallet/addresses)
 * and never pushed to the Helius watch list. Registering one would either
 * stream an enormous amount of unrelated traffic into the webhook queue or,
 * because nothing ever watches it, keep the hourly Helius drift alarm red.
 */
final class ReservedAddresses
{
    /** @var array<int, string> Solana base58 addresses (case-sensitive) */
    public const SOLANA = [
        '11111111111111111111111111111111',            // System program
        '11111111111111111111111111111112',
        'So11111111111111111111111111111111111111112', // Wrapped SOL mint
        'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA', // SPL Token program
        'TokenzQdBNbLqP5VEhdkAS6EPFLC1PHnBqCXEpPxuEb', // Token-2022 program
        'ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL', // Associated Token Account program
        'ComputeBudget111111111111111111111111111111', // Compute Budget program
        'MemoSq4gqABAXKb96qnH8TysNcWxMyWCqXgDLGmfcHr', // Memo program
        'BPFLoaderUpgradeab1e11111111111111111111111', // Upgradeable BPF loader
        'Vote111111111111111111111111111111111111111', // Vote program
        'Stake11111111111111111111111111111111111111', // Stake program
        'SysvarC1ock11111111111111111111111111111111', // Clock sysvar
        'SysvarRent111111111111111111111111111111111', // Rent sysvar
        'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', // USDC mint
        'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB', // USDT mint
    ];

    /** @var array<int, string> EVM addresses (lowercase) */
    private const EVM_FIXED = [
        '0x0000000000000000000000000000000000000000', // zero address (mint/burn counterparty)
        '0x000000000000000000000000000000000000dead', // burn address
        '0x5ff137d4b0fdcd49dca30c7cf57e578a026d2789', // ERC-4337 EntryPoint v0.6
        '0x0000000071727de22e5e9d8baf0edac6f37da032', // ERC-4337 EntryPoint v0.7
    ];

    public static function isReservedSolana(string $address): bool
    {
        return in_array($address, self::SOLANA, true);
    }

    public static function isReservedEvm(string $address): bool
    {
        $address = strtolower($address);

        if (in_array($address, self::EVM_FIXED, true)) {
            return true;
        }

        foreach ([EvmTokens::USDC, EvmTokens::USDT] as $contracts) {
            if (in_array($address, array_map('strtolower', $contracts), true)) {
                return true;
            }
        }

        return false;
    }
}
