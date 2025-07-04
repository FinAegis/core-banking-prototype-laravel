<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'application_id',
        'borrower_id',
        'principal',
        'interest_rate',
        'term_months',
        'repayment_schedule',
        'terms',
        'status',
        'investor_ids',
        'funded_amount',
        'funded_at',
        'disbursed_amount',
        'disbursed_at',
        'total_principal_paid',
        'total_interest_paid',
        'last_payment_date',
        'missed_payments',
        'settlement_amount',
        'settled_at',
        'settled_by',
        'defaulted_at',
        'completed_at',
    ];

    protected $casts = [
        'repayment_schedule' => 'array',
        'terms' => 'array',
        'investor_ids' => 'array',
        'principal' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'funded_amount' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'total_principal_paid' => 'decimal:2',
        'total_interest_paid' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'funded_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'last_payment_date' => 'datetime',
        'settled_at' => 'datetime',
        'defaulted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'application_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFunded($query)
    {
        return $query->where('status', 'funded');
    }

    public function scopeDelinquent($query)
    {
        return $query->where('status', 'delinquent');
    }

    public function scopeDefaulted($query)
    {
        return $query->where('status', 'defaulted');
    }

    public function getOutstandingBalanceAttribute()
    {
        return bcsub($this->principal, $this->total_principal_paid, 2);
    }

    public function getNextPaymentAttribute()
    {
        $lastPaymentNumber = $this->repayments()->max('payment_number') ?? 0;
        $schedule = collect($this->repayment_schedule);
        
        return $schedule->firstWhere('payment_number', $lastPaymentNumber + 1);
    }
}