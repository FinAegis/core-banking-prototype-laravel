<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
    use HasFactory, HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'account_uuid',
        'custodian_name',
        'custodian_account_id',
        'custodian_account_name',
        'status',
        'is_primary',
        'metadata',
        'last_known_balance',
        'last_synced_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_PENDING = 'pending';

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
    public function scopeForCustodian($query, string $custodianName)
    {
        return $query->where('custodian_name', $custodianName);
    }


    /**
     * Check if the account is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Set this account as primary
     */
    public function setAsPrimary(): void
    {
        // Remove primary from other accounts
        self::where('account_uuid', $this->account_uuid)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);
        
        // Set this as primary
        $this->update(['is_primary' => true]);
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