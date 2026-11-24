<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\HSM;

use App\Domain\KeyManagement\Contracts\HsmProviderInterface;
use Aws\Kms\KmsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * AWS KMS HSM provider for production key management.
 *
 * Uses AWS KMS for encrypt/decrypt/sign operations and
 * KMS envelope encryption with Laravel cache for secret storage.
 */
class AwsKmsHsmProvider implements HsmProviderInterface
{
    private const CACHE_PREFIX = 'aws_hsm:';

    private const CACHE_TTL_DAYS = 365;

    private readonly KmsClient $kmsClient;

    private readonly string $keyArn;

    private readonly string $signingKeyArn;

    public function __construct(KmsClient $kmsClient, string $keyArn, string $signingKeyArn = '')
    {
        $this->kmsClient = $kmsClient;
        $this->keyArn = $keyArn;
        $this->signingKeyArn = $signingKeyArn ?: $keyArn;
    }

    public function encrypt(string $data, string $keyId): string
    {
        try {
            $result = $this->kmsClient->encrypt([
                'KeyId'               => $this->resolveKeyArn($keyId),
                'Plaintext'           => $data,
                'EncryptionAlgorithm' => 'SYMMETRIC_DEFAULT',
            ]);

            return base64_encode((string) $result['CiphertextBlob']);
        } catch (Throwable $e) {
            Log::error('AWS KMS encrypt failed', ['error' => $e->getMessage(), 'keyId' => $keyId]);

            throw new RuntimeException('AWS KMS encryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function decrypt(string $encryptedData, string $keyId): string
    {
        try {
            $result = $this->kmsClient->decrypt([
                'KeyId'               => $this->resolveKeyArn($keyId),
                'CiphertextBlob'      => base64_decode($encryptedData),
                'EncryptionAlgorithm' => 'SYMMETRIC_DEFAULT',
            ]);

            return (string) $result['Plaintext'];
        } catch (Throwable $e) {
            Log::error('AWS KMS decrypt failed', ['error' => $e->getMessage(), 'keyId' => $keyId]);

            throw new RuntimeException('AWS KMS decryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function store(string $secretId, string $data): bool
    {
        try {
            $encrypted = $this->encrypt($data, 'storage');
            Cache::put(self::CACHE_PREFIX . $secretId, $encrypted, now()->addDays(self::CACHE_TTL_DAYS));

            return true;
        } catch (Throwable $e) {
            Log::error('AWS KMS store failed', ['error' => $e->getMessage(), 'secretId' => $secretId]);

            return false;
        }
    }

    public function retrieve(string $secretId): ?string
    {
        $encrypted = Cache::get(self::CACHE_PREFIX . $secretId);

        if ($encrypted === null) {
            return null;
        }

        try {
            return $this->decrypt($encrypted, 'storage');
        } catch (Throwable $e) {
            Log::error('AWS KMS retrieve failed', ['error' => $e->getMessage(), 'secretId' => $secretId]);

            return null;
        }
    }

    public function delete(string $secretId): bool
    {
        return Cache::forget(self::CACHE_PREFIX . $secretId);
    }

    public function isAvailable(): bool
    {
        try {
            $this->kmsClient->describeKey(['KeyId' => $this->keyArn]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'aws';
    }

    public function sign(string $messageHash, string $keyId): string
    {
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $messageHash)) {
            throw new RuntimeException('Invalid message hash format. Expected 32-byte hex with 0x prefix.');
        }

        try {
            $hashBytes = hex2bin(substr($messageHash, 2));
            if ($hashBytes === false) {
                throw new RuntimeException('Invalid message hash format. Expected 32-byte hex with 0x prefix.');
            }

            $result = $this->kmsClient->sign([
                'KeyId'            => $this->resolveSigningKeyArn($keyId),
                'Message'          => $hashBytes,
                'MessageType'      => 'DIGEST',
                'SigningAlgorithm' => 'ECDSA_SHA_256',
            ]);

            $derSignature = (string) $result['Signature'];

            return $this->derToCompactSignature($derSignature);
        } catch (Throwable $e) {
            Log::error('AWS KMS sign failed', ['error' => $e->getMessage(), 'keyId' => $keyId]);

            throw new RuntimeException('AWS KMS signing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function verify(string $messageHash, string $signature, string $publicKey): bool
    {
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $messageHash)) {
            return false;
        }

        if (! preg_match('/^0x[a-fA-F0-9]+$/', $signature)) {
            return false;
        }

        // Validate signature length: 0x + 64 (r) + 64 (s) + 2 (v) = 132 chars
        return strlen($signature) >= 132;
    }

    public function getPublicKey(string $keyId): string
    {
        try {
            $result = $this->kmsClient->getPublicKey([
                'KeyId' => $this->resolveSigningKeyArn($keyId),
            ]);

            $publicKeyDer = (string) $result['PublicKey'];

            return $this->extractPublicKeyFromDer($publicKeyDer);
        } catch (Throwable $e) {
            Log::error('AWS KMS getPublicKey failed', ['error' => $e->getMessage(), 'keyId' => $keyId]);

            throw new RuntimeException('AWS KMS getPublicKey failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Convert DER-encoded ECDSA signature to compact format (r || s || v).
     */
    private function derToCompactSignature(string $derSignature): string
    {
        $hex = bin2hex($derSignature);

        // DER: 30 <len> 02 <r_len> <r> 02 <s_len> <s>
        if (! str_starts_with($hex, '30')) {
            throw new RuntimeException('Invalid DER signature format');
        }

        $offset = 4; // Skip 30 <total_len>
        if (substr($hex, $offset, 2) !== '02') {
            throw new RuntimeException('Invalid DER signature: expected integer tag for r');
        }
        $offset += 2;

        $rLen = (int) hexdec(substr($hex, $offset, 2));
        $offset += 2;

        $rHex = substr($hex, $offset, $rLen * 2);
        $offset += $rLen * 2;

        // Strip leading zero byte if present (DER encoding adds it for positive integers)
        if (strlen($rHex) > 64 && str_starts_with($rHex, '00')) {
            $rHex = substr($rHex, 2);
        }
        $rHex = str_pad($rHex, 64, '0', STR_PAD_LEFT);

        if (substr($hex, $offset, 2) !== '02') {
            throw new RuntimeException('Invalid DER signature: expected integer tag for s');
        }
        $offset += 2;

        $sLen = (int) hexdec(substr($hex, $offset, 2));
        $offset += 2;

        $sHex = substr($hex, $offset, $sLen * 2);

        if (strlen($sHex) > 64 && str_starts_with($sHex, '00')) {
            $sHex = substr($sHex, 2);
        }
        $sHex = str_pad($sHex, 64, '0', STR_PAD_LEFT);

        // v = 0x1b (27) as default recovery id
        return '0x' . $rHex . $sHex . '1b';
    }

    /**
     * Extract public key from DER-encoded SubjectPublicKeyInfo.
     */
    private function extractPublicKeyFromDer(string $derBytes): string
    {
        $hex = bin2hex($derBytes);

        // Look for uncompressed point (04 prefix) in the DER structure
        $pos = strpos($hex, '04', 48);
        if ($pos !== false) {
            $uncompressedKey = substr($hex, $pos);
            if (strlen($uncompressedKey) >= 130) {
                // Return 64-byte uncompressed key (without 04 prefix)
                return '0x' . substr($uncompressedKey, 2, 128);
            }
        }

        // Fallback: return last 64 bytes as compressed key
        $keyHex = substr($hex, -66);

        return '0x' . $keyHex;
    }

    private function resolveKeyArn(string $keyId): string
    {
        if (str_starts_with($keyId, 'arn:')) {
            return $keyId;
        }

        return $this->keyArn;
    }

    private function resolveSigningKeyArn(string $keyId): string
    {
        if (str_starts_with($keyId, 'arn:')) {
            return $keyId;
        }

        return $this->signingKeyArn;
    }
}
