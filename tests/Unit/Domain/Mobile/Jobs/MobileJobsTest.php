<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mobile\Jobs;

use App\Domain\Mobile\Jobs\CleanupExpiredChallenges;
use App\Domain\Mobile\Jobs\CleanupStaleDevices;
use App\Domain\Mobile\Jobs\ProcessScheduledNotifications;
use App\Domain\Mobile\Jobs\RetryFailedNotifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Mobile domain jobs.
 *
 * These are pure unit tests that don't require database or Redis.
 * They verify job configuration, tagging, and tenant awareness.
 */
class MobileJobsTest extends TestCase
{
    #[Test]
    public function process_scheduled_notifications_implements_should_queue(): void
    {
        $job = new ProcessScheduledNotifications();

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    #[Test]
    public function process_scheduled_notifications_uses_mobile_queue(): void
    {
        $job = new ProcessScheduledNotifications();

        $this->assertEquals('mobile', $job->queue);
    }

    #[Test]
    public function process_scheduled_notifications_has_correct_tags(): void
    {
        $job = new ProcessScheduledNotifications();

        $tags = $job->tags();

        $this->assertContains('mobile', $tags);
        $this->assertContains('notifications', $tags);
        $this->assertContains('scheduled', $tags);
        $this->assertContains('tenant-aware', $tags);
    }

    #[Test]
    public function process_scheduled_notifications_does_not_require_tenant(): void
    {
        $job = new ProcessScheduledNotifications();

        $this->assertFalse($job->requiresTenantContext());
    }

    #[Test]
    public function process_scheduled_notifications_has_retry_settings(): void
    {
        $job = new ProcessScheduledNotifications();

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    #[Test]
    public function retry_failed_notifications_implements_should_queue(): void
    {
        $job = new RetryFailedNotifications();

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    #[Test]
    public function retry_failed_notifications_uses_mobile_queue(): void
    {
        $job = new RetryFailedNotifications();

        $this->assertEquals('mobile', $job->queue);
    }

    #[Test]
    public function retry_failed_notifications_has_correct_tags(): void
    {
        $job = new RetryFailedNotifications();

        $tags = $job->tags();

        $this->assertContains('mobile', $tags);
        $this->assertContains('notifications', $tags);
        $this->assertContains('retry', $tags);
        $this->assertContains('tenant-aware', $tags);
    }

    #[Test]
    public function retry_failed_notifications_accepts_batch_size(): void
    {
        $job = new RetryFailedNotifications(50);

        $reflection = new ReflectionClass($job);
        $property = $reflection->getProperty('batchSize');
        $property->setAccessible(true);

        $this->assertEquals(50, $property->getValue($job));
    }

    #[Test]
    public function retry_failed_notifications_has_retry_settings(): void
    {
        $job = new RetryFailedNotifications();

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->backoff);
    }

    #[Test]
    public function cleanup_expired_challenges_implements_should_queue(): void
    {
        $job = new CleanupExpiredChallenges();

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    #[Test]
    public function cleanup_expired_challenges_uses_mobile_queue(): void
    {
        $job = new CleanupExpiredChallenges();

        $this->assertEquals('mobile', $job->queue);
    }

    #[Test]
    public function cleanup_expired_challenges_has_correct_tags(): void
    {
        $job = new CleanupExpiredChallenges();

        $tags = $job->tags();

        $this->assertContains('mobile', $tags);
        $this->assertContains('biometric', $tags);
        $this->assertContains('cleanup', $tags);
        $this->assertContains('tenant-aware', $tags);
    }

    #[Test]
    public function cleanup_stale_devices_implements_should_queue(): void
    {
        $job = new CleanupStaleDevices();

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    #[Test]
    public function cleanup_stale_devices_uses_mobile_queue(): void
    {
        $job = new CleanupStaleDevices();

        $this->assertEquals('mobile', $job->queue);
    }

    #[Test]
    public function cleanup_stale_devices_has_correct_tags(): void
    {
        $job = new CleanupStaleDevices();

        $tags = $job->tags();

        $this->assertContains('mobile', $tags);
        $this->assertContains('devices', $tags);
        $this->assertContains('cleanup', $tags);
        $this->assertContains('tenant-aware', $tags);
    }

    #[Test]
    public function cleanup_stale_devices_accepts_stale_days(): void
    {
        $job = new CleanupStaleDevices(30);

        $reflection = new ReflectionClass($job);
        $property = $reflection->getProperty('staleDays');
        $property->setAccessible(true);

        $this->assertEquals(30, $property->getValue($job));
    }

    #[Test]
    public function cleanup_stale_devices_has_retry_settings(): void
    {
        $job = new CleanupStaleDevices();

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->backoff);
    }

    #[Test]
    public function all_jobs_have_tenant_aware_tag(): void
    {
        $jobs = [
            new ProcessScheduledNotifications(),
            new RetryFailedNotifications(),
            new CleanupExpiredChallenges(),
            new CleanupStaleDevices(),
        ];

        foreach ($jobs as $job) {
            $tags = $job->tags();
            $this->assertContains('tenant-aware', $tags, sprintf(
                '%s should have tenant-aware tag',
                get_class($job)
            ));
        }
    }

    #[Test]
    public function all_jobs_can_run_without_tenant_context(): void
    {
        $jobs = [
            new ProcessScheduledNotifications(),
            new RetryFailedNotifications(),
            new CleanupExpiredChallenges(),
            new CleanupStaleDevices(),
        ];

        foreach ($jobs as $job) {
            $this->assertFalse(
                $job->requiresTenantContext(),
                sprintf('%s should not require tenant context', get_class($job))
            );
        }
    }

    #[Test]
    public function all_jobs_have_null_tenant_id_when_no_tenant_active(): void
    {
        $jobs = [
            new ProcessScheduledNotifications(),
            new RetryFailedNotifications(),
            new CleanupExpiredChallenges(),
            new CleanupStaleDevices(),
        ];

        foreach ($jobs as $job) {
            $this->assertNull(
                $job->dispatchedTenantId,
                sprintf('%s should have null tenant id', get_class($job))
            );
        }
    }
}
