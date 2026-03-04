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
     * Subtype extraction from raw notification_type (part after the dot).
     */
    private const SUBTYPE_ICONS = [
        'received'         => 'arrow_down',
        'sent'             => 'arrow_up',
        'failed'           => 'alert_circle',
        'low'              => 'trending_down',
        'login'            => 'shield',
        'device_added'     => 'smartphone',
        'password_changed' => 'lock',
        'status_changed'   => 'badge_check',
        'maintenance'      => 'wrench',
        'update'           => 'refresh',
        'alert'            => 'bell',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rawType = $this->notification_type;
        $category = $this->mapType($rawType);
        $subtype = str_contains($rawType, '.') ? substr($rawType, strrpos($rawType, '.') + 1) : null;

        return [
            'id'         => $this->id,
            'type'       => $category,
            'subtype'    => $subtype,
            'icon'       => self::SUBTYPE_ICONS[$subtype] ?? 'bell',
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
