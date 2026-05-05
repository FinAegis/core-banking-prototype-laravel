<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Helpers\Crypto;

use BN\BN;
use Elliptic\EC;
use kornrunner\Keccak;
use RuntimeException;

/**
 * Deterministic secp256k1 keypair derivation for EVM smart-account ownership.
 *
 * Replaces the legacy phantom-address derivation in
 * {@see \App\Http\Controllers\Api\Relayer\SmartAccountController::deriveOwnerAddress()}
 * — which produced a 20-byte hash of `(user_id . app_key)` with NO matching
 * private key. Sends from such accounts were mathematically impossible.
 *
 * The derivation is custodial: the server is the sole holder of every user's
 * private key material. The seed scheme mirrors the Solana ed25519 helper but
 * uses the BIP-44 EVM path component (m/44'/60'/0'/0):
 *
 *   seed = sha256("evm:{userId}:{appKey}:m/44'/60'/0'/0", binary: true)
 *   privateKey = seed (32 bytes), validated to be in [1, n-1) on secp256k1
 *
 * Sensitive data handling: the private key returned by {@see deriveForUser()}
 * is hex-encoded. Callers MUST consume it inline (sign, then drop) and avoid
 * persisting or logging it. Where only the public address is needed,
 * {@see deriveAddressOnly()} avoids materialising the private key in PHP space.
 */
final class EvmKeyHelper
{
    /**
     * EVM derivation path component baked into the seed.
     */
    private const SEED_PATH = "m/44'/60'/0'/0";

    /**
     * Derive a real secp256k1 keypair for the given user.
     *
     * @return array{address: string, publicKey: string, privateKey: string}
     *               address    EIP-55 checksummed `0x`-prefixed 20-byte address.
     *               publicKey  Hex string, `0x`-prefixed, 64 bytes (X || Y, no SEC1 prefix byte).
     *               privateKey Hex string, `0x`-prefixed, 32 bytes. Treat as a secret.
     */
    public static function deriveForUser(int $userId, string $appKey): array
    {
        $privScalarHex = self::deriveValidPrivateScalarHex($userId, $appKey);

        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privScalarHex, 'hex');

        // Uncompressed encoding without the 0x04 SEC1 prefix: 64 bytes (X || Y).
        $publicKeyHex = $key->getPublic(false, 'hex');
        if (str_starts_with($publicKeyHex, '04')) {
            $publicKeyHex = substr($publicKeyHex, 2);
        }
        $publicKeyHex = str_pad($publicKeyHex, 128, '0', STR_PAD_LEFT);

        $address = self::addressFromPublicKeyHex($publicKeyHex);

        return [
            'address'    => self::toChecksumAddress($address),
            'publicKey'  => '0x' . $publicKeyHex,
            'privateKey' => '0x' . $privScalarHex,
        ];
    }

    /**
     * Convenience wrapper that returns only the EIP-55 checksummed owner address.
     *
     * Use this when you do not need to sign — it avoids returning the private
     * key string to the caller, reducing the surface for accidental leaks.
     */
    public static function deriveAddressOnly(int $userId, string $appKey): string
    {
        $privScalarHex = self::deriveValidPrivateScalarHex($userId, $appKey);

        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privScalarHex, 'hex');

        $publicKeyHex = $key->getPublic(false, 'hex');
        if (str_starts_with($publicKeyHex, '04')) {
            $publicKeyHex = substr($publicKeyHex, 2);
        }
        $publicKeyHex = str_pad($publicKeyHex, 128, '0', STR_PAD_LEFT);

        return self::toChecksumAddress(self::addressFromPublicKeyHex($publicKeyHex));
    }

    /**
     * Apply EIP-55 mixed-case checksum to a 20-byte hex address.
     *
     * Input may be 0x-prefixed or not; output is always 0x-prefixed lower/upper
     * mixed case per the spec.
     */
    public static function toChecksumAddress(string $address): string
    {
        $clean = strtolower(str_starts_with($address, '0x') ? substr($address, 2) : $address);
        if (strlen($clean) !== 40 || preg_match('/^[0-9a-f]{40}$/', $clean) !== 1) {
            throw new RuntimeException('Invalid EVM address: ' . $address);
        }

        $hash = Keccak::hash($clean, 256);

        $checksummed = '';
        for ($i = 0; $i < 40; $i++) {
            $hashChar = $hash[$i];
            $addrChar = $clean[$i];
            // Per EIP-55: if the corresponding nibble of the hash is >= 8, uppercase the address char (only affects a-f).
            $checksummed .= (hexdec($hashChar) >= 8) ? strtoupper($addrChar) : $addrChar;
        }

        return '0x' . $checksummed;
    }

    /**
     * Derive a private scalar in [1, n) on secp256k1.
     *
     * Practically the first hash always lands inside the curve order — the
     * probability of needing a counter bump is ~2^-128 — but we still iterate
     * defensively so the result is always a valid secp256k1 scalar.
     */
    private static function deriveValidPrivateScalarHex(int $userId, string $appKey): string
    {
        $base = "evm:{$userId}:{$appKey}:" . self::SEED_PATH;

        $ec = new EC('secp256k1');
        /** @var BN $n */
        $n = $ec->n;

        for ($counter = 0; $counter < 256; $counter++) {
            $material = $counter === 0
                ? $base
                : $base . ':' . chr($counter);
            $seedBin = hash('sha256', $material, binary: true);
            $hex = bin2hex($seedBin);
            $candidate = new BN($hex, 16);

            // Need 1 <= k < n.
            if ($candidate->cmpn(0) > 0 && $candidate->cmp($n) < 0) {
                return str_pad($hex, 64, '0', STR_PAD_LEFT);
            }
        }

        // Astronomically unreachable; throw rather than silently return weak material.
        throw new RuntimeException('Failed to derive a valid secp256k1 private scalar after 256 attempts');
    }

    /**
     * Compute the lowercase, non-checksummed EVM address from a 64-byte
     * public key hex string (X || Y, no 0x or 0x04 prefix).
     */
    private static function addressFromPublicKeyHex(string $publicKeyHex): string
    {
        $pubKeyBin = hex2bin($publicKeyHex);
        if ($pubKeyBin === false || strlen($pubKeyBin) !== 64) {
            throw new RuntimeException('Invalid public key hex; expected 64 bytes');
        }

        $hash = Keccak::hash($pubKeyBin, 256);

        return '0x' . substr($hash, 24);
    }
}
