<?php

/**
 * IapSubscriptionEvent — append-only event store for the IAP custom path.
 *
 * Backed by `iap_subscription_events`. One row per state-changing event
 * (initial purchase, renewal, lapse, refund, etc). Spatie-style schema —
 * unique on (aggregate_uuid, aggregate_version) so concurrent appenders
 * fail loudly instead of silently corrupting the stream.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.2
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string                    $id
 * @property string                    $aggregate_uuid
 * @property int                       $aggregate_version
 * @property string                    $event_class
 * @property array<string, mixed>      $event_payload
 * @property array<string, mixed>      $metadata
 * @property \Illuminate\Support\Carbon $created_at
 */
final class IapSubscriptionEvent extends Model
{
    use HasUuids;

    protected $table = 'iap_subscription_events';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'aggregate_uuid',
        'aggregate_version',
        'event_class',
        'event_payload',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_payload'     => 'array',
            'metadata'          => 'array',
            'aggregate_version' => 'integer',
            'created_at'        => 'datetime',
        ];
    }
}
