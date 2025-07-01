<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionProjection extends Model
{
    use HasFactory, HasUuids;
    
    protected $table = 'transaction_projections';
    
    protected $guarded = [];
    
    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
    
    /**
     * Get the account that owns the transaction.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }
    
    /**
     * Get formatted amount (in dollars/cents format)
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100, 2);
    }
    
    /**
     * Scope to get transactions for a specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
    
    /**
     * Scope to get completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}