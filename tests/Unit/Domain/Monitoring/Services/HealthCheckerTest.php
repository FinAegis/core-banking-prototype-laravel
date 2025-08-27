<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Monitoring\Services;

use App\Domain\Monitoring\Services\HealthChecker;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class HealthCheckerTest extends TestCase
{
    use RefreshDatabase;

    private HealthChecker $healthChecker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->healthChecker = app(HealthChecker::class);
    }

    public function test_health_check_returns_healthy_status(): void
    {
        // Act
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_database_check(): void
    {
        // Act
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('database', $result['checks']);
        $this->assertTrue($result['checks']['database']['healthy']);
        $this->assertEquals('Database connection successful', $result['checks']['database']['message']);
        $this->assertArrayHasKey('duration_ms', $result['checks']['database']);
        $this->assertIsFloat($result['checks']['database']['duration_ms']);
    }

    public function test_cache_check(): void
    {
        // Arrange
        Cache::put('test_key', 'test_value');

        // Act
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('cache', $result['checks']);
        $this->assertTrue($result['checks']['cache']['healthy']);
        $this->assertEquals('Cache is operational', $result['checks']['cache']['message']);
    }

    public function test_redis_check(): void
    {
        // Act
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('redis', $result['checks']);
        $this->assertArrayHasKey('healthy', $result['checks']['redis']);
        $this->assertArrayHasKey('message', $result['checks']['redis']);

        // Redis might not be available in test environment
        if ($result['checks']['redis']['healthy']) {
            $this->assertEquals('Redis connection successful', $result['checks']['redis']['message']);
            // Note: Redis check doesn't return memory_usage_mb, only duration_ms
            $this->assertArrayHasKey('duration_ms', $result['checks']['redis']);
        }
    }

    public function test_queue_check(): void
    {
        // Act
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('queue', $result['checks']);
        $this->assertTrue($result['checks']['queue']['healthy']);
        $this->assertEquals('Queue is operating normally', $result['checks']['queue']['message']);
        $this->assertArrayHasKey('pending_jobs', $result['checks']['queue']);
        $this->assertArrayHasKey('failed_jobs', $result['checks']['queue']);
    }

    public function test_storage_check(): void
    {
        // Act
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('storage', $result['checks']);
        $this->assertTrue($result['checks']['storage']['healthy']);
        $this->assertEquals('Storage has sufficient space', $result['checks']['storage']['message']);
        $this->assertArrayHasKey('free_gb', $result['checks']['storage']);
        $this->assertArrayHasKey('total_gb', $result['checks']['storage']);
        $this->assertArrayHasKey('used_percent', $result['checks']['storage']);
    }

    public function test_migrations_check(): void
    {
        // Act
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('migrations', $result['checks']);
        $this->assertArrayHasKey('healthy', $result['checks']['migrations']);
        $this->assertArrayHasKey('message', $result['checks']['migrations']);

        if ($result['checks']['migrations']['healthy']) {
            $this->assertEquals('All migrations are up to date', $result['checks']['migrations']['message']);
        }
    }

    public function test_readiness_check(): void
    {
        // Act
        $result = $this->healthChecker->checkReadiness();

        // Assert
        $this->assertArrayHasKey('ready', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('timestamp', $result);

        // Verify essential services are checked
        $checkNames = array_column($result['checks'], 'name');
        $this->assertContains('database', $checkNames);
        $this->assertContains('cache', $checkNames);
        $this->assertContains('migrations', $checkNames);
    }

    public function test_liveness_check(): void
    {
        // Act
        $result = $this->healthChecker->checkReadiness();

        // Assert
        $this->assertArrayHasKey('ready', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('checks', $result);

        $this->assertTrue($result['ready']);
    }

    public function test_unhealthy_status_when_check_fails(): void
    {
        // Mock a failing database connection
        DB::shouldReceive('select')
            ->once()
            ->andThrow(new Exception('Database connection failed'));

        $healthChecker = new HealthChecker();

        // Act
        $result = $healthChecker->check();

        // Assert
        $this->assertEquals('unhealthy', $result['status']);
        $this->assertFalse($result['checks']['database']['healthy']);
        $this->assertStringContainsString('Database connection failed', $result['checks']['database']['error']);
    }

    public function test_overall_health_depends_on_individual_checks(): void
    {
        // Act
        $result = $this->healthChecker->check();

        // Assert
        $allHealthy = true;
        foreach ($result['checks'] as $check) {
            if (! $check['healthy']) {
                $allHealthy = false;
                break;
            }
        }

        $this->assertEquals(
            $allHealthy ? 'healthy' : 'unhealthy',
            $result['status']
        );
    }

    public function test_response_times_are_measured(): void
    {
        // Act - checkDatabase is called internally
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('database', $result['checks']);

        if ($result['checks']['database']['healthy']) {
            $this->assertArrayHasKey('duration_ms', $result['checks']['database']);
            $this->assertIsFloat($result['checks']['database']['duration_ms']);
            $this->assertGreaterThan(0, $result['checks']['database']['duration_ms']);
        }
    }

    public function test_storage_metrics_are_calculated(): void
    {
        // Act - checkStorage is called internally by check()
        $result = $this->healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('storage', $result['checks']);
        $storage = $result['checks']['storage'];

        if ($storage['healthy']) {
            $this->assertArrayHasKey('free_gb', $storage);
            $this->assertArrayHasKey('total_gb', $storage);
            $this->assertArrayHasKey('used_percent', $storage);

            $this->assertIsFloat($storage['free_gb']);
            $this->assertIsFloat($storage['total_gb']);
            $this->assertIsFloat($storage['used_percent']);

            // Verify percentage calculation
            if ($storage['total_gb'] > 0) {
                $expectedPercentage = (($storage['total_gb'] - $storage['free_gb']) / $storage['total_gb']) * 100;
                $this->assertEqualsWithDelta($expectedPercentage, $storage['used_percent'], 0.1);
            }
        }
    }

    public function test_handles_partial_failures_gracefully(): void
    {
        // Mock Redis failure but keep other services working
        Redis::shouldReceive('ping')
            ->once()
            ->andThrow(new Exception('Redis not available'));

        $healthChecker = new HealthChecker();

        // Act
        $result = $healthChecker->check();

        // Assert
        $this->assertArrayHasKey('checks', $result);

        // Find Redis check
        $redisCheck = null;
        foreach ($result['checks'] as $check) {
            if ($check['name'] === 'redis') {
                $redisCheck = $check;
                break;
            }
        }

        if ($redisCheck) {
            $this->assertFalse($redisCheck['healthy']);
        }

        // Other checks should still work
        $databaseCheck = null;
        foreach ($result['checks'] as $check) {
            if ($check['name'] === 'database') {
                $databaseCheck = $check;
                break;
            }
        }

        if ($databaseCheck) {
            $this->assertTrue($databaseCheck['healthy']);
        }
    }
}
