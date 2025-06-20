<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * CustodianAccount Model
 * 
 * Represents the mapping between internal accounts and external custodian accounts.
 * This enables multi-custodian support where a single internal account can have
 * multiple external accounts across different custodians.
 */
class CustodianAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'account_uuid',
        'custodian_id',
        'external_account_id',
        'status',
        'is_primary',
        'last_synced_at',
        'sync_status',
        'sync_error',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Sync status constants
     */
    public const SYNC_STATUS_SUCCESS = 'success';
    public const SYNC_STATUS_FAILED = 'failed';
    public const SYNC_STATUS_PENDING = 'pending';

    /**
     * Get the internal account
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }

    /**
     * Scope a query to only include active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include primary accounts
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to only include accounts for a specific custodian
     */
    public function scopeForCustodian($query, string $custodianId)
    {
        return $query->where('custodian_id', $custodianId);
    }

    /**
     * Scope a query to only include accounts that need synchronization
     */
    public function scopeNeedsSynchronization($query, int $minutesSinceLastSync = 5)
    {
        return $query->where(function ($q) use ($minutesSinceLastSync) {
            $q->whereNull('last_synced_at')
              ->orWhere('last_synced_at', '<', now()->subMinutes($minutesSinceLastSync));
        });
    }

    /**
     * Check if the account is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if synchronization has failed
     */
    public function hasSyncFailed(): bool
    {
        return $this->sync_status === self::SYNC_STATUS_FAILED;
    }

    /**
     * Check if the account needs synchronization
     */
    public function needsSynchronization(int $minutesSinceLastSync = 5): bool
    {
        if (!$this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->isBefore(now()->subMinutes($minutesSinceLastSync));
    }

    /**
     * Get the time since last synchronization
     */
    public function getTimeSinceLastSync(): ?string
    {
        if (!$this->last_synced_at) {
            return null;
        }

        return $this->last_synced_at->diffForHumans();
    }

    /**
     * Mark as synchronized
     */
    public function markAsSynchronized(): void
    {
        $this->update([
            'last_synced_at' => now(),
            'sync_status' => self::SYNC_STATUS_SUCCESS,
            'sync_error' => null,
        ]);
    }

    /**
     * Mark synchronization as failed
     */
    public function markSyncAsFailed(string $error): void
    {
        $this->update([
            'last_synced_at' => now(),
            'sync_status' => self::SYNC_STATUS_FAILED,
            'sync_error' => $error,
        ]);
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;
        $this->save();
    }
}