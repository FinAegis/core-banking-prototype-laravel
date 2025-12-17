<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agent model for the agents table.
 *
 * @property int $id
 * @property string $agent_id
 * @property string $did
 * @property string $name
 * @property string $type
 * @property string $status
 * @property bool $is_suspended
 * @property string|null $suspension_reason
 * @property \Carbon\Carbon|null $suspended_at
 * @property bool $kyc_verified
 * @property string $kyc_status
 * @property string $kyc_verification_level
 * @property \Carbon\Carbon|null $kyc_verified_at
 * @property int $reputation_score
 * @property int $total_transactions
 * @property int $successful_transactions
 * @property float|null $single_transaction_limit
 * @property float|null $daily_limit
 * @property float|null $monthly_limit
 * @property string $risk_level
 * @property \Carbon\Carbon|null $last_security_audit
 * @property string|null $network_id
 * @property string|null $organization
 * @property array|null $endpoints
 * @property array|null $capabilities
 * @property array|null $metadata
 * @property float $relay_score
 * @property \Carbon\Carbon|null $last_activity_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'did',
        'name',
        'type',
        'status',
        'is_suspended',
        'suspension_reason',
        'suspended_at',
        'kyc_verified',
        'kyc_status',
        'kyc_verification_level',
        'kyc_verified_at',
        'reputation_score',
        'total_transactions',
        'successful_transactions',
        'single_transaction_limit',
        'daily_limit',
        'monthly_limit',
        'risk_level',
        'last_security_audit',
        'network_id',
        'organization',
        'endpoints',
        'capabilities',
        'metadata',
        'relay_score',
        'last_activity_at',
    ];

    protected $casts = [
        'is_suspended'             => 'boolean',
        'suspended_at'             => 'datetime',
        'kyc_verified'             => 'boolean',
        'kyc_verified_at'          => 'datetime',
        'last_security_audit'      => 'datetime',
        'last_activity_at'         => 'datetime',
        'endpoints'                => 'array',
        'capabilities'             => 'array',
        'metadata'                 => 'array',
        'single_transaction_limit' => 'decimal:2',
        'daily_limit'              => 'decimal:2',
        'monthly_limit'            => 'decimal:2',
        'relay_score'              => 'decimal:2',
        'reputation_score'         => 'integer',
        'total_transactions'       => 'integer',
        'successful_transactions'  => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class, 'agent_id', 'agent_id');
    }

    public function transactionTotals(): HasMany
    {
        return $this->hasMany(AgentTransactionTotal::class, 'agent_id', 'agent_id');
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_verified === true
            && $this->kyc_status === 'verified';
    }

    public function requiresKycRenewal(): bool
    {
        if ($this->kyc_status !== 'verified') {
            return true;
        }

        // If no verification date, renewal is needed
        if (! $this->kyc_verified_at) {
            return true;
        }

        // Require renewal if verified more than 11 months ago (30 days before 1 year expiry)
        return $this->kyc_verified_at->diffInMonths(now()) >= 11;
    }

    public function isHighRisk(): bool
    {
        return in_array($this->risk_level, ['high', 'critical'], true);
    }

    public function hasComplianceFlags(): bool
    {
        $metadata = $this->metadata ?? [];

        return ! empty($metadata['compliance_flags'] ?? []);
    }
}
