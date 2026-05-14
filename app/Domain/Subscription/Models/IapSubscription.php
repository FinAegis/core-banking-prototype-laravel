<?php

/**
 * IapSubscription — Plan B Slice 2 IAP read-model.
 *
 * Non-Cashier subscription row managed by the custom IAP path (Backend-Q1 γ).
 * One row per active Apple/Google subscription; the unified
 * SubscriptionProjection joins against this for cross-store entitlement.
 *
 * `id` is a UUID (RFC 4122 v4 — generated via HasUuids), `user_id` is the
 * BIGINT FK to users per project convention. The `active_user_id` STORED
 * generated column enforces the one-active-sub invariant in MySQL/MariaDB.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string                          $id
 * @property int                             $user_id
 * @property string                          $store               apple|google
 * @property string                          $tier
 * @property string                          $status              one of ALIVE_STATUSES, cancelled, expired, refunded
 * @property string|null                     $original_transaction_id
 * @property string|null                     $play_subscription_resource_id
 * @property string|null                     $google_purchase_token_hash
 * @property string|null                     $apple_app_account_token
 * @property string|null                     $google_obfuscated_account_id
 * @property \Illuminate\Support\Carbon|null $trial_started_at
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $current_period_starts_at
 * @property \Illuminate\Support\Carbon|null $current_period_ends_at
 * @property bool                            $cancel_at_period_end
 * @property \Illuminate\Support\Carbon|null $paused_at
 * @property \Illuminate\Support\Carbon|null $paused_until
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $expired_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property string|null                     $last_notification_type
 * @property string|null                     $last_event_id
 */
final class IapSubscription extends Model
{
    use HasUuids;

    public const STORE_APPLE = 'apple';

    public const STORE_GOOGLE = 'google';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_GRACE_PERIOD = 'grace_period';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    /**
     * "Alive" statuses where the user retains Pro entitlement.
     *
     * @var array<int, string>
     */
    public const ALIVE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_TRIALING,
        self::STATUS_PAST_DUE,
        self::STATUS_GRACE_PERIOD,
        self::STATUS_PAUSED,
    ];

    protected $table = 'iap_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'store',
        'tier',
        'status',
        'original_transaction_id',
        'play_subscription_resource_id',
        'google_purchase_token_hash',
        'apple_app_account_token',
        'google_obfuscated_account_id',
        'trial_started_at',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'cancel_at_period_end',
        'paused_at',
        'paused_until',
        'cancelled_at',
        'expired_at',
        'refunded_at',
        'last_notification_type',
        'last_event_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancel_at_period_end'     => 'boolean',
            'trial_started_at'         => 'datetime',
            'trial_ends_at'            => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at'   => 'datetime',
            'paused_at'                => 'datetime',
            'paused_until'             => 'datetime',
            'cancelled_at'             => 'datetime',
            'expired_at'               => 'datetime',
            'refunded_at'              => 'datetime',
        ];
    }
}
