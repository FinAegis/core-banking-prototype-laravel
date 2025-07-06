<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanCollateral extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'loan_id',
        'type',
        'description',
        'estimated_value',
        'currency',
        'status',
        'verification_document_id',
        'verified_at',
        'verified_by',
        'released_at',
        'liquidated_at',
        'liquidation_value',
        'last_valuation_date',
        'valuation_history',
        'metadata'
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'liquidation_value' => 'decimal:2',
        'verified_at' => 'datetime',
        'released_at' => 'datetime',
        'liquidated_at' => 'datetime',
        'last_valuation_date' => 'datetime',
        'valuation_history' => 'array',
        'metadata' => 'array'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending_verification' => 'yellow',
            'verified' => 'green',
            'rejected' => 'red',
            'released' => 'blue',
            'liquidated' => 'gray',
            default => 'gray'
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }
}