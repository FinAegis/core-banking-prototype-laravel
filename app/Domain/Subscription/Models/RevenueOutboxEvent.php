<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Outbox row queued by webhook handlers; projected into revenue_events
 * by ProjectRevenueOutbox per ADR-0002.
 *
 * @property int                            $id
 * @property string                         $source_event_id
 * @property string                         $source_type
 * @property string                         $event_kind
 * @property array<string, mixed>           $payload
 * @property string                         $status   pending|delivered|failed
 * @property int                            $attempts
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property string|null                    $failed_reason
 */
final class RevenueOutboxEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_STRIPE = 'stripe';

    public const SOURCE_APPLE_IAP = 'apple_iap';

    public const SOURCE_GOOGLE_PLAY = 'google_play';

    public const SOURCE_STRIPE_BRIDGE = 'stripe_bridge';

    public const SOURCE_ONDATO = 'ondato';

    protected $table = 'revenue_outbox_events';

    protected $fillable = [
        'source_event_id',
        'source_type',
        'event_kind',
        'payload',
        'status',
        'attempts',
        'delivered_at',
        'failed_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'attempts'     => 'integer',
            'delivered_at' => 'datetime',
        ];
    }
}
