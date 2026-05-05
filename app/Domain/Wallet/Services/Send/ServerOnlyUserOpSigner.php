<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\ValueObjects\UserOperation;
use App\Domain\Wallet\Helpers\Crypto\EvmKeyHelper;
use Elliptic\EC;
use kornrunner\Keccak;
use RuntimeException;

/**
 * Server-only ERC-4337 v0.6 UserOperation signer.
 *
 * Bypasses the MPC {@see \App\Domain\Relayer\Services\UserOperationSigningService}
 * — this is the custodial path where the server holds the user's full secp256k1
 * private key and signs end-to-end. Use this only for the wallet send pipeline
 * where keys are derived deterministically from the user identity.
 *
 * UserOpHash construction follows EntryPoint v0.6's `getUserOpHash`:
 *
 *   packed = abi.encode(
 *       sender,
 *       nonce,
 *       keccak256(initCode),
 *       keccak256(callData),
 *       callGasLimit,
 *       verificationGasLimit,
 *       preVerificationGas,
 *       maxFeePerGas,
 *       maxPriorityFeePerGas,
 *       keccak256(paymasterAndData)
 *   )
 *   userOpHash = keccak256(abi.encode(keccak256(packed), entryPoint, chainId))
 *
 * Signature format: `r (32) || s (32) || v (1)` where `v` is `27 + recoveryParam`
 * (the standard Ethereum convention). Total length is 65 bytes / 132 hex chars
 * (134 with the 0x prefix).
 */
final class ServerOnlyUserOpSigner
{
    /**
     * Sign a UserOperation for the given network with the given user's derived key.
     *
     * @return string `0x`-prefixed 65-byte signature (`r || s || v`).
     */
    public function signUserOp(UserOperation $userOp, SupportedNetwork $network, int $userId): string
    {
        $appKey = (string) config('app.key');
        $userOpHashHex = self::computeUserOpHash($userOp, $network);

        $keys = EvmKeyHelper::deriveForUser($userId, $appKey);
        $privKeyHex = $keys['privateKey'];
        if (str_starts_with($privKeyHex, '0x')) {
            $privKeyHex = substr($privKeyHex, 2);
        }

        try {
            $ec = new EC('secp256k1');
            $key = $ec->keyFromPrivate($privKeyHex, 'hex');
            // Canonical signatures (low-s) match Ethereum's expectation.
            $signature = $key->sign($userOpHashHex, ['canonical' => true]);

            $r = str_pad($signature->r->toString(16, 2), 64, '0', STR_PAD_LEFT);
            $s = str_pad($signature->s->toString(16, 2), 64, '0', STR_PAD_LEFT);

            $recoveryParam = $signature->recoveryParam;
            if ($recoveryParam === null || ($recoveryParam !== 0 && $recoveryParam !== 1)) {
                throw new RuntimeException('Invalid recovery parameter from secp256k1 signing');
            }

            $v = 27 + $recoveryParam;
            $vHex = str_pad(dechex($v), 2, '0', STR_PAD_LEFT);

            return '0x' . $r . $s . $vHex;
        } finally {
            // Drop the local hex copy. PHP cannot guarantee zeroing of strings,
            // but this minimises the residual lifetime in this scope.
            $privKeyHex = str_repeat("\x00", strlen($privKeyHex));
            unset($privKeyHex);
        }
    }

    /**
     * Compute the EntryPoint v0.6 UserOpHash for an already-finalised UserOp
     * (gas + paymasterAndData filled in).
     *
     * Public to allow callers (e.g. the dispatcher) to verify that the hash
     * passed to the signer matches what they're submitting.
     */
    public static function computeUserOpHash(UserOperation $userOp, SupportedNetwork $network): string
    {
        $packed = self::abiEncodeAddress($userOp->sender)
            . self::abiEncodeUintFromInt($userOp->nonce)
            . self::keccakOfHexBytes($userOp->initCode)
            . self::keccakOfHexBytes($userOp->callData)
            . self::abiEncodeUintFromInt($userOp->callGasLimit)
            . self::abiEncodeUintFromInt($userOp->verificationGasLimit)
            . self::abiEncodeUintFromInt($userOp->preVerificationGas)
            . self::abiEncodeUintFromInt($userOp->maxFeePerGas)
            . self::abiEncodeUintFromInt($userOp->maxPriorityFeePerGas)
            . self::keccakOfHexBytes($userOp->paymasterAndData);

        $packedBin = self::hexToBin($packed);
        $packedHashHex = Keccak::hash($packedBin, 256);

        $outer = self::abiEncodeBytes32($packedHashHex)
            . self::abiEncodeAddress($network->getEntryPointAddress())
            . self::abiEncodeUintFromInt($network->getChainId());

        return Keccak::hash(self::hexToBin($outer), 256);
    }

    /**
     * keccak256 of a 0x-prefixed (or bare) hex bytes payload, ABI-encoded as bytes32.
     */
    private static function keccakOfHexBytes(string $hex): string
    {
        $clean = str_starts_with($hex, '0x') || str_starts_with($hex, '0X') ? substr($hex, 2) : $hex;
        if ($clean === '') {
            // keccak256("") = c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470
            return 'c5d2460186f7233c927e7db2dcc703c0e500b653ca82273b7bfad8045d85a470';
        }
        if (preg_match('/^[0-9a-fA-F]*$/', $clean) !== 1) {
            throw new RuntimeException('Invalid hex in UserOp field');
        }
        if ((strlen($clean) & 1) !== 0) {
            $clean = '0' . $clean;
        }

        return Keccak::hash(self::hexToBin($clean), 256);
    }

    private static function abiEncodeAddress(string $address): string
    {
        $clean = strtolower(str_starts_with($address, '0x') || str_starts_with($address, '0X') ? substr($address, 2) : $address);
        if (strlen($clean) > 40 || preg_match('/^[0-9a-f]*$/', $clean) !== 1) {
            throw new RuntimeException("Invalid address: {$address}");
        }

        return str_pad($clean, 64, '0', STR_PAD_LEFT);
    }

    private static function abiEncodeUintFromInt(int $value): string
    {
        if ($value < 0) {
            throw new RuntimeException("uint256 cannot be negative: {$value}");
        }

        return str_pad(dechex($value), 64, '0', STR_PAD_LEFT);
    }

    private static function abiEncodeBytes32(string $hex): string
    {
        $clean = str_starts_with($hex, '0x') || str_starts_with($hex, '0X') ? substr($hex, 2) : $hex;
        if (strlen($clean) > 64 || preg_match('/^[0-9a-fA-F]*$/', $clean) !== 1) {
            throw new RuntimeException("Invalid bytes32: {$hex}");
        }

        return str_pad($clean, 64, '0', STR_PAD_RIGHT);
    }

    private static function hexToBin(string $hex): string
    {
        $bin = hex2bin($hex);
        if ($bin === false) {
            throw new RuntimeException('Failed to decode hex bytes');
        }

        return $bin;
    }
}
