<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Webhook redelivery dedup row, keyed on (provider, event_id).
 *
 * @property int                            $id
 * @property string                         $provider
 * @property string                         $event_id
 * @property string|null                    $event_type
 * @property \Illuminate\Support\Carbon     $processed_at
 */
final class ProcessedWebhookEvent extends Model
{
    protected $table = 'processed_webhook_events';

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
