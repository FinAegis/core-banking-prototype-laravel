<?php

declare(strict_types=1);

namespace App\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Trait for events that should be broadcast to tenant-specific channels.
 *
 * Apply this trait to any event implementing ShouldBroadcast to
 * automatically scope the broadcast to the current tenant's channels.
 *
 * Usage:
 * ```php
 * class AccountBalanceUpdated implements ShouldBroadcast
 * {
 *     use Dispatchable, InteractsWithSockets, SerializesModels;
 *     use TenantBroadcastEvent;
 *
 *     public function __construct(
 *         public readonly string $accountId,
 *         public readonly float $balance
 *     ) {}
 *
 *     // Override to customize the channel suffix
 *     protected function tenantChannelSuffix(): string
 *     {
 *         return 'accounts';
 *     }
 * }
 * ```
 *
 * The event will broadcast to: private-tenant.{tenantId}.accounts
 */
trait TenantBroadcastEvent
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $tenantId = $this->getTenantIdForBroadcast();

        if ($tenantId === null) {
            return [];
        }

        $suffix = $this->tenantChannelSuffix();
        $channelName = $suffix
            ? "tenant.{$tenantId}.{$suffix}"
            : "tenant.{$tenantId}";

        return [
            new PrivateChannel($channelName),
        ];
    }

    /**
     * Get the tenant ID for broadcast channel scoping.
     *
     * Override this method if the tenant ID is stored differently in your event.
     */
    protected function getTenantIdForBroadcast(): ?string
    {
        // Try to get from current tenant context
        if (function_exists('tenant') && tenant()) {
            return (string) tenant()->getTenantKey();
        }

        // Try to get from a tenantId property on the event
        /** @phpstan-ignore function.impossibleType, function.alreadyNarrowedType */
        if (property_exists($this, 'tenantId') && $this->tenantId !== null) {
            return (string) $this->tenantId;
        }

        return null;
    }

    /**
     * Get the channel suffix for this event type.
     *
     * Override this in your event to broadcast to a specific sub-channel.
     * Return empty string to broadcast to the base tenant channel.
     */
    protected function tenantChannelSuffix(): string
    {
        return '';
    }
}
