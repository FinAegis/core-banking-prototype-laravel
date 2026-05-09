<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Projection target written by ProjectRevenueOutbox (off-chain) and the
 * future chain-ingestor (on-chain). Idempotent on
 * (source_type, source_event_id, event_type).
 *
 * @property int                            $id
 * @property string                         $event_type
 * @property int|null                       $user_id
 * @property string|null                    $user_id_hash
 * @property string                         $source_type
 * @property string|null                    $source_event_id
 * @property string|null                    $aggregate_id
 * @property int                            $amount
 * @property int                            $amount_decimals
 * @property string                         $amount_denomination
 * @property array<string, mixed>|null      $payload
 * @property \Illuminate\Support\Carbon     $emitted_at
 */
final class RevenueEvent extends Model
{
    public const TYPE_SUBSCRIPTION_INITIAL = 'subscription_initial';

    public const TYPE_SUBSCRIPTION_RENEWAL = 'subscription_renewal';

    public const TYPE_REFUND = 'refund';

    protected $table = 'revenue_events';

    protected $fillable = [
        'event_type',
        'user_id',
        'user_id_hash',
        'source_type',
        'source_event_id',
        'aggregate_id',
        'amount',
        'amount_decimals',
        'amount_denomination',
        'payload',
        'emitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload'         => 'array',
            'amount'          => 'integer',
            'amount_decimals' => 'integer',
            'emitted_at'      => 'datetime',
        ];
    }
}
