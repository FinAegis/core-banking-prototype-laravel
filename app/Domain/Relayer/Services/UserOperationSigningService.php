<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\KeyManagement\HSM\HsmIntegrationService;
use App\Domain\Relayer\Contracts\UserOperationSignerInterface;
use App\Domain\Relayer\Exceptions\UserOpSigningException;
use App\Models\User;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Service for signing UserOperations with the server's authentication shard.
 *
 * This service uses the HSM integration from KeyManagement domain for cryptographic
 * operations. In demo mode (HSM provider = 'demo'), it uses deterministic signatures
 * that are suitable for testing but NOT for production.
 *
 * Production Configuration:
 * - Set HSM_PROVIDER=aws|azure|hashicorp in .env
 * - Configure appropriate HSM credentials
 * - Ensure secp256k1 signing key exists in HSM
 *
 * @see \App\Domain\KeyManagement\HSM\HsmIntegrationService
 */
class UserOperationSigningService implements UserOperationSignerInterface
{
    /**
     * Signature validity period in seconds.
     */
    private const SIGNATURE_VALIDITY_SECONDS = 300; // 5 minutes

    /**
     * Maximum signing requests per user per minute.
     */
    private const MAX_REQUESTS_PER_MINUTE = 10;

    /**
     * Rate limit window in seconds.
     */
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    public function __construct(
        private readonly HsmIntegrationService $hsm
    ) {}

    /**
     * Sign a UserOperation hash using the server's authentication shard.
     *
     * @return array{auth_shard_signature: string, expires_at: DateTimeInterface, signed_at: DateTimeInterface}
     */
    public function signUserOperation(
        User $user,
        string $userOpHash,
        string $deviceShardProof,
        string $biometricToken
    ): array {
        // 1. Validate inputs
        if (! $this->validateUserOpHash($userOpHash)) {
            throw UserOpSigningException::invalidUserOpHash('Expected 32-byte hex string with 0x prefix');
        }

        if (! $this->validateDeviceShardProof($deviceShardProof)) {
            throw UserOpSigningException::invalidDeviceShard('Expected hex string with 0x prefix');
        }

        // 2. Verify biometric authentication
        if (! $this->verifyBiometricToken($user, $biometricToken)) {
            Log::warning('UserOp signing biometric verification failed', [
                'user_id' => $user->id,
            ]);
            throw UserOpSigningException::biometricVerificationFailed();
        }

        // 3. Check rate limiting
        $this->checkRateLimit($user);

        // 4. Sign with HSM
        $authShardSignature = $this->signWithHsm($user, $userOpHash);

        $signedAt = new DateTimeImmutable();
        $expiresAt = $signedAt->modify('+' . self::SIGNATURE_VALIDITY_SECONDS . ' seconds');

        // Log only first 6 chars (3 bytes) of hash to reduce correlation risk
        Log::info('UserOperation signed with auth shard', [
            'user_id'      => $user->id,
            'hash_hint'    => substr($userOpHash, 0, 6),
            'hsm_provider' => $this->hsm->getProviderName(),
            'expires_at'   => $expiresAt->format('c'),
        ]);

        return [
            'auth_shard_signature' => $authShardSignature,
            'expires_at'           => $expiresAt,
            'signed_at'            => $signedAt,
        ];
    }

    /**
     * Verify biometric token validity.
     *
     * @todo PRODUCTION: Replace with JWT verification + mobile attestation
     */
    public function verifyBiometricToken(User $user, string $biometricToken): bool
    {
        // Demo: Accept any non-empty token for now
        // In production: Verify against stored biometric session data
        if (empty($biometricToken)) {
            return false;
        }

        // Demo: Accept tokens that match expected format
        // In production: Cryptographic verification with mobile attestation
        return strlen($biometricToken) >= 32;
    }

    /**
     * Validate device shard proof format.
     */
    public function validateDeviceShardProof(string $deviceShardProof): bool
    {
        // Must be a hex string with 0x prefix
        return (bool) preg_match('/^0x[a-fA-F0-9]+$/', $deviceShardProof);
    }

    /**
     * Validate UserOperation hash format.
     */
    private function validateUserOpHash(string $userOpHash): bool
    {
        // UserOp hash is a 32-byte (64 hex chars) hash with 0x prefix
        return (bool) preg_match('/^0x[a-fA-F0-9]{64}$/', $userOpHash);
    }

    /**
     * Sign using HSM.
     *
     * Uses the KeyManagement HSM integration service for cryptographic signing.
     * The actual implementation depends on the configured HSM provider:
     * - demo: Deterministic signatures for testing (NOT SECURE)
     * - aws: AWS CloudHSM with secp256k1
     * - azure: Azure Key Vault HSM
     * - hashicorp: HashiCorp Vault Transit
     *
     * @param  User  $user  The user whose auth shard key should be used
     * @param  string  $userOpHash  The 32-byte hash to sign (hex with 0x prefix)
     * @return string ECDSA signature in compact format (r || s || v, 65 bytes)
     *
     * @throws UserOpSigningException If HSM is unavailable or signing fails
     */
    private function signWithHsm(User $user, string $userOpHash): string
    {
        try {
            // Verify HSM is available
            if (! $this->hsm->isAvailable()) {
                Log::error('HSM not available for UserOp signing', [
                    'user_id'  => $user->id,
                    'provider' => $this->hsm->getProviderName(),
                ]);
                throw UserOpSigningException::shardUnavailable('HSM provider not available');
            }

            // Get the user's signing key ID
            // In production, this would retrieve the key ID from the user's key shard record
            $keyId = $this->getUserSigningKeyId($user);

            // Sign using HSM
            $signature = $this->hsm->sign($userOpHash, $keyId);

            return $signature;
        } catch (UserOpSigningException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('HSM signing failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            throw UserOpSigningException::signingFailed('HSM signing operation failed');
        }
    }

    /**
     * Get the signing key ID for a user.
     *
     * In production, this would query the KeyShardRecord to find the user's
     * auth shard key stored in HSM.
     *
     * @todo PRODUCTION: Query KeyShardRecord for user's HSM key ID
     */
    private function getUserSigningKeyId(User $user): string
    {
        // Demo: Use user UUID as key identifier
        // Production: Query KeyShardRecord for the actual HSM key ID
        return 'user_auth_shard_' . $user->uuid;
    }

    /**
     * Check rate limiting for signing requests.
     *
     * Uses Laravel's rate limiter for atomic operation.
     */
    private function checkRateLimit(User $user): void
    {
        $key = "userop_signing:{$user->id}";

        // Use Laravel's rate limiter for atomic rate limiting
        $executed = RateLimiter::attempt(
            $key,
            self::MAX_REQUESTS_PER_MINUTE,
            fn () => true, // Return true if under limit
            self::RATE_LIMIT_WINDOW_SECONDS
        );

        if (! $executed) {
            $seconds = RateLimiter::availableIn($key);
            throw UserOpSigningException::rateLimited("Try again in {$seconds} seconds.");
        }
    }
}
