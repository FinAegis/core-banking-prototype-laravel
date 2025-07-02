<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StablecoinOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'type',
        'stablecoin',
        'amount',
        'collateral_asset',
        'collateral_amount',
        'collateral_return',
        'source_account',
        'recipient_account',
        'operator_uuid',
        'position_uuid',
        'reason',
        'status',
        'metadata',
        'executed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'collateral_amount' => 'integer',
        'collateral_return' => 'integer',
        'metadata' => 'array',
        'executed_at' => 'datetime',
    ];

    /**
     * Get the operator user
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_uuid', 'uuid');
    }

    /**
     * Get the source account
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account', 'uuid');
    }

    /**
     * Get the recipient account
     */
    public function recipientAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'recipient_account', 'uuid');
    }
}