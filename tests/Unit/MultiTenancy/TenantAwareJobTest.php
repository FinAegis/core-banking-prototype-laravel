<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Domain\Shared\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Tests for TenantAwareJob trait functionality.
 *
 * These are pure unit tests that don't require database or Redis.
 */
class TenantAwareJobTest extends TestCase
{
    #[Test]
    public function it_does_not_capture_tenant_id_when_no_tenant_is_active(): void
    {
        // In unit test context, tenant() function doesn't exist or returns null
        $job = $this->createTenantAwareJob('test-data');

        $this->assertNull($job->dispatchedTenantId);
    }

    #[Test]
    public function it_returns_minimal_tenant_tags_when_no_tenant(): void
    {
        $job = $this->createTenantAwareJob();

        /** @var array<int, string> $tags */
        $tags = $job->tenantTags(); // @phpstan-ignore method.notFound

        $this->assertContains('tenant-aware', $tags);

        // Should not have a tenant:xxx tag (only 'tenant-aware')
        $tenantTags = array_filter($tags, fn ($tag) => str_starts_with($tag, 'tenant:'));
        $this->assertEmpty($tenantTags);
    }

    #[Test]
    public function it_returns_null_for_current_tenant_id_when_no_tenant(): void
    {
        $job = $this->createTenantAwareJob();

        // Use reflection to test the protected method
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('getCurrentTenantId');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($job));
    }

    #[Test]
    public function verify_tenant_context_throws_when_no_tenant_and_required(): void
    {
        $job = $this->createTenantRequiredJob();

        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('verifyTenantContext');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job requires tenant context but none is initialized');

        $method->invoke($job);
    }

    #[Test]
    public function requires_tenant_context_returns_true_by_default(): void
    {
        $job = $this->createTenantAwareJob();

        // The trait's default implementation returns true
        $this->assertTrue($job->requiresTenantContext()); // @phpstan-ignore method.notFound
    }

    #[Test]
    public function requires_tenant_context_can_be_overridden_to_false(): void
    {
        $job = $this->createOptionalTenantJob();

        $this->assertFalse($job->requiresTenantContext()); // @phpstan-ignore method.notFound
    }

    #[Test]
    public function tenant_tags_always_includes_tenant_aware_tag(): void
    {
        $job = $this->createTenantAwareJob();

        /** @var array<int, string> $tenantTags */
        $tenantTags = $job->tenantTags(); // @phpstan-ignore method.notFound

        $this->assertContains('tenant-aware', $tenantTags);
    }

    #[Test]
    public function job_properties_are_initialized(): void
    {
        $job = $this->createTenantAwareJob();

        // dispatchedTenantId should be null when no tenant is active
        $this->assertNull($job->dispatchedTenantId);
    }

    /**
     * Create a test job that uses the TenantAwareJob trait.
     */
    private function createTenantAwareJob(string $testData = 'test'): object
    {
        return new class ($testData) implements ShouldQueue {
            use Dispatchable;
            use InteractsWithQueue;
            use Queueable;
            use SerializesModels;
            use TenantAwareJob;

            public function __construct(
                public readonly string $testData = 'test'
            ) {
                $this->initializeTenantAwareJob();
            }

            public function handle(): void
            {
            }
        };
    }

    /**
     * Create a test job that requires tenant context.
     */
    private function createTenantRequiredJob(): object
    {
        return new class () implements ShouldQueue {
            use Dispatchable;
            use InteractsWithQueue;
            use Queueable;
            use SerializesModels;
            use TenantAwareJob;

            public function __construct()
            {
                $this->initializeTenantAwareJob();
            }

            public function handle(): void
            {
                $this->verifyTenantContext();
            }

            public function requiresTenantContext(): bool
            {
                return true;
            }
        };
    }

    /**
     * Create a test job that does not require tenant context.
     */
    private function createOptionalTenantJob(): object
    {
        return new class () implements ShouldQueue {
            use Dispatchable;
            use InteractsWithQueue;
            use Queueable;
            use SerializesModels;
            use TenantAwareJob;

            public function __construct()
            {
                $this->initializeTenantAwareJob();
            }

            public function handle(): void
            {
            }

            public function requiresTenantContext(): bool
            {
                return false;
            }
        };
    }
}
