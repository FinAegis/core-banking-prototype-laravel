<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Contracts;

use App\Models\User;
use DateTimeInterface;

/**
 * Interface for UserOperation signing with authentication shards.
 *
 * This interface defines the contract for signing ERC-4337 UserOperations
 * using the server-side authentication shard. The complete signature
 * requires both the device shard (client-side) and auth shard (server-side).
 */
interface UserOperationSignerInterface
{
    /**
     * Sign a UserOperation hash using the server's authentication shard.
     *
     * The signing flow:
     * 1. Mobile app creates UserOperation and computes user_op_hash
     * 2. Mobile signs with device shard: device_shard_proof
     * 3. Mobile calls this endpoint with biometric authentication
     * 4. Server validates biometrics and signs with auth shard
     * 5. Mobile combines both signatures for the final UserOp signature
     *
     * @param  User    $user              The authenticated user
     * @param  string  $userOpHash        The EIP-191 hash of the UserOperation
     * @param  string  $deviceShardProof  Signature from the device's key shard
     * @param  string  $biometricToken    Token proving biometric authentication
     * @return array{auth_shard_signature: string, expires_at: DateTimeInterface, signed_at: DateTimeInterface}
     *
     * @throws \App\Domain\Relayer\Exceptions\UserOpSigningException
     */
    public function signUserOperation(
        User $user,
        string $userOpHash,
        string $deviceShardProof,
        string $biometricToken
    ): array;

    /**
     * Verify that a biometric token is valid for the user.
     *
     * @param  User    $user            The user to verify
     * @param  string  $biometricToken  Token from biometric authentication
     */
    public function verifyBiometricToken(User $user, string $biometricToken): bool;

    /**
     * Validate that the device shard proof is correctly formatted.
     *
     * @param  string  $deviceShardProof  The proof to validate
     */
    public function validateDeviceShardProof(string $deviceShardProof): bool;
}
