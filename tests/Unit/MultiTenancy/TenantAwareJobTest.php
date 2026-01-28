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

/**
 * Test job class that uses the TenantAwareJob trait.
 */
class TestTenantAwareJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAwareJob;

    public bool $handled = false;

    public ?string $tenantIdDuringHandle = null;

    public function __construct(
        public readonly string $testData = 'test'
    ) {
        $this->initializeTenantAwareJob();
    }

    public function handle(): void
    {
        $this->handled = true;
        $this->tenantIdDuringHandle = $this->getCurrentTenantId();
    }

    public function tags(): array
    {
        return array_merge(
            ['test-job'],
            $this->tenantTags()
        );
    }
}

/**
 * Test job that requires tenant context.
 */
class TestTenantRequiredJob implements ShouldQueue
{
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
}

/**
 * Test job that does not require tenant context.
 */
class TestOptionalTenantJob implements ShouldQueue
{
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
        // This job works with or without tenant context
    }

    public function requiresTenantContext(): bool
    {
        return false;
    }
}

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
        $job = new TestTenantAwareJob('test-data');

        $this->assertNull($job->dispatchedTenantId);
    }

    #[Test]
    public function it_returns_minimal_tenant_tags_when_no_tenant(): void
    {
        $job = new TestTenantAwareJob();

        $tags = $job->tags();

        $this->assertContains('test-job', $tags);
        $this->assertContains('tenant-aware', $tags);

        // Should not have a tenant:xxx tag (only 'tenant-aware')
        $tenantTags = array_filter($tags, fn ($tag) => str_starts_with($tag, 'tenant:'));
        $this->assertEmpty($tenantTags);
    }

    #[Test]
    public function it_returns_null_for_current_tenant_id_when_no_tenant(): void
    {
        $job = new TestTenantAwareJob();
        $job->handle();

        $this->assertNull($job->tenantIdDuringHandle);
    }

    #[Test]
    public function verify_tenant_context_throws_when_no_tenant_and_required(): void
    {
        $job = new TestTenantRequiredJob();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Job requires tenant context but none is initialized');

        $job->handle();
    }

    #[Test]
    public function requires_tenant_context_returns_true_by_default(): void
    {
        $job = new TestTenantAwareJob();

        // The trait's default implementation returns true
        $this->assertTrue($job->requiresTenantContext());
    }

    #[Test]
    public function requires_tenant_context_can_be_overridden_to_false(): void
    {
        $job = new TestOptionalTenantJob();

        $this->assertFalse($job->requiresTenantContext());
    }

    #[Test]
    public function required_tenant_job_returns_true_for_requires_context(): void
    {
        $job = new TestTenantRequiredJob();

        $this->assertTrue($job->requiresTenantContext());
    }

    #[Test]
    public function job_stores_test_data_correctly(): void
    {
        $job = new TestTenantAwareJob('my-data');

        $this->assertEquals('my-data', $job->testData);
    }

    #[Test]
    public function tenant_tags_always_includes_tenant_aware_tag(): void
    {
        $job = new TestTenantAwareJob();

        $tenantTags = $job->tenantTags();

        $this->assertContains('tenant-aware', $tenantTags);
    }

    #[Test]
    public function job_can_be_handled(): void
    {
        $job = new TestTenantAwareJob();

        $this->assertFalse($job->handled);

        $job->handle();

        $this->assertTrue($job->handled);
    }
}
