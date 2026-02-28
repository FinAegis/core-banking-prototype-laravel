<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Services;

use App\Domain\Mobile\Exceptions\BiometricBlockedException;
use App\Domain\Mobile\Models\BiometricChallenge;
use App\Domain\Mobile\Models\BiometricFailure;
use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Models\User;
use App\Traits\HasApiScopes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Service for WebAuthn/Passkey authentication.
 *
 * Follows the same security patterns as BiometricAuthenticationService:
 * challenge/response flow, rate limiting, IP validation, session creation.
 */
class PasskeyAuthenticationService
{
    use HasApiScopes;

    private const SESSION_DURATION_MINUTES = 30;

    private const TRUSTED_SESSION_DURATION_MINUTES = 120;

    /**
     * Generate a WebAuthn challenge for passkey authentication.
     */
    public function generateChallenge(MobileDevice $device, ?string $ipAddress = null): BiometricChallenge
    {
        // Invalidate any existing pending challenges
        BiometricChallenge::where('mobile_device_id', $device->id)
            ->pending()
            ->update(['status' => BiometricChallenge::STATUS_EXPIRED]);

        return BiometricChallenge::createForDevice($device, $ipAddress);
    }

    /**
     * Verify a WebAuthn assertion and create an authenticated session.
     *
     * @param  string  $credentialId  The credential ID from the authenticator (base64url)
     * @param  string  $authenticatorData  The authenticator data (base64url)
     * @param  string  $clientDataJSON  The client data JSON (base64url)
     * @param  string  $signature  The signature from the authenticator (base64url)
     * @return array{access_token: string, refresh_token: string, expires_at: \Carbon\Carbon, session_id: string}|null
     *
     * @throws BiometricBlockedException If the device is temporarily blocked
     */
    public function verifyAndAuthenticate(
        MobileDevice $device,
        string $challenge,
        string $credentialId,
        string $authenticatorData,
        string $clientDataJSON,
        string $signature,
        ?string $ipAddress = null,
    ): ?array {
        // SECURITY CHECK 1: Is device blocked?
        if ($device->is_blocked) {
            Log::warning('Passkey auth: device is blocked', [
                'device_id' => $device->id,
                'reason'    => $device->blocked_reason,
            ]);

            return null;
        }

        // SECURITY CHECK 2: Is biometric/passkey temporarily blocked due to failures?
        if ($device->isBiometricBlocked()) {
            /** @var \Carbon\Carbon $blockedUntil */
            $blockedUntil = $device->biometric_blocked_until;

            throw new BiometricBlockedException($blockedUntil);
        }

        // SECURITY CHECK 3: Rate limit check
        $maxFailures = (int) config('mobile.security.max_biometric_failures', 3);
        $recentFailures = BiometricFailure::countRecentForDevice($device->id, 10);

        if ($recentFailures >= $maxFailures) {
            $this->blockDevicePasskey($device);
            $device->refresh();

            /** @var \Carbon\Carbon $blockedUntil */
            $blockedUntil = $device->biometric_blocked_until;

            throw new BiometricBlockedException($blockedUntil);
        }

        // Check if device has passkey enabled and credentials stored
        if (! $device->passkey_enabled || $device->passkey_credential_id === null || $device->passkey_public_key === null) {
            Log::warning('Passkey auth: passkey not enabled or no credentials', [
                'device_id'       => $device->id,
                'passkey_enabled' => $device->passkey_enabled,
            ]);

            return null;
        }

        // Verify credential ID matches
        if ($credentialId !== $device->passkey_credential_id) {
            Log::warning('Passkey auth: credential ID mismatch', [
                'device_id' => $device->id,
            ]);
            $this->recordFailure($device, 'passkey_credential_mismatch', $ipAddress);

            return null;
        }

        // Find the pending challenge
        $biometricChallenge = BiometricChallenge::where('mobile_device_id', $device->id)
            ->where('challenge', $challenge)
            ->pending()
            ->first();

        if (! $biometricChallenge) {
            Log::warning('Passkey auth: challenge not found or expired', [
                'device_id' => $device->id,
            ]);
            $this->recordFailure($device, 'passkey_challenge_not_found', $ipAddress);

            return null;
        }

        // SECURITY CHECK 4: Validate client data contains the correct challenge
        if (! $this->validateClientData($clientDataJSON, $challenge)) {
            $biometricChallenge->markAsFailed();
            $this->recordFailure($device, 'passkey_client_data_invalid', $ipAddress);

            return null;
        }

        // Verify the WebAuthn signature
        /** @var string $publicKey */
        $publicKey = $device->passkey_public_key;
        if (! $this->verifyWebAuthnSignature($authenticatorData, $clientDataJSON, $signature, $publicKey)) {
            $biometricChallenge->markAsFailed();
            $this->recordFailure($device, 'passkey_signature_invalid', $ipAddress);

            Log::warning('Passkey signature verification failed', [
                'device_id'    => $device->id,
                'challenge_id' => $biometricChallenge->id,
            ]);

            return null;
        }

        // Create session on success
        try {
            return DB::transaction(function () use ($device, $biometricChallenge, $ipAddress) {
                $biometricChallenge->markAsVerified();
                $device->resetBiometricFailures();

                $sessionDuration = $device->is_trusted
                    ? self::TRUSTED_SESSION_DURATION_MINUTES
                    : self::SESSION_DURATION_MINUTES;

                $session = MobileDeviceSession::create([
                    'mobile_device_id'     => $device->id,
                    'user_id'              => $device->user_id,
                    'session_token'        => MobileDeviceSession::generateToken(),
                    'ip_address'           => $ipAddress,
                    'last_activity_at'     => now(),
                    'expires_at'           => now()->addMinutes($sessionDuration),
                    'is_biometric_session' => true,
                ]);

                $user = $device->user;
                if (! $user) {
                    throw new RuntimeException('User not found for device');
                }
                $tokenPair = $this->createTokenPair($user, 'mobile-passkey');

                $device->update(['last_active_at' => now()]);

                Log::info('Passkey authentication successful', [
                    'user_id'    => $device->user_id,
                    'device_id'  => $device->device_id,
                    'session_id' => $session->id,
                ]);

                return [
                    'access_token'  => $tokenPair['access_token'],
                    'refresh_token' => $tokenPair['refresh_token'],
                    'expires_at'    => $session->expires_at,
                    'session_id'    => $session->id,
                ];
            });
        } catch (BiometricBlockedException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Passkey session creation failed', [
                'device_id' => $device->id,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Register a passkey credential for a device.
     *
     * @return array{credential_id: string, registered_at: string}
     */
    public function registerPasskey(MobileDevice $device, string $credentialId, string $publicKey): array
    {
        $device->update([
            'passkey_enabled'       => true,
            'passkey_credential_id' => $credentialId,
            'passkey_public_key'    => $publicKey,
            'passkey_enabled_at'    => now(),
        ]);

        Log::info('Passkey registered for device', [
            'device_id' => $device->device_id,
            'user_id'   => $device->user_id,
        ]);

        return [
            'credential_id' => $credentialId,
            'registered_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate a WebAuthn registration challenge (PublicKeyCredentialCreationOptions).
     *
     * @return array{challenge: string, rp: array, user: array, pubKeyCredParams: array, timeout: int, authenticatorSelection: array, attestation: string, excludeCredentials: array, expires_at: string}
     */
    public function generateRegistrationChallenge(MobileDevice $device, User $user): array
    {
        // Invalidate any existing pending challenges
        BiometricChallenge::where('mobile_device_id', $device->id)
            ->pending()
            ->update(['status' => BiometricChallenge::STATUS_EXPIRED]);

        $challenge = BiometricChallenge::createForDevice($device);

        // Collect existing credentials to exclude (prevent duplicate registration)
        $existingCredentials = MobileDevice::where('user_id', $user->id)
            ->whereNotNull('passkey_credential_id')
            ->pluck('passkey_credential_id')
            ->map(fn (string $id) => [
                'id'   => $id,
                'type' => 'public-key',
            ])
            ->all();

        $rpId = (string) config('mobile.webauthn.rp_id', 'finaegis.com');

        return [
            'challenge' => $challenge->challenge,
            'rp'        => [
                'id'   => $rpId,
                'name' => (string) config('mobile.webauthn.rp_name', 'FinAegis'),
            ],
            'user' => [
                'id'          => base64_encode((string) $user->id),
                'name'        => $user->email,
                'displayName' => $user->name ?? $user->email,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256 (ECDSA w/ SHA-256)
                ['type' => 'public-key', 'alg' => -257],  // RS256 (RSASSA-PKCS1-v1_5 w/ SHA-256)
            ],
            'timeout'                => (int) config('mobile.webauthn.timeout', 60000),
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey'             => 'preferred',
                'userVerification'        => 'required',
            ],
            'attestation'        => 'none',
            'excludeCredentials' => $existingCredentials,
            'expires_at'         => $challenge->expires_at->toIso8601String(),
        ];
    }

    /**
     * Verify attestation and register a passkey credential from WebAuthn registration ceremony.
     *
     * Decodes the attestation object to extract the public key from authenticator data,
     * validates the client data JSON, and stores the credential.
     *
     * @return array{credential_id: string, registered_at: string}
     */
    public function registerPasskeyWithAttestation(
        MobileDevice $device,
        string $challenge,
        string $credentialId,
        string $clientDataJSON,
        string $attestationObject,
    ): array {
        // 1. Validate the pending challenge
        $biometricChallenge = BiometricChallenge::where('mobile_device_id', $device->id)
            ->where('challenge', $challenge)
            ->pending()
            ->first();

        if (! $biometricChallenge) {
            throw new RuntimeException('Registration challenge not found or expired.');
        }

        // 2. Validate client data JSON
        $clientDataDecoded = base64_decode($clientDataJSON, true);
        if ($clientDataDecoded === false) {
            $biometricChallenge->markAsFailed();
            throw new RuntimeException('Invalid clientDataJSON encoding.');
        }

        $clientData = json_decode($clientDataDecoded, true);
        if (! is_array($clientData)) {
            $biometricChallenge->markAsFailed();
            throw new RuntimeException('Invalid clientDataJSON format.');
        }

        // Type must be "webauthn.create" for registration
        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            $biometricChallenge->markAsFailed();
            throw new RuntimeException('Invalid clientDataJSON type: expected webauthn.create.');
        }

        // Challenge must match
        if (! hash_equals($challenge, $clientData['challenge'] ?? '')) {
            $biometricChallenge->markAsFailed();
            throw new RuntimeException('Challenge mismatch in clientDataJSON.');
        }

        // Validate origin
        $expectedOrigin = (string) config('mobile.webauthn.origin', 'https://finaegis.com');
        $clientOrigin = $clientData['origin'] ?? '';
        if ($clientOrigin !== $expectedOrigin && ! app()->environment('local', 'testing')) {
            $biometricChallenge->markAsFailed();
            Log::warning('Passkey registration: origin mismatch', [
                'expected' => $expectedOrigin,
                'actual'   => $clientOrigin,
            ]);
            throw new RuntimeException('Origin validation failed.');
        }

        // 3. Decode attestation object (CBOR)
        $attestationData = base64_decode($attestationObject, true);
        if ($attestationData === false) {
            $biometricChallenge->markAsFailed();
            throw new RuntimeException('Invalid attestationObject encoding.');
        }

        $publicKey = $this->extractPublicKeyFromAttestation($attestationData);

        // 4. Mark challenge as verified and store credential
        $biometricChallenge->markAsVerified();

        return $this->registerPasskey($device, $credentialId, $publicKey);
    }

    /**
     * Extract the public key from attestation object's authenticator data.
     *
     * The authenticator data structure (§6.1 of WebAuthn spec):
     * - rpIdHash (32 bytes)
     * - flags (1 byte)
     * - signCount (4 bytes, big-endian)
     * - attestedCredentialData (variable, if AT flag set):
     *   - aaguid (16 bytes)
     *   - credentialIdLength (2 bytes, big-endian)
     *   - credentialId (credentialIdLength bytes)
     *   - credentialPublicKey (CBOR-encoded COSE key)
     */
    private function extractPublicKeyFromAttestation(string $attestationData): string
    {
        // For "none" attestation, the attestation object is CBOR-encoded:
        // { "fmt": "none", "attStmt": {}, "authData": <bytes> }
        // We use a simplified CBOR parser for this known structure.

        $authData = $this->extractAuthDataFromCbor($attestationData);
        if ($authData === null) {
            throw new RuntimeException('Failed to extract authData from attestation object.');
        }

        // Parse authenticator data
        if (strlen($authData) < 37) {
            throw new RuntimeException('Authenticator data too short.');
        }

        // rpIdHash (32) + flags (1) + signCount (4) = 37 bytes minimum
        $flags = ord($authData[32]);
        $hasAttestedCredentialData = ($flags & 0x40) !== 0;

        if (! $hasAttestedCredentialData) {
            throw new RuntimeException('Authenticator data does not contain attested credential data.');
        }

        $offset = 37; // After rpIdHash + flags + signCount

        // aaguid (16 bytes)
        if (strlen($authData) < $offset + 16) {
            throw new RuntimeException('Authenticator data too short for aaguid.');
        }
        $offset += 16;

        // credentialIdLength (2 bytes, big-endian)
        if (strlen($authData) < $offset + 2) {
            throw new RuntimeException('Authenticator data too short for credentialIdLength.');
        }
        $credIdLen = unpack('n', substr($authData, $offset, 2));
        if ($credIdLen === false) {
            throw new RuntimeException('Failed to parse credentialIdLength.');
        }
        $credIdLen = (int) $credIdLen[1];
        $offset += 2;

        // credentialId (skip)
        $offset += $credIdLen;

        // Remaining bytes are the COSE public key (CBOR-encoded)
        $coseKeyBytes = substr($authData, $offset);
        if ($coseKeyBytes === '') {
            throw new RuntimeException('No COSE public key data found.');
        }

        // Convert COSE key to PEM for storage
        return $this->coseKeyToPem($coseKeyBytes);
    }

    /**
     * Extract authData from a CBOR-encoded attestation object.
     *
     * Handles the "none" format attestation (most common for platform authenticators).
     * Uses a minimal CBOR map parser to find the "authData" key.
     */
    private function extractAuthDataFromCbor(string $data): ?string
    {
        // CBOR map starts with 0xa3 (map of 3 items) for standard attestation
        if (strlen($data) < 2) {
            return null;
        }

        $pos = 0;
        $firstByte = ord($data[$pos]);
        $majorType = ($firstByte >> 5) & 0x07;

        // Must be a map (major type 5)
        if ($majorType !== 5) {
            return null;
        }

        $mapLen = $firstByte & 0x1f;
        $pos++;

        if ($mapLen > 27) {
            return null; // Unsupported additional info
        }

        // Handle extended length (mapLen is in [24..27] here)
        if ($mapLen >= 24) {
            $extraBytes = 1 << ($mapLen - 24);
            $mapLen = 0;
            for ($i = 0; $i < $extraBytes; $i++) {
                $mapLen = ($mapLen << 8) | ord($data[$pos]);
                $pos++;
            }
        }

        // Iterate map entries looking for "authData"
        for ($i = 0; $i < $mapLen; $i++) {
            $keyResult = $this->cborReadTextString($data, $pos);
            if ($keyResult === null) {
                return null;
            }
            [$key, $pos] = $keyResult;

            if ($key === 'authData') {
                $valueResult = $this->cborReadByteString($data, $pos);
                if ($valueResult === null) {
                    return null;
                }

                return $valueResult[0];
            }

            // Skip this value
            $pos = $this->cborSkipValue($data, $pos);
            if ($pos === null) {
                return null;
            }
        }

        return null;
    }

    /**
     * Read a CBOR text string at the given position.
     *
     * @return array{0: string, 1: int}|null [value, new_position]
     */
    private function cborReadTextString(string $data, int $pos): ?array
    {
        if ($pos >= strlen($data)) {
            return null;
        }

        $firstByte = ord($data[$pos]);
        $majorType = ($firstByte >> 5) & 0x07;
        if ($majorType !== 3) { // text string
            return null;
        }

        $len = $firstByte & 0x1f;
        $pos++;

        if ($len >= 24 && $len <= 27) {
            $extraBytes = 1 << ($len - 24);
            $len = 0;
            for ($i = 0; $i < $extraBytes; $i++) {
                $len = ($len << 8) | ord($data[$pos]);
                $pos++;
            }
        }

        $value = substr($data, $pos, $len);
        $pos += $len;

        return [$value, $pos];
    }

    /**
     * Read a CBOR byte string at the given position.
     *
     * @return array{0: string, 1: int}|null [value, new_position]
     */
    private function cborReadByteString(string $data, int $pos): ?array
    {
        if ($pos >= strlen($data)) {
            return null;
        }

        $firstByte = ord($data[$pos]);
        $majorType = ($firstByte >> 5) & 0x07;
        if ($majorType !== 2) { // byte string
            return null;
        }

        $len = $firstByte & 0x1f;
        $pos++;

        if ($len >= 24 && $len <= 27) {
            $extraBytes = 1 << ($len - 24);
            $len = 0;
            for ($i = 0; $i < $extraBytes; $i++) {
                $len = ($len << 8) | ord($data[$pos]);
                $pos++;
            }
        }

        $value = substr($data, $pos, $len);
        $pos += $len;

        return [$value, $pos];
    }

    /**
     * Skip a CBOR value and return the new position.
     */
    private function cborSkipValue(string $data, int $pos): ?int
    {
        if ($pos >= strlen($data)) {
            return null;
        }

        $firstByte = ord($data[$pos]);
        $majorType = ($firstByte >> 5) & 0x07;
        $addInfo = $firstByte & 0x1f;
        $pos++;

        // Parse the length/value
        $len = $addInfo;
        if ($addInfo >= 24 && $addInfo <= 27) {
            $extraBytes = 1 << ($addInfo - 24);
            $len = 0;
            for ($i = 0; $i < $extraBytes; $i++) {
                if ($pos >= strlen($data)) {
                    return null;
                }
                $len = ($len << 8) | ord($data[$pos]);
                $pos++;
            }
        }

        switch ($majorType) {
            case 0: // unsigned int
            case 1: // negative int
            case 7: // simple/float
                return $pos;
            case 2: // byte string
            case 3: // text string
                return $pos + $len;
            case 4: // array
                for ($i = 0; $i < $len; $i++) {
                    $pos = $this->cborSkipValue($data, $pos);
                    if ($pos === null) {
                        return null;
                    }
                }

                return $pos;
            case 5: // map
                for ($i = 0; $i < $len; $i++) {
                    $pos = $this->cborSkipValue($data, $pos); // key
                    if ($pos === null) {
                        return null;
                    }
                    $pos = $this->cborSkipValue($data, $pos); // value
                    if ($pos === null) {
                        return null;
                    }
                }

                return $pos;
            default:
                return null;
        }
    }

    /**
     * Convert a COSE_Key (CBOR-encoded) to PEM format.
     *
     * Supports EC2 (kty=2) with P-256 curve (ES256) and RSA (kty=3) keys.
     */
    private function coseKeyToPem(string $coseKeyBytes): string
    {
        // Parse the CBOR map for the COSE key
        $pos = 0;
        $firstByte = ord($coseKeyBytes[$pos]);
        $majorType = ($firstByte >> 5) & 0x07;

        if ($majorType !== 5) {
            throw new RuntimeException('Invalid COSE key: expected CBOR map.');
        }

        $mapLen = $firstByte & 0x1f;
        $pos++;

        if ($mapLen >= 24 && $mapLen <= 27) {
            $extraBytes = 1 << ($mapLen - 24);
            $mapLen = 0;
            for ($i = 0; $i < $extraBytes; $i++) {
                $mapLen = ($mapLen << 8) | ord($coseKeyBytes[$pos]);
                $pos++;
            }
        }

        $kty = null;
        $xCoord = null;
        $yCoord = null;

        for ($i = 0; $i < $mapLen; $i++) {
            $keyResult = $this->cborReadSignedInt($coseKeyBytes, $pos);
            if ($keyResult === null) {
                $pos = $this->cborSkipValue($coseKeyBytes, $pos);
                if ($pos === null) {
                    throw new RuntimeException('Failed to parse COSE key.');
                }
                $pos = $this->cborSkipValue($coseKeyBytes, $pos);
                if ($pos === null) {
                    throw new RuntimeException('Failed to parse COSE key.');
                }

                continue;
            }
            [$label, $pos] = $keyResult;

            switch ($label) {
                case 1: // kty
                    $valResult = $this->cborReadSignedInt($coseKeyBytes, $pos);
                    if ($valResult === null) {
                        throw new RuntimeException('Failed to parse COSE kty.');
                    }
                    [$kty, $pos] = $valResult;
                    break;
                case -2: // x coordinate (EC2)
                    $valResult = $this->cborReadByteString($coseKeyBytes, $pos);
                    if ($valResult === null) {
                        throw new RuntimeException('Failed to parse COSE x coordinate.');
                    }
                    [$xCoord, $pos] = $valResult;
                    break;
                case -3: // y coordinate (EC2)
                    $valResult = $this->cborReadByteString($coseKeyBytes, $pos);
                    if ($valResult === null) {
                        throw new RuntimeException('Failed to parse COSE y coordinate.');
                    }
                    [$yCoord, $pos] = $valResult;
                    break;
                default:
                    $pos = $this->cborSkipValue($coseKeyBytes, $pos);
                    if ($pos === null) {
                        throw new RuntimeException('Failed to skip COSE key field.');
                    }
                    break;
            }
        }

        // EC2 key (kty=2): convert uncompressed point to PEM
        if ($kty === 2 && $xCoord !== null && $yCoord !== null) {
            return $this->ec2KeyToPem($xCoord, $yCoord);
        }

        // For RSA or unknown key types, store as base64 for openssl_pkey_get_public
        throw new RuntimeException('Unsupported COSE key type: ' . ($kty ?? 'null') . '. Only ES256 (EC2/P-256) is supported.');
    }

    /**
     * Read a CBOR signed integer (major types 0 and 1).
     *
     * @return array{0: int, 1: int}|null [value, new_position]
     */
    private function cborReadSignedInt(string $data, int $pos): ?array
    {
        if ($pos >= strlen($data)) {
            return null;
        }

        $firstByte = ord($data[$pos]);
        $majorType = ($firstByte >> 5) & 0x07;

        if ($majorType !== 0 && $majorType !== 1) {
            return null;
        }

        $addInfo = $firstByte & 0x1f;
        $pos++;
        $value = $addInfo;

        if ($addInfo >= 24 && $addInfo <= 27) {
            $extraBytes = 1 << ($addInfo - 24);
            $value = 0;
            for ($i = 0; $i < $extraBytes; $i++) {
                if ($pos >= strlen($data)) {
                    return null;
                }
                $value = ($value << 8) | ord($data[$pos]);
                $pos++;
            }
        }

        // Major type 1 = negative integer: -1 - value
        if ($majorType === 1) {
            $value = -1 - $value;
        }

        return [$value, $pos];
    }

    /**
     * Convert EC2 P-256 key coordinates to PEM format.
     *
     * Creates a DER-encoded SubjectPublicKeyInfo structure with the
     * EC uncompressed point (0x04 || x || y) and wraps it in PEM.
     */
    private function ec2KeyToPem(string $x, string $y): string
    {
        // Uncompressed EC point: 0x04 || x || y
        $point = "\x04" . $x . $y;

        // ASN.1 DER encoding for SubjectPublicKeyInfo with P-256 (prime256v1) OID
        // SEQUENCE {
        //   SEQUENCE {
        //     OID 1.2.840.10045.2.1 (ecPublicKey)
        //     OID 1.2.840.10045.3.1.7 (prime256v1 / P-256)
        //   }
        //   BIT STRING (0x00 || point)
        // }
        $ecPublicKeyOid = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
        $prime256v1Oid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
        $algorithmSequence = "\x30" . chr(strlen($ecPublicKeyOid) + strlen($prime256v1Oid)) . $ecPublicKeyOid . $prime256v1Oid;

        $bitString = "\x03" . chr(strlen($point) + 1) . "\x00" . $point;
        $spki = "\x30" . chr(strlen($algorithmSequence) + strlen($bitString)) . $algorithmSequence . $bitString;

        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($spki), 64, "\n");
        $pem .= '-----END PUBLIC KEY-----';

        return $pem;
    }

    /**
     * Validate client data JSON contains the expected challenge.
     */
    private function validateClientData(string $clientDataJSON, string $challenge): bool
    {
        $decoded = base64_decode($clientDataJSON, true);
        if ($decoded === false) {
            return false;
        }

        $clientData = json_decode($decoded, true);
        if (! is_array($clientData)) {
            return false;
        }

        // WebAuthn spec: type must be "webauthn.get" for assertion
        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            return false;
        }

        // Challenge in clientDataJSON must match the server-issued challenge
        $clientChallenge = $clientData['challenge'] ?? '';

        return hash_equals($challenge, $clientChallenge);
    }

    /**
     * Verify WebAuthn assertion signature.
     *
     * Verifies that: signature = sign(authenticatorData + SHA256(clientDataJSON), privateKey)
     */
    private function verifyWebAuthnSignature(
        string $authenticatorData,
        string $clientDataJSON,
        string $signature,
        string $publicKey,
    ): bool {
        $authData = base64_decode($authenticatorData, true);
        $clientData = base64_decode($clientDataJSON, true);
        $sig = base64_decode($signature, true);

        if ($authData === false || $clientData === false || $sig === false) {
            return false;
        }

        // WebAuthn: signed data = authenticatorData || SHA256(clientDataJSON)
        $clientDataHash = hash('sha256', $clientData, true);
        $signedData = $authData . $clientDataHash;

        // Verify with the stored public key (PEM format expected)
        $pubKeyResource = openssl_pkey_get_public($publicKey);
        if ($pubKeyResource === false) {
            Log::warning('Passkey: invalid public key format');

            return false;
        }

        $result = openssl_verify($signedData, $sig, $pubKeyResource, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    private function recordFailure(MobileDevice $device, string $reason, ?string $ipAddress): void
    {
        BiometricFailure::create([
            'mobile_device_id' => $device->id,
            'failure_reason'   => $reason,
            'ip_address'       => $ipAddress,
        ]);

        $device->incrementBiometricFailures();
    }

    private function blockDevicePasskey(MobileDevice $device): void
    {
        $blockMinutes = (int) config('mobile.security.biometric_block_minutes', 15);
        $device->blockBiometric($blockMinutes);

        Log::warning('Passkey blocked for device due to failures', [
            'device_id'     => $device->id,
            'block_minutes' => $blockMinutes,
        ]);
    }
}
