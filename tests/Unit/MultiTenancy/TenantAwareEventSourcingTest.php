<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Domain\Account\Models\TenantAccountEvent;
use App\Domain\Account\Models\TenantAccountSnapshot;
use App\Domain\Account\Repositories\TenantAccountEventRepository;
use App\Domain\Account\Repositories\TenantAccountSnapshotRepository;
use App\Domain\Shared\EventSourcing\TenantAwareAggregateRoot;
use App\Domain\Shared\EventSourcing\TenantAwareSnapshot;
use App\Domain\Shared\EventSourcing\TenantAwareSnapshotRepository;
use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;
use App\Domain\Shared\EventSourcing\TenantAwareStoredEventRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Spatie\EventSourcing\Snapshots\EloquentSnapshot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

/**
 * Unit tests for tenant-aware event sourcing infrastructure.
 *
 * These tests verify the class structure and inheritance without
 * requiring database or Redis connections.
 */
class TenantAwareEventSourcingTest extends TestCase
{
    #[Test]
    public function tenant_aware_stored_event_uses_tenant_connection(): void
    {
        $event = new class () extends TenantAwareStoredEvent {
            public $table = 'test_events';
        };

        $this->assertSame('tenant', $event->getConnectionName());
    }

    #[Test]
    public function tenant_aware_stored_event_extends_eloquent_stored_event(): void
    {
        $reflection = new ReflectionClass(TenantAwareStoredEvent::class);

        $this->assertTrue($reflection->isSubclassOf(EloquentStoredEvent::class));
    }

    #[Test]
    public function tenant_aware_snapshot_uses_tenant_connection(): void
    {
        $snapshot = new class () extends TenantAwareSnapshot {
            public $table = 'test_snapshots';
        };

        $this->assertSame('tenant', $snapshot->getConnectionName());
    }

    #[Test]
    public function tenant_aware_snapshot_extends_eloquent_snapshot(): void
    {
        $reflection = new ReflectionClass(TenantAwareSnapshot::class);

        $this->assertTrue($reflection->isSubclassOf(EloquentSnapshot::class));
    }

    #[Test]
    public function tenant_account_event_model_has_correct_table_property(): void
    {
        $reflection = new ReflectionClass(TenantAccountEvent::class);

        $this->assertTrue($reflection->hasProperty('table'));
        $this->assertTrue($reflection->isSubclassOf(TenantAwareStoredEvent::class));
    }

    #[Test]
    public function tenant_account_snapshot_model_has_correct_structure(): void
    {
        $reflection = new ReflectionClass(TenantAccountSnapshot::class);

        $this->assertTrue($reflection->hasProperty('table'));
        $this->assertTrue($reflection->isSubclassOf(TenantAwareSnapshot::class));
    }

    #[Test]
    public function tenant_aware_stored_event_repository_extends_correct_class(): void
    {
        $reflection = new ReflectionClass(TenantAwareStoredEventRepository::class);

        $this->assertTrue($reflection->implementsInterface(StoredEventRepository::class)
            || $reflection->isSubclassOf(\Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository::class));
    }

    #[Test]
    public function tenant_aware_snapshot_repository_extends_correct_class(): void
    {
        $reflection = new ReflectionClass(TenantAwareSnapshotRepository::class);

        $this->assertTrue($reflection->implementsInterface(SnapshotRepository::class)
            || $reflection->isSubclassOf(\Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository::class));
    }

    #[Test]
    public function tenant_account_event_repository_extends_tenant_aware_repository(): void
    {
        $reflection = new ReflectionClass(TenantAccountEventRepository::class);

        $this->assertTrue($reflection->isSubclassOf(TenantAwareStoredEventRepository::class));
    }

    #[Test]
    public function tenant_account_snapshot_repository_extends_tenant_aware_repository(): void
    {
        $reflection = new ReflectionClass(TenantAccountSnapshotRepository::class);

        $this->assertTrue($reflection->isSubclassOf(TenantAwareSnapshotRepository::class));
    }

    #[Test]
    public function tenant_aware_aggregate_root_is_abstract(): void
    {
        $reflection = new ReflectionClass(TenantAwareAggregateRoot::class);

        $this->assertTrue($reflection->isAbstract());
    }

    #[Test]
    public function tenant_aware_aggregate_root_extends_aggregate_root(): void
    {
        $reflection = new ReflectionClass(TenantAwareAggregateRoot::class);

        $this->assertTrue($reflection->isSubclassOf(
            \Spatie\EventSourcing\AggregateRoots\AggregateRoot::class
        ));
    }

    #[Test]
    public function tenant_aware_aggregate_root_has_require_tenant_context_method(): void
    {
        $reflection = new ReflectionClass(TenantAwareAggregateRoot::class);

        $this->assertTrue($reflection->hasMethod('requireTenantContext'));
    }

    #[Test]
    public function tenant_aware_aggregate_root_has_record_that_method(): void
    {
        $reflection = new ReflectionClass(TenantAwareAggregateRoot::class);

        $this->assertTrue($reflection->hasMethod('recordThat'));
    }

    #[Test]
    public function tenant_aware_stored_event_has_casts_property(): void
    {
        $reflection = new ReflectionClass(TenantAwareStoredEvent::class);

        $this->assertTrue($reflection->hasProperty('casts'));
        $property = $reflection->getProperty('casts');
        $this->assertTrue($property->isPublic());
    }

    #[Test]
    public function tenant_aware_snapshot_has_casts_property(): void
    {
        $reflection = new ReflectionClass(TenantAwareSnapshot::class);

        $this->assertTrue($reflection->hasProperty('casts'));
        $property = $reflection->getProperty('casts');
        $this->assertTrue($property->isPublic());
    }
}
