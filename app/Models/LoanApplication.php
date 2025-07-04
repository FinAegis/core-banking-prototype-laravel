<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanApplication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'borrower_id',
        'requested_amount',
        'term_months',
        'purpose',
        'status',
        'borrower_info',
        'credit_score',
        'credit_bureau',
        'credit_report',
        'credit_checked_at',
        'risk_rating',
        'default_probability',
        'risk_factors',
        'risk_assessed_at',
        'approved_amount',
        'interest_rate',
        'terms',
        'approved_by',
        'approved_at',
        'rejection_reasons',
        'rejected_by',
        'rejected_at',
        'submitted_at',
    ];

    protected $casts = [
        'borrower_info' => 'array',
        'credit_report' => 'array',
        'risk_factors' => 'array',
        'terms' => 'array',
        'rejection_reasons' => 'array',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'default_probability' => 'decimal:4',
        'credit_checked_at' => 'datetime',
        'risk_assessed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class, 'application_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['submitted', 'credit_checked', 'risk_assessed']);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}