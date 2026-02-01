<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Services;

use App\Domain\KeyManagement\Enums\ShardType;
use App\Domain\KeyManagement\Events\KeyReconstructed;
use App\Domain\KeyManagement\Events\KeyReconstructionFailed;
use App\Domain\KeyManagement\Models\KeyReconstructionLog;
use App\Domain\KeyManagement\Models\KeyShardRecord;
use App\Domain\KeyManagement\ValueObjects\KeyShard;
use App\Domain\KeyManagement\ValueObjects\ReconstructedKey;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;

class KeyReconstructionService
{
    public function __construct(
        private readonly ShamirService $shamirService,
        private readonly ?Request $request = null
    ) {
    }

    /**
     * Reconstruct a key using device shard + auth shard.
     */
    public function reconstructWithAuth(
        string $userUuid,
        string $deviceShardData,
        string $sessionToken
    ): ReconstructedKey {
        // Get the auth shard from database
        $authShardRecord = KeyShardRecord::query()
            ->forUser($userUuid)
            ->ofType(ShardType::AUTH)
            ->active()
            ->firstOrFail();

        // Create KeyShard objects
        $deviceShard = new KeyShard(
            type: ShardType::DEVICE,
            data: $deviceShardData,
            encryptedFor: 'device-enclave',
            userId: $userUuid,
            index: 1
        );

        $authShard = new KeyShard(
            type: ShardType::AUTH,
            data: $authShardRecord->encrypted_data,
            encryptedFor: 'hsm',
            userId: $userUuid,
            index: 2
        );

        return $this->reconstruct(
            $userUuid,
            [$deviceShard, $authShard],
            'transaction_signing'
        );
    }

    /**
     * Reconstruct a key using device shard + recovery shard.
     */
    public function reconstructWithRecovery(
        string $userUuid,
        string $deviceShardData,
        string $recoveryShardData
    ): ReconstructedKey {
        $deviceShard = new KeyShard(
            type: ShardType::DEVICE,
            data: $deviceShardData,
            encryptedFor: 'device-enclave',
            userId: $userUuid,
            index: 1
        );

        $recoveryShard = new KeyShard(
            type: ShardType::RECOVERY,
            data: $recoveryShardData,
            encryptedFor: 'user-cloud',
            userId: $userUuid,
            index: 3
        );

        return $this->reconstruct(
            $userUuid,
            [$deviceShard, $recoveryShard],
            'device_recovery'
        );
    }

    /**
     * Core reconstruction method.
     *
     * @param array<KeyShard> $shards
     */
    public function reconstruct(
        string $userUuid,
        array $shards,
        string $purpose
    ): ReconstructedKey {
        $shardTypes = array_map(fn ($s) => $s->type->value, $shards);

        try {
            if (count($shards) !== 2) {
                throw new RuntimeException('Exactly 2 shards required for reconstruction');
            }

            // Reconstruct the key
            $reconstructedKey = $this->shamirService->reconstructKey($shards[0], $shards[1]);

            // Log successful reconstruction
            $this->logReconstruction($userUuid, $shardTypes, $purpose, true);

            // Mark auth shard as accessed if used
            $this->markShardsAccessed($userUuid, $shardTypes);

            // Dispatch success event
            event(new KeyReconstructed($userUuid, $purpose, $shardTypes));

            return $reconstructedKey;
        } catch (Exception $e) {
            // Log failed reconstruction
            $this->logReconstruction($userUuid, $shardTypes, $purpose, false, $e->getMessage());

            // Dispatch failure event
            event(new KeyReconstructionFailed($userUuid, $purpose, $e->getMessage()));

            throw $e;
        }
    }

    /**
     * Check if reconstruction is allowed (rate limiting).
     */
    public function canReconstruct(string $userUuid, int $maxAttemptsPerHour = 10): bool
    {
        $recentAttempts = KeyReconstructionLog::query()
            ->forUser($userUuid)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentAttempts < $maxAttemptsPerHour;
    }

    /**
     * Get recent reconstruction logs for a user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, KeyReconstructionLog>
     */
    public function getRecentLogs(string $userUuid, int $limit = 10)
    {
        return KeyReconstructionLog::query()
            ->forUser($userUuid)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param array<string> $shardTypes
     */
    private function logReconstruction(
        string $userUuid,
        array $shardTypes,
        string $purpose,
        bool $success,
        ?string $failureReason = null
    ): void {
        // Get user's current key version
        $keyVersion = KeyShardRecord::query()
            ->forUser($userUuid)
            ->active()
            ->value('key_version') ?? 'v1';

        KeyReconstructionLog::create([
            'user_uuid'      => $userUuid,
            'key_version'    => $keyVersion,
            'shards_used'    => $shardTypes,
            'purpose'        => $purpose,
            'ip_address'     => $this->request?->ip() ?? 'unknown',
            'user_agent'     => $this->request?->userAgent(),
            'device_id'      => $this->request?->header('X-Device-Id'),
            'success'        => $success,
            'failure_reason' => $failureReason,
        ]);
    }

    /**
     * @param array<string> $shardTypes
     */
    private function markShardsAccessed(string $userUuid, array $shardTypes): void
    {
        foreach ($shardTypes as $type) {
            $shardType = ShardType::from($type);

            // Only mark database-stored shards
            if ($shardType !== ShardType::DEVICE) {
                KeyShardRecord::query()
                    ->forUser($userUuid)
                    ->ofType($shardType)
                    ->active()
                    ->update(['last_accessed_at' => now()]);
            }
        }
    }
}
