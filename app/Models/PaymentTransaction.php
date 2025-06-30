<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'aggregate_uuid',
        'account_uuid',
        'type',
        'status',
        'amount',
        'currency',
        'reference',
        'external_reference',
        'transaction_id',
        'payment_method',
        'payment_method_type',
        'bank_account_number',
        'bank_routing_number',
        'bank_account_name',
        'metadata',
        'initiated_at',
        'completed_at',
        'failed_at',
        'failed_reason',
    ];

    protected $casts = [
        'metadata' => 'array',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2) . ' ' . strtoupper($this->currency);
    }
}