<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CgoInvestment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'round_id',
        'amount',
        'currency',
        'share_price',
        'shares_purchased',
        'ownership_percentage',
        'tier',
        'status',
        'payment_method',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'payment_status',
        'payment_completed_at',
        'payment_failed_at',
        'payment_failure_reason',
        'coinbase_charge_id',
        'coinbase_charge_code',
        'crypto_payment_url',
        'bank_transfer_reference',
        'crypto_address',
        'crypto_tx_hash',
        'crypto_transaction_hash',
        'crypto_amount_paid',
        'crypto_currency_paid',
        'amount_paid',
        'payment_pending_at',
        'failed_at',
        'failure_reason',
        'notes',
        'certificate_number',
        'certificate_issued_at',
        'agreement_path',
        'agreement_generated_at',
        'agreement_signed_at',
        'certificate_path',
        'cancelled_at',
        'metadata',
        'email',
        'kyc_verified_at',
        'kyc_level',
        'risk_assessment',
        'aml_checked_at',
        'aml_flags',
        'agreement_path',
        'agreement_generated_at',
        'agreement_signed_at',
        'certificate_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'crypto_amount_paid' => 'decimal:8',
        'share_price' => 'decimal:4',
        'shares_purchased' => 'decimal:4',
        'ownership_percentage' => 'decimal:6',
        'certificate_issued_at' => 'datetime',
        'agreement_generated_at' => 'datetime',
        'agreement_signed_at' => 'datetime',
        'payment_completed_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'payment_pending_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'kyc_verified_at' => 'datetime',
        'aml_checked_at' => 'datetime',
        'aml_flags' => 'array',
        'risk_assessment' => 'decimal:2',
        'agreement_generated_at' => 'datetime',
        'agreement_signed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(CgoPricingRound::class, 'round_id');
    }

    public function getTierColorAttribute(): string
    {
        return match($this->tier) {
            'bronze' => 'yellow',
            'silver' => 'gray',
            'gold' => 'amber',
            default => 'gray',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'confirmed' => 'green',
            'cancelled' => 'red',
            'refunded' => 'gray',
            default => 'gray',
        };
    }

    public function generateCertificateNumber(): string
    {
        return 'CGO-' . strtoupper($this->tier[0]) . '-' . date('Y') . '-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(CgoRefund::class, 'investment_id');
    }

    public function canBeRefunded(): bool
    {
        // Check if investment is in a refundable state
        if (!in_array($this->status, ['confirmed'])) {
            return false;
        }

        // Check if payment was completed
        if ($this->payment_status !== 'completed') {
            return false;
        }

        // Check if not already refunded
        if ($this->status === 'refunded') {
            return false;
        }

        // Check time limit (e.g., 90 days)
        if ($this->payment_completed_at && $this->payment_completed_at->diffInDays(now()) > 90) {
            return false;
        }

        return true;
    }

    public function getTotalRefundedAmount(): int
    {
        return $this->refunds()
            ->where('status', 'completed')
            ->sum('amount_refunded');
    }

    public function getRefundableAmount(): int
    {
        return $this->amount - $this->getTotalRefundedAmount();
    }

    public function hasActiveRefund(): bool
    {
        return $this->refunds()
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->exists();
    }
}
