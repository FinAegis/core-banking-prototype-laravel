<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan B Slice 5 — companion to CardWaitlist for the deposit lifecycle.
 *
 * Separate table (not ALTER on card_waitlist) for three reasons:
 *  1. Clean separation of concerns (free-tier join vs. deposit payment).
 *  2. Multiple deposit attempts per user across sessions.
 *  3. Audit trail of historical attempts (ADR-0002 §4.4 reconciliation).
 *
 * Lives on the default global connection. No UsesTenantConnection trait —
 * webhook writes are safe inside DB::transaction() alongside dedup rows.
 *
 * @property string                            $id
 * @property int                               $user_id
 * @property string                            $quote_id
 * @property string                            $status
 * @property int                               $deposit_amount_cents
 * @property int                               $deposit_decimals
 * @property string                            $deposit_currency
 * @property string|null                       $stripe_checkout_session_id
 * @property string|null                       $stripe_payment_intent_id
 * @property string|null                       $stripe_refund_id
 * @property \Illuminate\Support\Carbon|null   $refund_eligible_after
 * @property \Illuminate\Support\Carbon|null   $paid_at
 * @property \Illuminate\Support\Carbon|null   $cancelled_at
 * @property \Illuminate\Support\Carbon|null   $refunded_at
 * @property \Illuminate\Support\Carbon|null   $expired_at
 * @property \Illuminate\Support\Carbon|null   $shipped_at
 * @property string|null                       $refunded_reason
 * @property int|null                          $withdrawal_consent_log_id
 * @property \Illuminate\Support\Carbon|null   $created_at
 * @property \Illuminate\Support\Carbon|null   $updated_at
 */
final class CardWaitlistDeposit extends Model
{
    use HasUuids;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REFUND_PENDING_MANUAL = 'refund_pending_manual';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CARD_SHIPPED = 'card_shipped';

    public const REFUNDED_REASON_USER_CANCELLED = 'user_cancelled';

    public const REFUNDED_REASON_CARD_SHIPPED = 'card_shipped';

    public const REFUNDED_REASON_EIGHTEEN_MONTH_AUTO = 'eighteen_month_auto';

    public const REFUNDED_REASON_ACCOUNT_CLOSURE = 'account_closure';

    /** States where the user has an in-flight deposit (blocks new starts). */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_CANCELLATION_REQUESTED,
    ];

    /** States cancel may transition from (Q9.3 CHECK-constrained UPDATE). */
    public const CANCELLABLE_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
    ];

    protected $table = 'card_waitlist_deposits';

    protected $fillable = [
        'id',
        'user_id',
        'quote_id',
        'status',
        'deposit_amount_cents',
        'deposit_decimals',
        'deposit_currency',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_refund_id',
        'refund_eligible_after',
        'paid_at',
        'cancelled_at',
        'refunded_at',
        'expired_at',
        'shipped_at',
        'refunded_reason',
        'withdrawal_consent_log_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deposit_amount_cents'      => 'integer',
            'deposit_decimals'          => 'integer',
            'withdrawal_consent_log_id' => 'integer',
            'refund_eligible_after'     => 'datetime',
            'paid_at'                   => 'datetime',
            'cancelled_at'              => 'datetime',
            'refunded_at'               => 'datetime',
            'expired_at'                => 'datetime',
            'shipped_at'                => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
