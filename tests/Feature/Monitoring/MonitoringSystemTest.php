<?php

declare(strict_types=1);

namespace Tests\Feature\Monitoring;

use App\Domain\Monitoring\Services\HealthChecker;
use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\Services\PrometheusExporter;
use App\Models\User;
use App\Models\Team;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MonitoringSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_monitoring_workflow(): void
    {
        // Arrange
        $collector = app(MetricsCollector::class);
        $exporter = app(PrometheusExporter::class);
        $healthChecker = app(HealthChecker::class);

        // Create test data
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        
        // Act - Collect various metrics
        $collector->collectHttpRequest('GET', '/api/users', 200, 0.125);
        $collector->collectBusinessEvent('UserRegistered', ['user_id' => $user->id]);
        $collector->collectCacheHit('user_profile');
        $collector->collectQueueJob('ProcessPayment', 'completed', 1.5);

        // Export metrics
        $prometheusOutput = $exporter->export();
        
        // Check health
        $health = $healthChecker->check();
        $readiness = $healthChecker->checkReadiness();
        $liveness = $healthChecker->checkLiveness();

        // Assert - Prometheus output
        $this->assertIsString($prometheusOutput);
        $this->assertStringContainsString('# HELP', $prometheusOutput);
        $this->assertStringContainsString('# TYPE', $prometheusOutput);
        $this->assertStringContainsString('http_requests_total', $prometheusOutput);
        $this->assertStringContainsString('app_users_total', $prometheusOutput);

        // Assert - Health checks
        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('checks', $health);
        
        $this->assertIsArray($readiness);
        $this->assertArrayHasKey('ready', $readiness);
        
        $this->assertIsArray($liveness);
        $this->assertArrayHasKey('alive', $liveness);
        $this->assertTrue($liveness['alive']);

        // Assert - Metrics were collected
        $this->assertEquals(1, Cache::get('metrics.http.total'));
        $this->assertEquals(1, Cache::get('metrics.http.success'));
        $this->assertEquals(1, Cache::get('metrics.events.UserRegistered'));
        $this->assertEquals(1, Cache::get('metrics.cache.hits'));
        $this->assertEquals(1, Cache::get('metrics.queue.completed'));
    }

    public function test_monitoring_api_endpoints(): void
    {
        // Arrange - Create some metrics
        Cache::put('metrics.http.total', 100);
        Cache::put('metrics.http.success', 95);
        Cache::put('metrics.http.errors', 5);

        // Act & Assert - Metrics endpoint
        $response = $this->get('/api/monitoring/metrics');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4');
        $response->assertSee('http_requests_total');
        
        // Act & Assert - Health endpoint
        $response = $this->getJson('/api/monitoring/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'checks' => [],
            'timestamp',
        ]);
        
        // Act & Assert - Ready endpoint
        $response = $this->getJson('/api/monitoring/ready');
        $response->assertStatus(200);
        $response->assertJsonPath('ready', true);
        
        // Act & Assert - Alive endpoint
        $response = $this->getJson('/api/monitoring/alive');
        $response->assertStatus(200);
        $response->assertJsonPath('alive', true);
    }

    public function test_metrics_collector_increments_correctly(): void
    {
        // Arrange
        $collector = app(MetricsCollector::class);
        // Reset metrics manually
        Cache::forget('metrics.http.total');
        Cache::forget('metrics.http.success');
        Cache::forget('metrics.http.errors');

        // Act - Simulate multiple requests
        for ($i = 0; $i < 10; $i++) {
            $statusCode = $i < 8 ? 200 : 500; // 80% success rate
            $collector->collectHttpRequest('GET', '/api/test', $statusCode, 0.1);
        }

        // Assert
        $this->assertEquals(10, Cache::get('metrics.http.total'));
        $this->assertEquals(8, Cache::get('metrics.http.success'));
        $this->assertEquals(2, Cache::get('metrics.http.errors'));
    }

    public function test_health_checker_detects_issues(): void
    {
        // Arrange
        $healthChecker = app(HealthChecker::class);

        // Act - Check overall health
        $health = $healthChecker->check();
        $readiness = $healthChecker->checkReadiness();
        $liveness = $healthChecker->checkLiveness();

        // Assert - All should be healthy in test environment
        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertIsArray($readiness);
        $this->assertArrayHasKey('ready', $readiness);
        $this->assertIsArray($liveness);
        $this->assertTrue($liveness['alive']);
    }

    public function test_prometheus_exporter_formats_metrics(): void
    {
        // Arrange
        $exporter = app(PrometheusExporter::class);
        User::factory()->count(5)->create();
        Team::factory()->count(3)->create();

        // Act
        $output = $exporter->export();

        // Assert - Check format
        $lines = explode("\n", $output);
        $hasHelp = false;
        $hasType = false;
        $hasMetric = false;

        foreach ($lines as $line) {
            if (strpos($line, '# HELP') === 0) {
                $hasHelp = true;
            }
            if (strpos($line, '# TYPE') === 0) {
                $hasType = true;
            }
            if (preg_match('/^[a-z_][a-z0-9_]*/', $line)) {
                $hasMetric = true;
            }
        }

        $this->assertTrue($hasHelp, 'Output should contain HELP lines');
        $this->assertTrue($hasType, 'Output should contain TYPE lines');
        $this->assertTrue($hasMetric, 'Output should contain metric lines');
    }

    public function test_monitoring_middleware_collects_metrics(): void
    {
        // Arrange
        Cache::forget('metrics.http.total');

        // Act - Make HTTP requests
        $this->get('/api/monitoring/health');
        $this->get('/api/monitoring/metrics');
        $this->get('/api/monitoring/ready');

        // Assert - Metrics should be collected
        $total = Cache::get('metrics.http.total', 0);
        $this->assertGreaterThanOrEqual(3, $total);
    }

    public function test_business_metrics_are_exported(): void
    {
        // Arrange
        $exporter = app(PrometheusExporter::class);
        
        // Create business data
        User::factory()->count(10)->create();
        Team::factory()->count(5)->create();

        // Act
        $output = $exporter->export();

        // Assert
        $this->assertStringContainsString('app_users_total', $output);
        $this->assertStringContainsString('10', $output);
    }

    public function test_cache_metrics_tracking(): void
    {
        // Arrange
        $collector = app(MetricsCollector::class);
        // Reset cache metrics
        Cache::forget('metrics.cache.hits');
        Cache::forget('metrics.cache.misses');

        // Act
        $collector->collectCacheHit('key1');
        $collector->collectCacheHit('key2');
        $collector->collectCacheMiss('key3');
        $collector->collectCacheHit('key4');
        $collector->collectCacheMiss('key5');

        // Assert
        $this->assertEquals(3, Cache::get('metrics.cache.hits'));
        $this->assertEquals(2, Cache::get('metrics.cache.misses'));
        
        // Calculate hit rate
        $hits = Cache::get('metrics.cache.hits', 0);
        $misses = Cache::get('metrics.cache.misses', 0);
        $total = $hits + $misses;
        $hitRate = $total > 0 ? $hits / $total : 0;
        
        $this->assertEquals(0.6, $hitRate); // 60% hit rate
    }

    public function test_queue_metrics_tracking(): void
    {
        // Arrange
        $collector = app(MetricsCollector::class);
        // Reset queue metrics
        Cache::forget('metrics.queue.completed');
        Cache::forget('metrics.queue.failed');
        Cache::forget('metrics.queue.duration');

        // Act - Simulate queue jobs
        $collector->collectQueueJob('SendEmail', 'completed', 0.5);
        $collector->collectQueueJob('ProcessPayment', 'completed', 2.0);
        $collector->collectQueueJob('GenerateReport', 'failed', 10.0);
        $collector->collectQueueJob('SyncData', 'completed', 1.5);

        // Assert
        $this->assertEquals(3, Cache::get('metrics.queue.completed'));
        $this->assertEquals(1, Cache::get('metrics.queue.failed'));
        
        // Average duration should be calculated
        $avgDuration = Cache::get('metrics.queue.duration');
        $this->assertIsFloat($avgDuration);
        $this->assertGreaterThan(0, $avgDuration);
    }

    public function test_workflow_metrics_tracking(): void
    {
        // Arrange
        $collector = app(MetricsCollector::class);
        // Reset workflow metrics
        Cache::forget('metrics.workflows.LoanApplicationWorkflow.started');
        Cache::forget('metrics.workflows.LoanApplicationWorkflow.completed');
        Cache::forget('metrics.workflows.LoanApplicationWorkflow.failed');

        // Act - Simulate workflow executions
        $collector->collectWorkflow('LoanApplicationWorkflow', 'started', 0);
        $collector->collectWorkflow('LoanApplicationWorkflow', 'started', 0);
        $collector->collectWorkflow('LoanApplicationWorkflow', 'completed', 300.5);
        $collector->collectWorkflow('LoanApplicationWorkflow', 'failed', 150.2);
        $collector->collectWorkflow('LoanApplicationWorkflow', 'started', 0);

        // Assert
        $this->assertEquals(3, Cache::get('metrics.workflows.LoanApplicationWorkflow.started'));
        $this->assertEquals(1, Cache::get('metrics.workflows.LoanApplicationWorkflow.completed'));
        $this->assertEquals(1, Cache::get('metrics.workflows.LoanApplicationWorkflow.failed'));
    }
}