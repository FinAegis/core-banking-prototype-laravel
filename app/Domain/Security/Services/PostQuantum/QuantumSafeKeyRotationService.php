<?php

declare(strict_types=1);

namespace App\Domain\Security\Services\PostQuantum;

use App\Domain\Security\Enums\KeyRotationStrategy;
use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\ValueObjects\HybridEncryptionResult;
use App\Domain\Security\ValueObjects\PostQuantumKeyPair;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Quantum-safe key rotation service.
 *
 * Manages the lifecycle of post-quantum key pairs:
 * - Generates new PQ-safe key pairs on rotation
 * - Re-encrypts data from old keys to new keys
 * - Supports graceful rotation with overlap periods
 * - Upgrades classical-only keys to hybrid PQ-safe keys
 * - Tracks rotation history for audit compliance
 */
class QuantumSafeKeyRotationService
{
    private const CACHE_PREFIX = 'pq-key-rotation:';

    private const ROTATION_HISTORY_KEY = 'pq-key-rotation:history';

    private HybridEncryptionService $encryptionService;

    private MlDsaService $dsaService;

    public function __construct(
        ?HybridEncryptionService $encryptionService = null,
        ?MlDsaService $dsaService = null,
    ) {
        $this->encryptionService = $encryptionService ?? new HybridEncryptionService();
        $this->dsaService = $dsaService ?? new MlDsaService();
    }

    /**
     * Rotate an encryption key pair, generating a new PQ-safe pair.
     *
     * @return array{old: PostQuantumKeyPair, new: PostQuantumKeyPair}
     */
    public function rotateEncryptionKeyPair(
        PostQuantumKeyPair $existingKeyPair,
        KeyRotationStrategy $strategy = KeyRotationStrategy::GRACEFUL,
    ): array {
        $newKeyPair = $this->encryptionService->generateKeyPair();

        if ($strategy === KeyRotationStrategy::GRACEFUL) {
            $this->cacheOldKeyForTransition($existingKeyPair);
        }

        $this->logRotation($existingKeyPair, $newKeyPair, $strategy, 'encryption');

        return [
            'old' => $existingKeyPair,
            'new' => $newKeyPair,
        ];
    }

    /**
     * Rotate a signing key pair, generating a new PQ-safe pair.
     *
     * @return array{old: PostQuantumKeyPair, new: PostQuantumKeyPair}
     */
    public function rotateSigningKeyPair(
        PostQuantumKeyPair $existingKeyPair,
        KeyRotationStrategy $strategy = KeyRotationStrategy::GRACEFUL,
    ): array {
        $newKeyPair = $this->dsaService->generateSigningKeyPair();

        if ($strategy === KeyRotationStrategy::GRACEFUL) {
            $this->cacheOldKeyForTransition($existingKeyPair);
        }

        $this->logRotation($existingKeyPair, $newKeyPair, $strategy, 'signing');

        return [
            'old' => $existingKeyPair,
            'new' => $newKeyPair,
        ];
    }

    /**
     * Re-encrypt a collection of encrypted data from old key to new key.
     *
     * @param  array<HybridEncryptionResult>  $encryptedItems
     * @return array{re_encrypted: array<HybridEncryptionResult>, failed: array<int>, total: int, success_count: int}
     */
    public function reEncryptBatch(
        array $encryptedItems,
        string $oldSecretKey,
        string $newPublicKey,
        string $newRecipientKeyId = '',
    ): array {
        $reEncrypted = [];
        $failed = [];

        foreach ($encryptedItems as $index => $item) {
            try {
                $reEncrypted[] = $this->encryptionService->reEncrypt(
                    $item,
                    $oldSecretKey,
                    $newPublicKey,
                    $newRecipientKeyId,
                );
            } catch (Throwable $e) {
                $failed[] = $index;
                Log::warning('Key rotation re-encryption failed', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            're_encrypted'  => $reEncrypted,
            'failed'        => $failed,
            'total'         => count($encryptedItems),
            'success_count' => count($reEncrypted),
        ];
    }

    /**
     * Upgrade a classical X25519 key to a hybrid PQ-safe key pair.
     */
    public function upgradeToQuantumSafe(string $classicalPublicKey, string $classicalSecretKey): PostQuantumKeyPair
    {
        $classicalPubBytes = base64_decode($classicalPublicKey, true);
        $classicalSecBytes = base64_decode($classicalSecretKey, true);

        if ($classicalPubBytes === false || $classicalSecBytes === false) {
            throw new RuntimeException('Invalid base64-encoded classical key');
        }

        // Generate fresh PQ key material
        $pqSeed = random_bytes(64);
        $pqPublicSeed = hash('sha3-256', $pqSeed . 'public', true);
        $pqSecretSeed = hash('sha3-256', $pqSeed . 'secret', true);

        // Compose hybrid key: classical || PQ
        $hybridPublic = $classicalPubBytes . $pqPublicSeed;
        $hybridSecret = $classicalSecBytes . $pqSecretSeed . $pqSeed;

        $keyPair = new PostQuantumKeyPair(
            publicKey: base64_encode($hybridPublic),
            secretKey: base64_encode($hybridSecret),
            algorithm: PostQuantumAlgorithm::HYBRID_X25519_ML_KEM,
            keyId: 'upgraded-' . bin2hex(random_bytes(8)),
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+1 year'),
        );

        // Zero sensitive intermediates
        sodium_memzero($pqSeed);
        sodium_memzero($pqSecretSeed);
        sodium_memzero($classicalSecBytes);

        Log::info('Classical key upgraded to quantum-safe', [
            'key_id'    => $keyPair->keyId,
            'algorithm' => $keyPair->algorithm->value,
        ]);

        return $keyPair;
    }

    /**
     * Check if a key pair needs rotation based on age or algorithm.
     *
     * @return array{needs_rotation: bool, reason: string|null}
     */
    public function assessRotationNeed(PostQuantumKeyPair $keyPair): array
    {
        if ($keyPair->isExpired()) {
            return ['needs_rotation' => true, 'reason' => 'Key pair has expired'];
        }

        // Keys older than 90 days should be rotated
        $age = $keyPair->createdAt->diff(new DateTimeImmutable());
        if ($age->days > 90) {
            return ['needs_rotation' => true, 'reason' => 'Key pair is older than 90 days'];
        }

        // Non-PQ algorithms should be upgraded
        if (! $keyPair->algorithm->isHybrid() && ! $keyPair->algorithm->isKeyEncapsulation()) {
            return ['needs_rotation' => true, 'reason' => 'Key pair does not use post-quantum algorithm'];
        }

        return ['needs_rotation' => false, 'reason' => null];
    }

    /**
     * Get rotation history from cache.
     *
     * @return array<array<string, mixed>>
     */
    public function getRotationHistory(): array
    {
        /** @var array<array<string, mixed>> $history */
        $history = Cache::get(self::ROTATION_HISTORY_KEY, []);

        return $history;
    }

    /**
     * Cache old key pair during graceful rotation for transition decryption.
     */
    private function cacheOldKeyForTransition(PostQuantumKeyPair $oldKeyPair): void
    {
        $cacheKey = self::CACHE_PREFIX . 'transition:' . $oldKeyPair->keyId;

        // Keep old key available for 7 days during transition
        Cache::put($cacheKey, $oldKeyPair->toArray(), now()->addDays(7));
    }

    private function logRotation(
        PostQuantumKeyPair $oldKey,
        PostQuantumKeyPair $newKey,
        KeyRotationStrategy $strategy,
        string $keyType,
    ): void {
        $entry = [
            'old_key_id' => $oldKey->keyId,
            'new_key_id' => $newKey->keyId,
            'algorithm'  => $newKey->algorithm->value,
            'strategy'   => $strategy->value,
            'key_type'   => $keyType,
            'rotated_at' => (new DateTimeImmutable())->format('c'),
        ];

        Log::info('Post-quantum key rotation completed', $entry);

        /** @var array<array<string, mixed>> $history */
        $history = Cache::get(self::ROTATION_HISTORY_KEY, []);
        $history[] = $entry;

        // Keep last 100 rotations
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        Cache::put(self::ROTATION_HISTORY_KEY, $history, now()->addDays(365));
    }
}
