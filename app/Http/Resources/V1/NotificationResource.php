<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Mobile\Models\MobilePushNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * Simplified type category mapping.
     *
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'transaction.received'      => 'transaction',
        'transaction.sent'          => 'transaction',
        'transaction.failed'        => 'transaction',
        'balance.low'               => 'transaction',
        'security.login'            => 'security',
        'security.device_added'     => 'security',
        'security.password_changed' => 'security',
        'kyc.status_changed'        => 'system',
        'system.maintenance'        => 'system',
        'system.update'             => 'system',
        'price.alert'               => 'transaction',
        'general'                   => 'system',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->mapType($this->notification_type),
            'title'      => $this->title,
            'body'       => $this->body,
            'data'       => $this->data,
            'read'       => $this->read_at !== null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Map raw notification_type to simplified category.
     */
    private function mapType(string $rawType): string
    {
        if (isset(self::TYPE_MAP[$rawType])) {
            return self::TYPE_MAP[$rawType];
        }

        // promo.* and marketing.* → promo
        if (str_starts_with($rawType, 'promo.') || str_starts_with($rawType, 'marketing.')) {
            return 'promo';
        }

        // security.* → security
        if (str_starts_with($rawType, 'security.')) {
            return 'security';
        }

        // transaction.* → transaction
        if (str_starts_with($rawType, 'transaction.')) {
            return 'transaction';
        }

        // system.* → system
        if (str_starts_with($rawType, 'system.')) {
            return 'system';
        }

        return 'system';
    }
}
