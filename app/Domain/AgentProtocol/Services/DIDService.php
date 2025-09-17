<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DIDService
{
    private const DID_PREFIX = 'did:finaegis:';

    private const DID_CACHE_TTL = 3600; // 1 hour

    public function generateDID(string $method = 'key'): string
    {
        $uniqueId = Str::uuid()->toString();
        $timestamp = now()->timestamp;
        $randomBytes = bin2hex(random_bytes(16));

        $identifier = hash('sha256', $uniqueId . $timestamp . $randomBytes);

        return self::DID_PREFIX . $method . ':' . substr($identifier, 0, 32);
    }

    public function resolveDID(string $did): ?array
    {
        // Check cache first
        $cacheKey = 'did:' . $did;
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // Parse DID
        if (! $this->validateDID($did)) {
            return null;
        }

        $parts = explode(':', $did);
        if (count($parts) < 4) {
            return null;
        }

        $didDocument = [
            '@context' => [
                'https://www.w3.org/ns/did/v1',
                'https://w3id.org/security/v1',
            ],
            'id'                 => $did,
            'verificationMethod' => [
                [
                    'id'                 => $did . '#keys-1',
                    'type'               => 'Ed25519VerificationKey2020',
                    'controller'         => $did,
                    'publicKeyMultibase' => $this->generatePublicKeyMultibase(),
                ],
            ],
            'authentication'  => [$did . '#keys-1'],
            'assertionMethod' => [$did . '#keys-1'],
            'service'         => [
                [
                    'id'              => $did . '#ap2',
                    'type'            => 'AP2Service',
                    'serviceEndpoint' => config('app.url') . '/api/agents',
                ],
            ],
            'created' => now()->toIso8601String(),
            'updated' => now()->toIso8601String(),
        ];

        // Cache the document
        Cache::put($cacheKey, $didDocument, self::DID_CACHE_TTL);

        return $didDocument;
    }

    public function validateDID(string $did): bool
    {
        // Basic validation - check format
        if (! str_starts_with($did, self::DID_PREFIX)) {
            return false;
        }

        // Check structure
        $parts = explode(':', $did);
        if (count($parts) < 4) {
            return false;
        }

        // Validate method
        $validMethods = ['key', 'web', 'agent'];
        if (! in_array($parts[2], $validMethods)) {
            return false;
        }

        // Validate identifier format (should be hex)
        $identifier = $parts[3];
        if (! preg_match('/^[a-f0-9]{32}$/', $identifier)) {
            return false;
        }

        return true;
    }

    public function createDIDDocument(array $attributes): array
    {
        $did = $attributes['did'] ?? $this->generateDID();

        return [
            '@context' => [
                'https://www.w3.org/ns/did/v1',
                'https://w3id.org/security/v1',
            ],
            'id'                   => $did,
            'verificationMethod'   => $attributes['verificationMethod'] ?? [],
            'authentication'       => $attributes['authentication'] ?? [],
            'assertionMethod'      => $attributes['assertionMethod'] ?? [],
            'keyAgreement'         => $attributes['keyAgreement'] ?? [],
            'capabilityInvocation' => $attributes['capabilityInvocation'] ?? [],
            'capabilityDelegation' => $attributes['capabilityDelegation'] ?? [],
            'service'              => $attributes['service'] ?? [],
            'created'              => $attributes['created'] ?? now()->toIso8601String(),
            'updated'              => now()->toIso8601String(),
            'proof'                => $attributes['proof'] ?? null,
        ];
    }

    public function storeDIDDocument(string $did, array $document): bool
    {
        $cacheKey = 'did:document:' . $did;
        Cache::put($cacheKey, $document, self::DID_CACHE_TTL * 24); // Cache for 24 hours

        // Store in database for persistence
        // This would be implemented with a proper DID registry

        return true;
    }

    public function verifyDIDSignature(string $did, string $signature, string $message): bool
    {
        $document = $this->resolveDID($did);
        if (! $document) {
            return false;
        }

        // In a real implementation, this would verify the signature
        // using the public key from the DID document
        // For now, we'll return a placeholder

        // TODO: Implement actual signature verification
        return true;
    }

    private function generatePublicKeyMultibase(): string
    {
        // Generate a dummy public key in multibase format
        // In production, this would be derived from actual cryptographic keys
        $publicKey = random_bytes(32);

        return 'z' . base58_encode($publicKey);
    }

    public function extractAgentIdFromDID(string $did): ?string
    {
        if (! $this->validateDID($did)) {
            return null;
        }

        $parts = explode(':', $did);

        return $parts[3] ?? null;
    }

    public function getDIDMethod(string $did): ?string
    {
        if (! $this->validateDID($did)) {
            return null;
        }

        $parts = explode(':', $did);

        return $parts[2] ?? null;
    }
}

// Helper function for base58 encoding (simplified version)
if (! function_exists('base58_encode')) {
    function base58_encode($data): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $encoded = '';
        $num = gmp_import($data);

        while (gmp_cmp($num, 0) > 0) {
            $remainder = gmp_mod($num, 58);
            $num = gmp_div($num, 58);
            $encoded = $alphabet[gmp_intval($remainder)] . $encoded;
        }

        // Add leading 1s for leading zeros
        for ($i = 0; isset($data[$i]) && $data[$i] === "\0"; $i++) {
            $encoded = '1' . $encoded;
        }

        return $encoded;
    }
}
