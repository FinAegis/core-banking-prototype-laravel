<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use InvalidArgumentException;

/**
 * Encodes ERC-20 `transfer(address,uint256)` calldata.
 *
 * The function selector `0xa9059cbb` is the first 4 bytes of
 * keccak256("transfer(address,uint256)") and is hardcoded here — it never
 * changes for ERC-20.
 *
 * This class is intentionally tiny and stateless; it produces deterministic
 * hex calldata that callers wrap into the smart account's `execute(...)`
 * outer call (see {@see UserOpCallDataBuilder}).
 */
final class Erc20Transfer
{
    /**
     * keccak256("transfer(address,uint256)")[0..4].
     */
    public const TRANSFER_SELECTOR = '0xa9059cbb';

    /**
     * Build ERC-20 transfer calldata.
     *
     * @param  string $to        20-byte EVM address (with or without 0x prefix). Lower-cased in calldata.
     * @param  string $amountWei Decimal string in token's smallest unit (wei). Must be non-negative.
     * @return string `0x`-prefixed calldata: selector || pad(to,32) || pad(amount,32) — exactly 138 chars.
     *
     * @throws InvalidArgumentException If `$to` is not a 20-byte hex address or `$amountWei` is invalid.
     */
    public static function encodeTransferCallData(string $to, string $amountWei): string
    {
        $cleanTo = strtolower(str_starts_with($to, '0x') || str_starts_with($to, '0X') ? substr($to, 2) : $to);
        if (strlen($cleanTo) !== 40 || preg_match('/^[0-9a-f]{40}$/', $cleanTo) !== 1) {
            throw new InvalidArgumentException("Invalid recipient address for ERC-20 transfer: {$to}");
        }

        if ($amountWei === '' || preg_match('/^\d+$/', $amountWei) !== 1) {
            throw new InvalidArgumentException("Invalid uint256 amount (must be non-negative integer string): {$amountWei}");
        }

        $paddedTo = str_pad($cleanTo, 64, '0', STR_PAD_LEFT);
        $paddedAmount = str_pad(self::bcDecToHex($amountWei), 64, '0', STR_PAD_LEFT);

        if (strlen($paddedAmount) > 64) {
            throw new InvalidArgumentException("Amount exceeds uint256 max: {$amountWei}");
        }

        return self::TRANSFER_SELECTOR . $paddedTo . $paddedAmount;
    }

    /**
     * Decimal-string -> lowercase hex (no 0x prefix) using bcmath.
     *
     * Returns '0' for zero.
     */
    private static function bcDecToHex(string $dec): string
    {
        if ($dec === '0') {
            return '0';
        }

        /** @var numeric-string $remaining */
        $remaining = $dec;
        $hex = '';
        while (bccomp($remaining, '0', 0) > 0) {
            $mod = bcmod($remaining, '16', 0);
            $hex = dechex((int) $mod) . $hex;
            /** @var numeric-string $remaining */
            $remaining = bcdiv($remaining, '16', 0);
        }

        return $hex;
    }
}
