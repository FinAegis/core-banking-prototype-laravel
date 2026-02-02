<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\UserOperationSignerInterface;
use App\Domain\Relayer\Exceptions\UserOpSigningException;
use App\Models\User;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for signing UserOperations with the server's authentication shard.
 *
 * DEMO IMPLEMENTATION - NOT FOR PRODUCTION USE
 *
 * @todo PRODUCTION: Replace signWithAuthShard() with HSM integration via KeyManagement domain
 * @todo PRODUCTION: Replace verifyBiometricToken() with JWT verification + mobile attestation
 * @todo PRODUCTION: Add device binding validation against MobileDeviceSession
 * @todo PRODUCTION: Implement proper ECDSA signing with secp256k1
 *
 * In production, this service would:
 * - Retrieve the user's auth shard from secure storage (HSM, KeyManagement)
 * - Verify biometric authentication with the mobile device using JWT + attestation
 * - Sign the UserOperation hash with the auth shard using proper ECDSA
 * - Return a signature that can be combined with the device shard
 *
 * This demo implementation returns deterministic mock signatures.
 */
class UserOperationSigningService implements UserOperationSignerInterface
{
    /**
     * Signature validity period in seconds.
     */
    private const SIGNATURE_VALIDITY_SECONDS = 300; // 5 minutes

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

        // 4. Sign with auth shard (demo implementation)
        $authShardSignature = $this->signWithAuthShard($user, $userOpHash);

        $signedAt = new DateTimeImmutable();
        $expiresAt = $signedAt->modify('+' . self::SIGNATURE_VALIDITY_SECONDS . ' seconds');

        // Log only first 6 chars (3 bytes) of hash to reduce correlation risk
        Log::info('UserOperation signed with auth shard', [
            'user_id'    => $user->id,
            'hash_hint'  => substr($userOpHash, 0, 6),
            'expires_at' => $expiresAt->format('c'),
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
     * In production, this would verify the token against the mobile biometric system.
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
     * Sign with the authentication shard.
     *
     * DEMO IMPLEMENTATION - Produces fake signatures for testing only.
     *
     * @todo PRODUCTION: Use ShamirService to reconstruct signing key
     * @todo PRODUCTION: Use HSM (simplehsm, AWS CloudHSM, Azure HSM) for signing
     * @todo PRODUCTION: Implement proper secp256k1 ECDSA with random k
     * @todo PRODUCTION: Never use config('app.key') for cryptographic operations
     *
     * In production, this would:
     * 1. Retrieve user's auth shard from KeyManagement (HSM-backed)
     * 2. Sign the userOpHash with proper ECDSA using secp256k1
     * 3. Return the (r, s, v) signature tuple
     */
    private function signWithAuthShard(User $user, string $userOpHash): string
    {
        // DEMO: Generate deterministic signature for testing
        // WARNING: This is NOT cryptographically secure - do not use in production
        $sigData = hash('sha256', $user->id . $userOpHash . config('app.key'));

        // Create an ECDSA-like signature structure (r, s, v)
        // NOTE: This is a fake structure and will NOT verify on-chain
        $r = '0x' . substr($sigData, 0, 64);
        $s = '0x' . hash('sha256', $sigData);
        $v = '1b'; // Recovery id (demo value)

        return $r . substr($s, 2) . $v;
    }

    /**
     * Maximum signing requests per user per minute.
     */
    private const MAX_REQUESTS_PER_MINUTE = 10;

    /**
     * Rate limit window in seconds.
     */
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    /**
     * Check rate limiting for signing requests.
     *
     * Uses atomic increment to prevent race conditions.
     */
    private function checkRateLimit(User $user): void
    {
        $key = "userop_signing_rate:{$user->id}";

        // Use atomic increment to prevent race conditions
        // If key doesn't exist, increment() initializes it to 1
        $current = Cache::increment($key);

        // Set expiration on first request (when counter is 1)
        if ($current === 1) {
            Cache::put($key, 1, self::RATE_LIMIT_WINDOW_SECONDS);
        }

        if ($current > self::MAX_REQUESTS_PER_MINUTE) {
            throw UserOpSigningException::signingFailed('Rate limit exceeded. Try again later.');
        }
    }
}
