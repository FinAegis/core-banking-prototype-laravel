<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

/**
 * Re-derives a user's deterministic ed25519 keypair on demand to sign Solana
 * transaction messages. Uses the SAME seed scheme as
 * {@see \App\Domain\Wallet\Helpers\SolanaAddressHelper::deriveAddress()} so the
 * signing key always matches the public address registered on Helius.
 *
 * Secret key bytes are zeroed via sodium_memzero() immediately after each
 * signing operation. This is best-effort on PHP strings (copy-on-write may
 * leave residual copies) but provides defence-in-depth and matches the
 * pattern already used in SolanaAddressHelper.
 */
final class SolanaSigner
{
    /**
     * Sign arbitrary message bytes with the user's derived ed25519 secret key.
     *
     * Returns a 64-byte detached signature. Callers are responsible for
     * splicing the signature into the Solana transaction wire format.
     */
    public function signMessage(int $userId, string $appKey, string $messageBytes): string
    {
        // Inline the keypair derivation so PHPStan keeps the non-empty-string
        // flow and so raw seed bytes never live in a named variable.
        $keypair = sodium_crypto_sign_seed_keypair(
            hash('sha256', "solana:{$userId}:{$appKey}" . ":m/44'/501'/0'/0'", binary: true)
        );
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        try {
            return sodium_crypto_sign_detached($messageBytes, $secretKey);
        } finally {
            sodium_memzero($secretKey);
            sodium_memzero($keypair);
        }
    }

    /**
     * Return the raw 32-byte ed25519 public key for a user. Callers Base58-encode
     * if they need the canonical Solana address representation.
     */
    public function getPublicKey(int $userId, string $appKey): string
    {
        $keypair = sodium_crypto_sign_seed_keypair(
            hash('sha256', "solana:{$userId}:{$appKey}" . ":m/44'/501'/0'/0'", binary: true)
        );

        try {
            return sodium_crypto_sign_publickey($keypair);
        } finally {
            sodium_memzero($keypair);
        }
    }
}
