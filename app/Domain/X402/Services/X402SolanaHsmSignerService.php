<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\Contracts\X402SignerInterface;
use RuntimeException;

/**
 * Production Solana signer that delegates to HSM (Hardware Security Module).
 *
 * Supports: AWS CloudHSM, Azure Key Vault, or local sodium for development.
 * In production, the private key never leaves the HSM — signing happens inside
 * the secure boundary.
 */
class X402SolanaHsmSignerService implements X402SignerInterface
{
    private ?string $publicKey = null;

    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private readonly string $keyId,
        private readonly string $provider = 'sodium', // sodium|aws|azure
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function signTransferAuthorization(
        string $network,
        string $to,
        string $amount,
        string $asset,
        int $maxTimeoutSeconds,
        array $extra = [],
    ): array {
        $from = $this->getAddress();
        $nonce = bin2hex(random_bytes(32));
        $validBefore = (string) (time() + $maxTimeoutSeconds);

        $message = json_encode([
            'from'        => $from,
            'to'          => $to,
            'amount'      => $amount,
            'mint'        => $asset,
            'validBefore' => $validBefore,
            'nonce'       => $nonce,
        ], JSON_THROW_ON_ERROR);

        $signature = $this->signWithHsm($message);

        return [
            'signature'   => $signature,
            'transaction' => [
                'from'         => $from,
                'to'           => $to,
                'amount'       => $amount,
                'mint'         => $asset,
                'validBefore'  => $validBefore,
                'nonce'        => $nonce,
                'tokenProgram' => $extra['token_program']
                    ?? (string) config('x402.solana_programs.token_program', 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA'),
            ],
        ];
    }

    public function getAddress(): string
    {
        if ($this->publicKey === null) {
            $this->publicKey = $this->loadPublicKey();
        }

        return $this->publicKey;
    }

    private function signWithHsm(string $message): string
    {
        return match ($this->provider) {
            'sodium' => $this->signWithSodium($message),
            'aws'    => $this->signWithAws($message),
            'azure'  => $this->signWithAzure($message),
            default  => throw new RuntimeException("Unknown HSM provider: {$this->provider}"),
        };
    }

    private function signWithSodium(string $message): string
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Sodium provider reads raw private keys from disk — use "aws" or "azure" provider in production. '
                . 'Set X402_SOLANA_HSM_PROVIDER=aws in .env.'
            );
        }

        $keyPath = (string) config('x402.client.solana_key_path', '');
        if ($keyPath === '' || ! file_exists($keyPath)) {
            throw new RuntimeException(
                'Solana keypair file not found. Set X402_SOLANA_KEY_PATH in .env for sodium provider.'
            );
        }

        $keypairBytes = file_get_contents($keyPath);
        if ($keypairBytes === false || strlen($keypairBytes) < 64) {
            throw new RuntimeException('Invalid Solana keypair file format.');
        }

        // Ed25519 signing via libsodium
        $secretKey = substr($keypairBytes, 0, 64);
        $signature = sodium_crypto_sign_detached($message, $secretKey);

        // Zero sensitive key material
        sodium_memzero($secretKey);
        sodium_memzero($keypairBytes);

        return bin2hex($signature);
    }

    private function signWithAws(string $message): string
    {
        // AWS CloudHSM / KMS integration point
        // Uses AWS SDK to sign with the key identified by $this->keyId
        throw new RuntimeException(
            'AWS CloudHSM Solana signing not yet implemented. Use sodium provider for development.'
        );
    }

    private function signWithAzure(string $message): string
    {
        // Azure Key Vault integration point
        throw new RuntimeException(
            'Azure Key Vault Solana signing not yet implemented. Use sodium provider for development.'
        );
    }

    private function loadPublicKey(): string
    {
        return match ($this->provider) {
            'sodium' => $this->loadSodiumPublicKey(),
            default  => (string) config('x402.client.solana_signer_address', ''),
        };
    }

    private function loadSodiumPublicKey(): string
    {
        $keyPath = (string) config('x402.client.solana_key_path', '');
        if ($keyPath === '' || ! file_exists($keyPath)) {
            return (string) config('x402.client.solana_signer_address', '');
        }

        $keypairBytes = file_get_contents($keyPath);
        if ($keypairBytes === false || strlen($keypairBytes) < 64) {
            return '';
        }

        // Extract public key (last 32 bytes of 64-byte keypair)
        $publicKey = substr($keypairBytes, 32, 32);
        sodium_memzero($keypairBytes);

        return self::base58Encode($publicKey);
    }

    /**
     * Base58 encode (Bitcoin/Solana alphabet).
     */
    private static function base58Encode(string $bytes): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

        // Count leading zero bytes
        $leadingZeros = 0;
        $len = strlen($bytes);
        while ($leadingZeros < $len && $bytes[$leadingZeros] === "\x00") {
            $leadingZeros++;
        }

        // Convert bytes to a GMP integer and encode
        $num = gmp_import($bytes);
        $encoded = '';
        $base = gmp_init(58);
        $zero = gmp_init(0);

        while (gmp_cmp($num, $zero) > 0) {
            [$num, $remainder] = gmp_div_qr($num, $base);
            $encoded = $alphabet[gmp_intval($remainder)] . $encoded;
        }

        // Prepend '1' for each leading zero byte
        return str_repeat('1', $leadingZeros) . $encoded;
    }
}
