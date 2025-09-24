<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $agent_id
 * @property string $agent_did
 * @property string $agent_name
 * @property string $agent_type
 * @property string $status
 * @property string $kyc_status
 * @property string $kyc_verification_level
 * @property \Carbon\Carbon|null $kyc_verified_at
 * @property \Carbon\Carbon|null $kyc_expires_at
 * @property int $risk_score
 * @property array $compliance_flags
 * @property int $reputation_score
 * @property int $total_transactions
 * @property float $total_transaction_volume
 * @property float $daily_transaction_limit
 * @property float $weekly_transaction_limit
 * @property float $monthly_transaction_limit
 * @property string $limit_currency
 * @property \Carbon\Carbon|null $limits_updated_at
 * @property string $country_code
 * @property array $metadata
 */
class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'agent_did',
        'agent_name',
        'agent_type',
        'status',
        'kyc_status',
        'kyc_verification_level',
        'kyc_verified_at',
        'kyc_expires_at',
        'risk_score',
        'compliance_flags',
        'reputation_score',
        'total_transactions',
        'total_transaction_volume',
        'daily_transaction_limit',
        'weekly_transaction_limit',
        'monthly_transaction_limit',
        'limit_currency',
        'limits_updated_at',
        'country_code',
        'metadata',
    ];

    protected $casts = [
        'kyc_verified_at'           => 'datetime',
        'kyc_expires_at'            => 'datetime',
        'limits_updated_at'         => 'datetime',
        'compliance_flags'          => 'array',
        'metadata'                  => 'array',
        'daily_transaction_limit'   => 'decimal:2',
        'weekly_transaction_limit'  => 'decimal:2',
        'monthly_transaction_limit' => 'decimal:2',
        'total_transaction_volume'  => 'decimal:2',
        'risk_score'                => 'integer',
        'reputation_score'          => 'integer',
        'total_transactions'        => 'integer',
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
        return $this->kyc_status === 'verified'
            && $this->kyc_expires_at
            && $this->kyc_expires_at->isFuture();
    }

    public function requiresKycRenewal(): bool
    {
        if (! $this->kyc_expires_at) {
            return true;
        }

        return $this->kyc_expires_at->diffInDays(now()) <= 30;
    }

    public function isHighRisk(): bool
    {
        return $this->risk_score >= 70;
    }

    public function hasComplianceFlags(): bool
    {
        return ! empty($this->compliance_flags);
    }
}
