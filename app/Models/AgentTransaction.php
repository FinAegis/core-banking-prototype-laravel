<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $transaction_id
 * @property string $agent_id
 * @property string $counterparty_agent_id
 * @property string $transaction_type
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property string|null $description
 * @property array|null $metadata
 * @property bool $is_flagged
 * @property string|null $flag_reason
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AgentTransaction extends Model
{
    use HasFactory;

    protected $table = 'agent_transactions';

    protected $fillable = [
        'transaction_id',
        'agent_id',
        'counterparty_agent_id',
        'transaction_type',
        'amount',
        'currency',
        'status',
        'description',
        'metadata',
        'is_flagged',
        'flag_reason',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'metadata'    => 'array',
        'is_flagged'  => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Transaction types.
     */
    public const TYPE_PAYMENT = 'payment';

    public const TYPE_ESCROW = 'escrow';

    public const TYPE_REFUND = 'refund';

    public const TYPE_FEE = 'fee';

    public const TYPE_SETTLEMENT = 'settlement';

    /**
     * Transaction statuses.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REVERSED = 'reversed';

    /**
     * Get the agent that owns the transaction.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Get the counterparty agent.
     */
    public function counterpartyAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'counterparty_agent_id', 'agent_id');
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction is flagged.
     */
    public function isFlagged(): bool
    {
        return $this->is_flagged;
    }

    /**
     * Flag transaction for review.
     */
    public function flag(string $reason): void
    {
        $this->update([
            'is_flagged'  => true,
            'flag_reason' => $reason,
        ]);
    }

    /**
     * Mark transaction as reviewed.
     */
    public function markAsReviewed(string $reviewedBy): void
    {
        $this->update([
            'is_flagged'  => false,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
        ]);
    }

    /**
     * Check if transaction exceeds threshold.
     */
    public function exceedsThreshold(float $threshold): bool
    {
        return $this->amount >= $threshold;
    }

    /**
     * Scope for flagged transactions.
     */
    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    /**
     * Scope for completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for transactions above amount.
     */
    public function scopeAboveAmount($query, float $amount)
    {
        return $query->where('amount', '>=', $amount);
    }

    /**
     * Scope for date range.
     */
    public function scopeInDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmount(): string
    {
        return sprintf('%s %.2f', $this->currency, $this->amount);
    }
}
