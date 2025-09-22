<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agent payment read model.
 *
 * @property int $id
 * @property int|null $parent_payment_id
 * @property string $transaction_id
 * @property string $payment_id
 * @property string $from_agent_did
 * @property string $to_agent_did
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property string $payment_type
 * @property float $fees
 * @property string|null $escrow_id
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $failed_at
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AgentPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_payment_id',
        'transaction_id',
        'payment_id',
        'from_agent_did',
        'to_agent_did',
        'amount',
        'currency',
        'status',
        'payment_type',
        'fees',
        'escrow_id',
        'metadata',
        'completed_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'amount'       => 'float',
        'fees'         => 'float',
        'metadata'     => 'array',
        'completed_at' => 'datetime',
        'failed_at'    => 'datetime',
    ];

    /**
     * Get the parent payment if this is a split payment.
     */
    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_payment_id');
    }

    /**
     * Get split payments for this payment.
     */
    public function splitPayments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_payment_id');
    }

    /**
     * Check if payment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if payment is in escrow.
     */
    public function hasEscrow(): bool
    {
        return $this->escrow_id !== null;
    }

    /**
     * Check if this is a split payment.
     */
    public function isSplitPayment(): bool
    {
        return $this->parent_payment_id !== null;
    }

    /**
     * Get total amount including fees.
     */
    public function getTotalAmount(): float
    {
        return $this->amount + $this->fees;
    }
}
