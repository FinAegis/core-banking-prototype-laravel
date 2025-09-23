<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SignatureService
{
    private const SIGNATURE_ALGORITHMS = [
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
        'ES256' => 'sha256',
        'ES384' => 'sha384',
        'ES512' => 'sha512',
    ];

    /**
     * Generate a digital signature for transaction data.
     */
    public function signTransaction(
        array $transactionData,
        string $privateKey,
        string $algorithm = 'RS256'
    ): array {
        try {
            $dataToSign = $this->canonicalizeData($transactionData);
            $signature = '';

            if (! array_key_exists($algorithm, self::SIGNATURE_ALGORITHMS)) {
                throw new InvalidArgumentException("Unsupported signature algorithm: {$algorithm}");
            }

            $algoConstant = self::SIGNATURE_ALGORITHMS[$algorithm];

            if (str_starts_with($algorithm, 'RS')) {
                // RSA signature
                $success = openssl_sign(
                    $dataToSign,
                    $signature,
                    $privateKey,
                    $algoConstant
                );

                if (! $success) {
                    throw new Exception('Failed to generate RSA signature');
                }
            } elseif (str_starts_with($algorithm, 'ES')) {
                // ECDSA signature (simplified)
                $hashAlgo = strval($algoConstant);
                $hash = hash($hashAlgo, $dataToSign, true);
                $signature = $this->generateECDSASignature($hash, $privateKey);
            }

            return [
                'signature' => base64_encode($signature),
                'algorithm' => $algorithm,
                'timestamp' => now()->toIso8601String(),
                'data_hash' => hash('sha256', $dataToSign),
            ];
        } catch (Exception $e) {
            Log::error('Transaction signing failed', [
                'error'     => $e->getMessage(),
                'algorithm' => $algorithm,
            ]);

            throw $e;
        }
    }

    /**
     * Verify a transaction signature.
     */
    public function verifySignature(
        array $transactionData,
        string $signature,
        string $publicKey,
        string $algorithm = 'RS256'
    ): bool {
        try {
            $dataToVerify = $this->canonicalizeData($transactionData);
            $signatureBinary = base64_decode($signature);

            if (! array_key_exists($algorithm, self::SIGNATURE_ALGORITHMS)) {
                throw new InvalidArgumentException("Unsupported signature algorithm: {$algorithm}");
            }

            $algoConstant = self::SIGNATURE_ALGORITHMS[$algorithm];

            if (str_starts_with($algorithm, 'RS')) {
                // RSA verification
                $result = openssl_verify(
                    $dataToVerify,
                    $signatureBinary,
                    $publicKey,
                    $algoConstant
                );

                return $result === 1;
            } elseif (str_starts_with($algorithm, 'ES')) {
                // ECDSA verification (simplified)
                $hashAlgo = strval($algoConstant);
                $hash = hash($hashAlgo, $dataToVerify, true);

                return $this->verifyECDSASignature($hash, $signatureBinary, $publicKey);
            }

            return false;
        } catch (Exception $e) {
            Log::error('Signature verification failed', [
                'error'     => $e->getMessage(),
                'algorithm' => $algorithm,
            ]);

            return false;
        }
    }

    /**
     * Generate a key pair for signing.
     */
    public function generateKeyPair(string $type = 'RSA', int $keySize = 2048): array
    {
        if ($type === 'RSA') {
            $config = [
                'private_key_bits' => $keySize,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];

            $resource = openssl_pkey_new($config);
            if (! $resource) {
                throw new Exception('Failed to generate RSA key pair');
            }

            $privateKey = '';
            openssl_pkey_export($resource, $privateKey);
            $publicKeyDetails = openssl_pkey_get_details($resource);
            $publicKey = $publicKeyDetails['key'];

            return [
                'private_key' => $privateKey,
                'public_key'  => $publicKey,
                'type'        => 'RSA',
                'size'        => $keySize,
            ];
        } elseif ($type === 'ECDSA') {
            // Simplified ECDSA key generation
            $config = [
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'curve_name'       => 'secp256k1',
            ];

            $resource = openssl_pkey_new($config);
            if (! $resource) {
                throw new Exception('Failed to generate ECDSA key pair');
            }

            $privateKey = '';
            openssl_pkey_export($resource, $privateKey);
            $publicKeyDetails = openssl_pkey_get_details($resource);
            $publicKey = $publicKeyDetails['key'];

            return [
                'private_key' => $privateKey,
                'public_key'  => $publicKey,
                'type'        => 'ECDSA',
                'curve'       => 'secp256k1',
            ];
        }

        throw new InvalidArgumentException("Unsupported key type: {$type}");
    }

    /**
     * Create a multi-signature transaction.
     */
    public function createMultiSignature(
        array $transactionData,
        array $signatures,
        int $requiredSignatures
    ): array {
        $signatureCount = count($signatures);
        if ($signatureCount < $requiredSignatures) {
            throw new InvalidArgumentException(
                "Insufficient signatures: {$signatureCount} provided, {$requiredSignatures} required"
            );
        }

        $dataHash = hash('sha256', $this->canonicalizeData($transactionData));

        return [
            'data_hash'           => $dataHash,
            'signatures'          => $signatures,
            'required_signatures' => $requiredSignatures,
            'total_signatures'    => count($signatures),
            'threshold_met'       => count($signatures) >= $requiredSignatures,
            'timestamp'           => now()->toIso8601String(),
        ];
    }

    /**
     * Canonicalize data for consistent signing/verification.
     */
    private function canonicalizeData(array $data): string
    {
        // Sort keys recursively for consistent ordering
        $this->recursiveKeySort($data);

        // Convert to JSON with specific flags for consistency
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('Failed to encode data for canonicalization');
        }

        return $json;
    }

    /**
     * Recursively sort array keys.
     */
    private function recursiveKeySort(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKeySort($value);
            }
        }
    }

    /**
     * Generate ECDSA signature (simplified implementation).
     */
    private function generateECDSASignature(string $hash, string $privateKey): string
    {
        // This is a simplified implementation
        // In production, use proper ECDSA library
        $key = openssl_pkey_get_private($privateKey);
        if (! $key) {
            throw new Exception('Invalid private key for ECDSA');
        }

        $signature = '';
        openssl_sign($hash, $signature, $key, OPENSSL_ALGO_SHA256);

        return $signature;
    }

    /**
     * Verify ECDSA signature (simplified implementation).
     */
    private function verifyECDSASignature(string $hash, string $signature, string $publicKey): bool
    {
        // This is a simplified implementation
        // In production, use proper ECDSA library
        $key = openssl_pkey_get_public($publicKey);
        if (! $key) {
            return false;
        }

        $result = openssl_verify($hash, $signature, $key, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }
}
