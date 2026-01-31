<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Events;

use App\Broadcasting\TenantBroadcastEvent;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class MobileDeviceBlocked extends ShouldBeStored implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use TenantBroadcastEvent;

    public static string $queue = 'events';

    public function __construct(
        public readonly ?string $tenantId,
        public readonly string $deviceId,
        public readonly string $userId,
        public readonly string $reason,
        public readonly ?string $blockedBy,
        public readonly Carbon $blockedAt,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {
    }

    protected function tenantChannelSuffix(): string
    {
        return 'mobile';
    }

    public function broadcastAs(): string
    {
        return 'device.blocked';
    }
}
