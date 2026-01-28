<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Broadcasting\TenantBroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for TenantBroadcastEvent trait.
 */
class TenantBroadcastEventTest extends TestCase
{
    #[Test]
    public function broadcast_on_returns_empty_array_when_no_tenant(): void
    {
        $event = $this->createBroadcastEvent();

        /** @phpstan-ignore method.notFound */
        $channels = $event->broadcastOn();

        $this->assertIsArray($channels);
        $this->assertEmpty($channels);
    }

    #[Test]
    public function broadcast_on_uses_tenant_id_property_when_set(): void
    {
        $event = $this->createBroadcastEventWithTenantId('test-tenant-123');

        /** @phpstan-ignore method.notFound */
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-tenant.test-tenant-123', $channels[0]->name);
    }

    #[Test]
    public function broadcast_on_includes_channel_suffix(): void
    {
        $event = $this->createBroadcastEventWithSuffix('test-tenant-456', 'accounts');

        /** @phpstan-ignore method.notFound */
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('private-tenant.test-tenant-456.accounts', $channels[0]->name);
    }

    #[Test]
    public function broadcast_on_returns_empty_when_tenant_id_is_null(): void
    {
        $event = $this->createBroadcastEventWithTenantId(null);

        /** @phpstan-ignore method.notFound */
        $channels = $event->broadcastOn();

        $this->assertEmpty($channels);
    }

    #[Test]
    public function default_channel_suffix_is_empty(): void
    {
        $event = $this->createBroadcastEvent();

        $reflection = new ReflectionClass($event);
        $method = $reflection->getMethod('tenantChannelSuffix');
        $method->setAccessible(true);

        $this->assertEquals('', $method->invoke($event));
    }

    /**
     * Create a basic broadcast event (no tenant context).
     */
    private function createBroadcastEvent(): object
    {
        return new class () {
            use TenantBroadcastEvent;
        };
    }

    /**
     * Create a broadcast event with a tenantId property.
     */
    private function createBroadcastEventWithTenantId(?string $tenantId): object
    {
        return new class ($tenantId) {
            use TenantBroadcastEvent;

            public function __construct(
                public readonly ?string $tenantId = null
            ) {
            }
        };
    }

    /**
     * Create a broadcast event with a custom channel suffix.
     */
    private function createBroadcastEventWithSuffix(string $tenantId, string $suffix): object
    {
        return new class ($tenantId, $suffix) {
            use TenantBroadcastEvent;

            public function __construct(
                public readonly ?string $tenantId = null,
                private readonly string $suffix = ''
            ) {
            }

            protected function tenantChannelSuffix(): string
            {
                return $this->suffix;
            }
        };
    }
}
