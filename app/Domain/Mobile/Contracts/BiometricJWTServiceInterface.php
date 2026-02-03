<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Contracts;

use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Models\User;

/**
 * Interface for Biometric JWT Service.
 *
 * Provides JWT-based authentication tokens for mobile biometric sessions.
 * These tokens are used for UserOperation signing in the Relayer domain.
 */
interface BiometricJWTServiceInterface
{
    /**
     * Generate a JWT token for a biometric session.
     *
     * @param  User  $user  The authenticated user
     * @param  MobileDevice  $device  The authenticated device
     * @param  MobileDeviceSession  $session  The biometric session
     * @return string Signed JWT token
     */
    public function generateToken(
        User $user,
        MobileDevice $device,
        MobileDeviceSession $session
    ): string;

    /**
     * Verify a biometric JWT token.
     *
     * @param  User  $user  The user to verify against
     * @param  string  $token  The JWT token to verify
     * @return bool True if token is valid and belongs to user
     */
    public function verifyToken(User $user, string $token): bool;

    /**
     * Decode a JWT token and return claims.
     *
     * @param  string  $token  The JWT token to decode
     * @return array<string, mixed>|null Claims array or null if invalid
     */
    public function decodeToken(string $token): ?array;

    /**
     * Verify device attestation (Apple App Attest / Google SafetyNet).
     *
     * @param  string  $attestation  The attestation data from the device
     * @param  string  $deviceType  'ios' or 'android'
     * @return bool True if attestation is valid
     */
    public function verifyDeviceAttestation(string $attestation, string $deviceType): bool;

    /**
     * Revoke all tokens for a device.
     *
     * @param  MobileDevice  $device  The device to revoke tokens for
     * @return int Number of tokens revoked
     */
    public function revokeDeviceTokens(MobileDevice $device): int;
}
