<?php

declare(strict_types=1);

namespace App\Domain\Banking\Models;

use Carbon\Carbon;

class BankConnection
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $bankCode,
        public readonly string $status,
        public readonly array $credentials,
        public readonly array $permissions,
        public readonly ?Carbon $lastSyncAt,
        public readonly ?Carbon $expiresAt,
        public readonly Carbon $createdAt,
        public readonly Carbon $updatedAt,
        public readonly array $metadata = [],
    ) {}
    
    /**
     * Check if connection is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               ($this->expiresAt === null || $this->expiresAt->isFuture());
    }
    
    /**
     * Check if connection needs renewal
     */
    public function needsRenewal(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        
        return $this->expiresAt->diffInDays(now()) <= 7;
    }
    
    /**
     * Check if connection has permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions) || 
               in_array('*', $this->permissions);
    }
    
    /**
     * Check if sync is needed
     */
    public function needsSync(): bool
    {
        if ($this->lastSyncAt === null) {
            return true;
        }
        
        $syncInterval = $this->metadata['sync_interval'] ?? 3600; // Default 1 hour
        return $this->lastSyncAt->diffInSeconds(now()) >= $syncInterval;
    }
    
    /**
     * Get masked credentials for display
     */
    public function getMaskedCredentials(): array
    {
        $masked = [];
        foreach ($this->credentials as $key => $value) {
            if (in_array($key, ['username', 'account_id', 'customer_id'])) {
                $masked[$key] = $value;
            } else {
                $masked[$key] = str_repeat('*', 8);
            }
        }
        return $masked;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'bank_code' => $this->bankCode,
            'status' => $this->status,
            'credentials' => $this->credentials,
            'permissions' => $this->permissions,
            'last_sync_at' => $this->lastSyncAt?->toIso8601String(),
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            userId: $data['user_id'],
            bankCode: $data['bank_code'],
            status: $data['status'],
            credentials: $data['credentials'] ?? [],
            permissions: $data['permissions'] ?? [],
            lastSyncAt: isset($data['last_sync_at']) ? Carbon::parse($data['last_sync_at']) : null,
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at']),
            metadata: $data['metadata'] ?? [],
        );
    }
}