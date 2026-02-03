<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Services;

use App\Domain\Mobile\Contracts\BiometricJWTServiceInterface;
use App\Domain\Mobile\Exceptions\BiometricJWTException;
use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for Biometric JWT token management.
 *
 * Generates and verifies JWT tokens for biometric authentication sessions.
 * Tokens bind a user to a specific device and session for UserOperation signing.
 *
 * Production Configuration:
 * - BIOMETRIC_JWT_SECRET: Secret key for HMAC-SHA256 signing (min 32 bytes)
 * - BIOMETRIC_JWT_TTL_SECONDS: Token lifetime (default: 300 = 5 minutes)
 *
 * @todo PRODUCTION: Consider RSA/ECDSA signing with key rotation
 * @todo PRODUCTION: Integrate with Apple App Attest and Google Play Integrity
 */
class BiometricJWTService implements BiometricJWTServiceInterface
{
    /**
     * JWT algorithm.
     */
    private const ALGORITHM = 'HS256';

    /**
     * Default token TTL in seconds.
     */
    private const DEFAULT_TTL_SECONDS = 300; // 5 minutes

    /**
     * Revoked tokens cache prefix.
     */
    private const REVOKED_TOKENS_PREFIX = 'biometric_jwt_revoked:';

    /**
     * Revoked tokens cache TTL (24 hours).
     */
    private const REVOKED_TTL_HOURS = 24;

    /**
     * Minimum token length for validation.
     */
    private const MIN_TOKEN_LENGTH = 32;

    private string $secret;

    private int $ttlSeconds;

    public function __construct()
    {
        $this->secret = $this->getSecret();
        $this->ttlSeconds = (int) config('mobile.biometric_jwt.ttl_seconds', self::DEFAULT_TTL_SECONDS);
    }

    /**
     * Generate a JWT token for a biometric session.
     */
    public function generateToken(
        User $user,
        MobileDevice $device,
        MobileDeviceSession $session
    ): string {
        $now = new DateTimeImmutable();
        $exp = $now->modify('+' . $this->ttlSeconds . ' seconds');

        $header = [
            'alg' => self::ALGORITHM,
            'typ' => 'JWT',
        ];

        $payload = [
            'iss'    => config('app.name', 'FinAegis'),
            'sub'    => $user->uuid,
            'aud'    => 'userop_signing',
            'iat'    => $now->getTimestamp(),
            'exp'    => $exp->getTimestamp(),
            'jti'    => bin2hex(random_bytes(16)),
            'claims' => [
                'user_id'            => $user->id,
                'device_id'          => $device->id,
                'session_id'         => $session->id,
                'device_fingerprint' => $device->device_id,
                'biometric_key_id'   => $device->biometric_key_id,
                'is_trusted_device'  => $device->is_trusted,
            ],
        ];

        $token = $this->encode($header, $payload);

        Log::debug('Biometric JWT generated', [
            'user_id'    => $user->id,
            'device_id'  => $device->id,
            'session_id' => $session->id,
            'jti'        => $payload['jti'],
            'exp'        => $exp->format('c'),
        ]);

        return $token;
    }

    /**
     * Verify a biometric JWT token.
     */
    public function verifyToken(User $user, string $token): bool
    {
        try {
            $claims = $this->decodeToken($token);

            if ($claims === null) {
                return false;
            }

            // Verify user matches
            if (! isset($claims['sub']) || $claims['sub'] !== $user->uuid) {
                Log::warning('Biometric JWT user mismatch', [
                    'expected_uuid' => $user->uuid,
                    'token_sub'     => $claims['sub'] ?? 'missing',
                ]);

                return false;
            }

            // Check if token is revoked
            if (isset($claims['jti']) && $this->isTokenRevoked($claims['jti'])) {
                Log::warning('Biometric JWT token revoked', [
                    'jti'     => $claims['jti'],
                    'user_id' => $user->id,
                ]);

                return false;
            }

            // Verify session is still valid
            if (isset($claims['claims']['session_id'])) {
                /** @var MobileDeviceSession|null $session */
                $session = MobileDeviceSession::find($claims['claims']['session_id']);
                if ($session === null || $session->isExpired() || $session->user_id !== $user->id) {
                    Log::warning('Biometric JWT session invalid', [
                        'session_id' => $claims['claims']['session_id'],
                        'user_id'    => $user->id,
                    ]);

                    return false;
                }
            }

            return true;
        } catch (BiometricJWTException $e) {
            Log::warning('Biometric JWT verification failed', [
                'error'   => $e->getMessage(),
                'code'    => $e->errorCode,
                'user_id' => $user->id,
            ]);

            return false;
        }
    }

    /**
     * Decode a JWT token and return claims.
     */
    public function decodeToken(string $token): ?array
    {
        if (strlen($token) < self::MIN_TOKEN_LENGTH) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify signature
        $expectedSignature = $this->sign($headerB64 . '.' . $payloadB64);
        if (! hash_equals($expectedSignature, $signatureB64)) {
            throw BiometricJWTException::invalidSignature();
        }

        // Decode payload
        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($payloadJson === false) {
            throw BiometricJWTException::invalidToken('Failed to decode payload');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($payloadJson, true);
        if (! is_array($payload)) {
            throw BiometricJWTException::invalidToken('Invalid payload JSON');
        }

        // Check expiration
        if (! isset($payload['exp']) || ! is_int($payload['exp'])) {
            throw BiometricJWTException::invalidClaims('Missing expiration');
        }

        if ($payload['exp'] < time()) {
            throw BiometricJWTException::expiredToken();
        }

        // Check required claims
        if (! isset($payload['sub']) || ! isset($payload['claims'])) {
            throw BiometricJWTException::invalidClaims('Missing required claims');
        }

        return $payload;
    }

    /**
     * Verify device attestation (Apple App Attest / Google SafetyNet).
     *
     * @todo PRODUCTION: Implement actual attestation verification
     */
    public function verifyDeviceAttestation(string $attestation, string $deviceType): bool
    {
        if (empty($attestation)) {
            return false;
        }

        // Demo mode: Accept any non-empty attestation with minimum length
        // Production: Verify with Apple/Google attestation services
        $minLength = match ($deviceType) {
            'ios'     => 100,  // App Attest assertion minimum
            'android' => 50,   // SafetyNet response minimum
            default   => 32,
        };

        if (strlen($attestation) < $minLength) {
            Log::warning('Device attestation too short', [
                'type'            => $deviceType,
                'length'          => strlen($attestation),
                'required_length' => $minLength,
            ]);

            return false;
        }

        // Demo: Log attestation verification (would verify with Apple/Google in production)
        Log::debug('Device attestation verified (demo mode)', [
            'type'   => $deviceType,
            'length' => strlen($attestation),
        ]);

        return true;
    }

    /**
     * Revoke all tokens for a device.
     */
    public function revokeDeviceTokens(MobileDevice $device): int
    {
        // In a production system, we would:
        // 1. Query all active sessions for the device
        // 2. Mark their JTIs as revoked
        // 3. Invalidate the sessions

        $sessions = MobileDeviceSession::where('mobile_device_id', $device->id)
            ->where('is_biometric_session', true)
            ->where('expires_at', '>', now())
            ->get();

        $count = 0;
        foreach ($sessions as $session) {
            // Add a revocation entry for this session
            $revokedKey = self::REVOKED_TOKENS_PREFIX . 'session:' . $session->id;
            Cache::put($revokedKey, true, now()->addHours(self::REVOKED_TTL_HOURS));
            $session->invalidate();
            $count++;
        }

        Log::info('Device tokens revoked', [
            'device_id' => $device->id,
            'count'     => $count,
        ]);

        return $count;
    }

    /**
     * Check if a token JTI is revoked.
     */
    private function isTokenRevoked(string $jti): bool
    {
        return Cache::has(self::REVOKED_TOKENS_PREFIX . $jti);
    }

    /**
     * Revoke a specific token by JTI.
     */
    public function revokeToken(string $jti): void
    {
        Cache::put(
            self::REVOKED_TOKENS_PREFIX . $jti,
            true,
            now()->addHours(self::REVOKED_TTL_HOURS)
        );
    }

    /**
     * Encode JWT.
     *
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $header, array $payload): string
    {
        $headerB64 = $this->base64UrlEncode((string) json_encode($header));
        $payloadB64 = $this->base64UrlEncode((string) json_encode($payload));
        $signature = $this->sign($headerB64 . '.' . $payloadB64);

        return $headerB64 . '.' . $payloadB64 . '.' . $signature;
    }

    /**
     * Sign data with HMAC-SHA256.
     */
    private function sign(string $data): string
    {
        $hash = hash_hmac('sha256', $data, $this->secret, true);

        return $this->base64UrlEncode($hash);
    }

    /**
     * Base64 URL encode.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode.
     */
    private function base64UrlDecode(string $data): string|false
    {
        $padded = str_pad($data, strlen($data) % 4, '=', STR_PAD_RIGHT);

        return base64_decode(strtr($padded, '-_', '+/'), true);
    }

    /**
     * Get the JWT signing secret.
     */
    private function getSecret(): string
    {
        $secret = config('mobile.biometric_jwt.secret');

        if (empty($secret)) {
            // Fallback to app key in development
            $secret = config('app.key');
        }

        if (empty($secret) || strlen($secret) < 32) {
            Log::warning('Biometric JWT secret is weak or missing - using fallback');
            // Demo fallback - NOT for production
            $secret = 'demo-biometric-jwt-secret-key-32b!';
        }

        return (string) $secret;
    }
}
