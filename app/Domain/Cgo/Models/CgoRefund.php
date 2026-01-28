<?php

namespace App\Domain\Cgo\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values)
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static mixed sum(string $column)
 * @method static int count(string $columns = '*')
 * @method static static|null first()
 * @method static \Illuminate\Database\Eloquent\Collection get(array|string $columns = ['*'])
 */
class CgoRefund extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'investment_id',
        'user_id',
        'amount',
        'currency',
        'reason',
        'reason_details',
        'status',
        'initiated_by',
        'approved_by',
        'approval_notes',
        'approved_at',
        'rejected_by',
        'rejection_reason',
        'rejected_at',
        'payment_processor',
        'processor_refund_id',
        'processor_status',
        'processor_response',
        'processed_at',
        'processor_reference',
        'processing_notes',
        'amount_refunded',
        'completed_at',
        'failure_reason',
        'failed_at',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'requested_at',
        'refund_address',
        'bank_details',
        'metadata',
    ];

    protected $casts = [
        'amount'             => 'integer',
        'amount_refunded'    => 'integer',
        'processor_response' => 'array',
        'bank_details'       => 'array',
        'metadata'           => 'array',
        'requested_at'       => 'datetime',
        'approved_at'        => 'datetime',
        'rejected_at'        => 'datetime',
        'processed_at'       => 'datetime',
        'completed_at'       => 'datetime',
        'failed_at'          => 'datetime',
        'cancelled_at'       => 'datetime',
    ];

    /**
     * Get the investment associated with the refund.
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(CgoInvestment::class, 'investment_id');
    }

    /**
     * Get the user who owns the investment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the user who initiated the refund.
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'initiated_by');
    }

    /**
     * Get the user who approved the refund.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    /**
     * Get the user who rejected the refund.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'rejected_by');
    }

    /**
     * Get the user who cancelled the refund.
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'cancelled_by');
    }

    /**
     * Get status color for display.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'warning',
            'approved'   => 'primary',
            'rejected'   => 'danger',
            'processing' => 'info',
            'completed'  => 'success',
            'failed'     => 'danger',
            'cancelled'  => 'gray',
            default      => 'gray',
        };
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount / 100, 2);
    }

    /**
     * Status check methods.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if refund can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    /**
     * Check if refund can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this->isPending();
    }

    /**
     * Check if refund can be processed.
     */
    public function canBeProcessed(): bool
    {
        return $this->isApproved();
    }

    /**
     * Check if refund can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled']);
    }

    /**
     * Scope for pending refunds.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for processing refunds.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope for completed refunds.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
