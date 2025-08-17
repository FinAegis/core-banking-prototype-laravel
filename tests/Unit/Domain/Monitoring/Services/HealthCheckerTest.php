<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Monitoring\Services;

use App\Domain\Monitoring\Services\HealthChecker;
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
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_database_check(): void
    {
        // Act
        $result = $this->healthChecker->checkDatabase();

        // Assert
        $this->assertTrue($result['healthy']);
        $this->assertEquals('Database connection successful', $result['message']);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertIsFloat($result['response_time']);
    }

    public function test_cache_check(): void
    {
        // Arrange
        Cache::put('test_key', 'test_value');

        // Act
        $result = $this->healthChecker->checkCache();

        // Assert
        $this->assertTrue($result['healthy']);
        $this->assertEquals('Cache is operational', $result['message']);
    }

    public function test_redis_check(): void
    {
        // Act
        $result = $this->healthChecker->checkRedis();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('healthy', $result);
        $this->assertArrayHasKey('message', $result);

        // Redis might not be available in test environment
        if ($result['healthy']) {
            $this->assertEquals('Redis connection successful', $result['message']);
            $this->assertArrayHasKey('memory_usage', $result);
        }
    }

    public function test_queue_check(): void
    {
        // Act
        $result = $this->healthChecker->checkQueue();

        // Assert
        $this->assertTrue($result['healthy']);
        $this->assertEquals('Queue system operational', $result['message']);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayHasKey('failed_jobs', $result);
    }

    public function test_storage_check(): void
    {
        // Act
        $result = $this->healthChecker->checkStorage();

        // Assert
        $this->assertTrue($result['healthy']);
        $this->assertEquals('Storage is writable', $result['message']);
        $this->assertArrayHasKey('disk_free', $result);
        $this->assertArrayHasKey('disk_total', $result);
        $this->assertArrayHasKey('disk_usage_percentage', $result);
    }

    public function test_migrations_check(): void
    {
        // Act
        $result = $this->healthChecker->checkMigrations();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('healthy', $result);
        $this->assertArrayHasKey('message', $result);

        if ($result['healthy']) {
            $this->assertEquals('All migrations are up to date', $result['message']);
        }
    }

    public function test_readiness_check(): void
    {
        // Act
        $result = $this->healthChecker->checkReadiness();

        // Assert
        $this->assertIsArray($result);
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
        $result = $this->healthChecker->checkLiveness();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('alive', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('uptime', $result);
        $this->assertArrayHasKey('memory_usage', $result);

        $this->assertTrue($result['alive']);
        $this->assertIsFloat($result['uptime']);
        $this->assertIsInt($result['memory_usage']);
    }

    public function test_unhealthy_status_when_check_fails(): void
    {
        // Mock a failing database connection
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $healthChecker = new HealthChecker();

        // Act
        $result = $healthChecker->checkDatabase();

        // Assert
        $this->assertFalse($result['healthy']);
        $this->assertStringContainsString('Database connection failed', $result['message']);
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
        // Act
        $result = $this->healthChecker->checkDatabase();

        // Assert
        $this->assertArrayHasKey('response_time', $result);
        $this->assertIsFloat($result['response_time']);
        $this->assertGreaterThan(0, $result['response_time']);
        $this->assertLessThan(1, $result['response_time']); // Should be fast
    }

    public function test_storage_metrics_are_calculated(): void
    {
        // Act
        $result = $this->healthChecker->checkStorage();

        // Assert
        $this->assertArrayHasKey('disk_free', $result);
        $this->assertArrayHasKey('disk_total', $result);
        $this->assertArrayHasKey('disk_usage_percentage', $result);

        $this->assertIsInt($result['disk_free']);
        $this->assertIsInt($result['disk_total']);
        $this->assertIsFloat($result['disk_usage_percentage']);

        // Verify percentage calculation
        if ($result['disk_total'] > 0) {
            $expectedPercentage = (($result['disk_total'] - $result['disk_free']) / $result['disk_total']) * 100;
            $this->assertEqualsWithDelta($expectedPercentage, $result['disk_usage_percentage'], 0.1);
        }
    }

    public function test_handles_partial_failures_gracefully(): void
    {
        // Mock Redis failure but keep other services working
        Redis::shouldReceive('connection->ping')
            ->once()
            ->andThrow(new \Exception('Redis not available'));

        $healthChecker = new HealthChecker();

        // Act
        $result = $healthChecker->check();

        // Assert
        $this->assertIsArray($result);
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
