<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use InvalidArgumentException;

/**
 * Builds the outer `execute(address target, uint256 value, bytes data)` calldata
 * the ERC-4337 SimpleAccount uses to dispatch the inner ERC-20 transfer.
 *
 * Selector: keccak256("execute(address,uint256,bytes)")[0..4] = 0xb61d27f6.
 * Used by the canonical eth-infinitism SimpleAccount implementation that the
 * relayer's factory deploys.
 *
 * ABI layout for `execute(address,uint256,bytes)` calldata (after selector):
 *   [head] address target          — 32 bytes, left-padded
 *   [head] uint256 value           — 32 bytes, left-padded (we always pass 0 — no native value with ERC-20 transfers)
 *   [head] bytes  data             — 32-byte offset to the dynamic tail (always 0x60 here)
 *   [tail] uint256 length          — 32 bytes, byte-length of `data`
 *   [tail] bytes  data...zero-pad  — multiple of 32 bytes
 */
final class UserOpCallDataBuilder
{
    /**
     * keccak256("execute(address,uint256,bytes)")[0..4].
     */
    public const EXECUTE_SELECTOR = '0xb61d27f6';

    /**
     * Default value field for execute() — no native ETH/MATIC sent. The ERC-20
     * transfer carries its own amount in `data`.
     */
    private const ZERO_VALUE_HEX = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Offset (in bytes) from the start of the head section to the dynamic
     * `bytes data` tail. With three head slots of 32 bytes each, the offset
     * is always 0x60 (96).
     */
    private const BYTES_HEAD_OFFSET_HEX = '0000000000000000000000000000000000000000000000000000000000000060';

    /**
     * Wrap an inner ERC-20 calldata as a SimpleAccount `execute()` call.
     *
     * @param  string $tokenContractAddress Token contract (the `target` of execute()).
     * @param  string $erc20CallData        Inner calldata from {@see Erc20Transfer::encodeTransferCallData()}.
     * @return string `0x`-prefixed outer calldata.
     */
    public static function buildExecute(string $tokenContractAddress, string $erc20CallData): string
    {
        $cleanTarget = strtolower(
            str_starts_with($tokenContractAddress, '0x') || str_starts_with($tokenContractAddress, '0X')
                ? substr($tokenContractAddress, 2)
                : $tokenContractAddress
        );
        if (strlen($cleanTarget) !== 40 || preg_match('/^[0-9a-f]{40}$/', $cleanTarget) !== 1) {
            throw new InvalidArgumentException("Invalid target contract address: {$tokenContractAddress}");
        }

        $cleanData = strtolower(
            str_starts_with($erc20CallData, '0x') || str_starts_with($erc20CallData, '0X')
                ? substr($erc20CallData, 2)
                : $erc20CallData
        );
        if ($cleanData !== '' && preg_match('/^[0-9a-f]+$/', $cleanData) !== 1) {
            throw new InvalidArgumentException('Inner calldata is not valid hex');
        }
        // Inner bytes must come in whole bytes (even hex length).
        if ((strlen($cleanData) & 1) !== 0) {
            throw new InvalidArgumentException('Inner calldata must have an even number of hex characters');
        }

        $byteLength = intdiv(strlen($cleanData), 2);

        // Pad the bytes payload up to a multiple of 32 bytes (right-pad with zeros).
        $paddedDataLen = (int) (ceil($byteLength / 32) * 32);
        $paddedData = str_pad($cleanData, $paddedDataLen * 2, '0', STR_PAD_RIGHT);

        $paddedTarget = str_pad($cleanTarget, 64, '0', STR_PAD_LEFT);
        $lengthHex = str_pad(dechex($byteLength), 64, '0', STR_PAD_LEFT);

        return self::EXECUTE_SELECTOR
            . $paddedTarget
            . self::ZERO_VALUE_HEX
            . self::BYTES_HEAD_OFFSET_HEX
            . $lengthHex
            . $paddedData;
    }
}
