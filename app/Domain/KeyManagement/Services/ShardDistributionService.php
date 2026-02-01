<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Services;

use App\Domain\KeyManagement\Enums\ShardStatus;
use App\Domain\KeyManagement\Enums\ShardType;
use App\Domain\KeyManagement\Events\KeyShardsCreated;
use App\Domain\KeyManagement\Events\KeyShardsRotated;
use App\Domain\KeyManagement\HSM\HsmIntegrationService;
use App\Domain\KeyManagement\Models\KeyShardRecord;
use App\Domain\KeyManagement\Models\RecoveryBackup;
use App\Domain\KeyManagement\ValueObjects\KeyShard;
use Exception;

class ShardDistributionService
{
    public function __construct(
        private readonly ShamirService $shamirService,
        private readonly HsmIntegrationService $hsm,
        private readonly EncryptionService $encryption
    ) {
    }

    /**
     * Create and distribute shards for a new wallet.
     *
     * @return array{device: string, auth_stored: bool, recovery_stored: bool, key_version: string}
     */
    public function createAndDistribute(string $privateKey, string $userUuid): array
    {
        $keyVersion = 'v' . time();

        // Split the key into shards
        $shards = $this->shamirService->splitKey($privateKey, $userUuid);

        // Store auth shard in HSM
        $authStored = $this->storeAuthShard($userUuid, $shards['auth'], $keyVersion);

        // Store recovery shard in database
        $recoveryStored = $this->storeRecoveryShard($userUuid, $shards['recovery'], $keyVersion);

        // Create shard records in database for tracking
        $this->createShardRecords($userUuid, $shards, $keyVersion, $privateKey);

        // Dispatch event
        event(new KeyShardsCreated($userUuid, $keyVersion));

        // Return device shard to be stored on user's device
        return [
            'device'          => $shards['device']->data,
            'auth_stored'     => $authStored,
            'recovery_stored' => $recoveryStored,
            'key_version'     => $keyVersion,
        ];
    }

    /**
     * Rotate key shards (create new shards for existing key).
     *
     * @return array{device: string, auth_stored: bool, recovery_stored: bool, key_version: string}
     */
    public function rotateShards(string $privateKey, string $userUuid, string $oldKeyVersion): array
    {
        // Mark old shards as rotated
        KeyShardRecord::query()
            ->forUser($userUuid)
            ->where('key_version', $oldKeyVersion)
            ->update(['status' => ShardStatus::ROTATED]);

        // Create new shards
        $result = $this->createAndDistribute($privateKey, $userUuid);

        // Dispatch rotation event
        event(new KeyShardsRotated($userUuid, $oldKeyVersion, $result['key_version']));

        return $result;
    }

    /**
     * Revoke all shards for a user (emergency key compromise).
     */
    public function revokeAllShards(string $userUuid): int
    {
        return KeyShardRecord::query()
            ->forUser($userUuid)
            ->active()
            ->update(['status' => ShardStatus::REVOKED]);
    }

    /**
     * Get active shards summary for a user.
     *
     * @return array<string, array{exists: bool, last_accessed: string|null}>
     */
    public function getShardsSummary(string $userUuid): array
    {
        $shards = KeyShardRecord::query()
            ->forUser($userUuid)
            ->active()
            ->get();

        $summary = [];
        foreach (ShardType::cases() as $type) {
            $shard = $shards->firstWhere('shard_type', $type);
            $summary[$type->value] = [
                'exists'        => $shard !== null,
                'last_accessed' => $shard?->last_accessed_at?->toIso8601String(),
            ];
        }

        return $summary;
    }

    /**
     * Verify recovery shard is valid.
     */
    public function verifyRecoveryShard(string $userUuid, string $recoveryShardData): bool
    {
        $backup = RecoveryBackup::query()
            ->forUser($userUuid)
            ->verified()
            ->latest()
            ->first();

        if (! $backup) {
            return false;
        }

        try {
            $decrypted = $this->encryption->decryptForUser($recoveryShardData, $userUuid);

            return $backup->verifyHash($decrypted);
        } catch (Exception) {
            return false;
        }
    }

    private function storeAuthShard(string $userUuid, KeyShard $shard, string $keyVersion): bool
    {
        $secretId = $this->getHsmSecretId($userUuid, $keyVersion);

        return $this->hsm->store($secretId, $shard->data);
    }

    private function storeRecoveryShard(string $userUuid, KeyShard $shard, string $keyVersion): bool
    {
        RecoveryBackup::create([
            'user_uuid'         => $userUuid,
            'encrypted_backup'  => $shard->data,
            'encryption_method' => 'aes-256-gcm',
            'key_version'       => $keyVersion,
            'backup_hash'       => hash('sha256', $shard->data),
            'is_verified'       => false,
            'usage_count'       => 0,
        ]);

        return true;
    }

    /**
     * @param array<string, KeyShard> $shards
     */
    private function createShardRecords(
        string $userUuid,
        array $shards,
        string $keyVersion,
        string $privateKey
    ): void {
        $publicKeyHash = hash('sha256', $privateKey);

        foreach ($shards as $type => $shard) {
            // Don't store device shard data - only metadata
            $encryptedData = $type === 'device'
                ? hash('sha256', $shard->data) // Just store hash for verification
                : $shard->data;

            KeyShardRecord::create([
                'user_uuid'       => $userUuid,
                'shard_type'      => $shard->type,
                'shard_index'     => $shard->index,
                'encrypted_data'  => $encryptedData,
                'encrypted_for'   => $shard->encryptedFor,
                'key_version'     => $keyVersion,
                'status'          => ShardStatus::ACTIVE,
                'public_key_hash' => $publicKeyHash,
            ]);
        }
    }

    private function getHsmSecretId(string $userUuid, string $keyVersion): string
    {
        return "auth-shard:{$userUuid}:{$keyVersion}";
    }
}
