<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Monitoring\Services;

use App\Domain\Monitoring\Services\PrometheusExporter;
use App\Models\Account;
use App\Models\Asset;
use App\Models\ExchangeOrder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PrometheusExporterTest extends TestCase
{
    use RefreshDatabase;

    private PrometheusExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = app(PrometheusExporter::class);
    }

    public function test_exports_prometheus_format(): void
    {
        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertIsString($output);
        $this->assertStringContainsString('# HELP', $output);
        $this->assertStringContainsString('# TYPE', $output);
    }

    public function test_exports_application_metrics(): void
    {
        // Arrange - Create some test data
        User::factory()->count(5)->create();

        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('app_users_total', $output);
        $this->assertStringContainsString('app_uptime_seconds', $output);
        $this->assertStringContainsString('app_memory_usage_bytes', $output);
        $this->assertStringContainsString('app_cache_', $output);
    }

    public function test_exports_business_metrics(): void
    {
        // Arrange - Create business data
        $user = User::factory()->create();
        $asset = Asset::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Transaction::factory()->count(3)->create([
            'account_id' => $account->id,
            'asset_id'   => $asset->id,
            'status'     => 'completed',
        ]);

        ExchangeOrder::factory()->count(2)->create([
            'status' => 'filled',
        ]);

        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('business_transactions_total', $output);
        $this->assertStringContainsString('business_accounts_total', $output);
        $this->assertStringContainsString('business_orders_total', $output);
    }

    public function test_exports_infrastructure_metrics(): void
    {
        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('infra_db_connections', $output);
        $this->assertStringContainsString('infra_queue_size', $output);
        $this->assertStringContainsString('infra_redis_memory_bytes', $output);
    }

    public function test_exports_http_metrics(): void
    {
        // Arrange - Simulate some HTTP metrics
        Cache::put('metrics.http.total', 1000);
        Cache::put('metrics.http.success', 950);
        Cache::put('metrics.http.errors', 50);
        Cache::put('metrics.http.duration', 0.250);

        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('http_requests_total', $output);
        $this->assertStringContainsString('http_request_duration_seconds', $output);
    }

    public function test_exports_cache_metrics(): void
    {
        // Arrange - Set some cache metrics
        Cache::put('test_key_1', 'value1');
        Cache::put('test_key_2', 'value2');
        Cache::put('metrics.cache.hits', 100);
        Cache::put('metrics.cache.misses', 10);

        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('app_cache_hits_total', $output);
        $this->assertStringContainsString('app_cache_misses_total', $output);
    }

    public function test_exports_queue_metrics(): void
    {
        // Arrange
        Cache::put('metrics.queue.jobs', 50);
        Cache::put('metrics.queue.failed', 2);

        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('infra_queue_size', $output);
        $this->assertStringContainsString('infra_queue_failed_total', $output);
    }

    public function test_exports_database_metrics(): void
    {
        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('infra_db_connections', $output);
        $this->assertStringContainsString('infra_db_queries_total', $output);
    }

    public function test_exports_with_labels(): void
    {
        // Arrange
        Transaction::factory()->create(['status' => 'completed']);
        Transaction::factory()->create(['status' => 'pending']);
        Transaction::factory()->create(['status' => 'failed']);

        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('status="completed"', $output);
        $this->assertStringContainsString('status="pending"', $output);
        $this->assertStringContainsString('status="failed"', $output);
    }

    public function test_handles_empty_metrics_gracefully(): void
    {
        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
        // Should still have system metrics even with no business data
        $this->assertStringContainsString('app_uptime_seconds', $output);
        $this->assertStringContainsString('app_memory_usage_bytes', $output);
    }

    public function test_metric_names_follow_prometheus_convention(): void
    {
        // Act
        $output = $this->exporter->export();

        // Assert - Check naming conventions
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            if (strpos($line, '# HELP') === 0 || strpos($line, '# TYPE') === 0) {
                // Check metric names in HELP and TYPE lines
                if (preg_match('/# (?:HELP|TYPE) ([a-z_][a-z0-9_]*)/i', $line, $matches)) {
                    $metricName = $matches[1];
                    // Prometheus naming convention: lowercase with underscores
                    $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $metricName);
                }
            }
        }
    }

    public function test_exports_workflow_metrics(): void
    {
        // Arrange
        Cache::put('metrics.workflow.started', 10);
        Cache::put('metrics.workflow.completed', 8);
        Cache::put('metrics.workflow.failed', 2);

        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('workflow_executions_total', $output);
    }

    public function test_exports_event_metrics(): void
    {
        // Arrange
        Cache::put('metrics.events.processed', 1000);
        Cache::put('metrics.events.failed', 5);

        // Act
        $output = $this->exporter->export();

        // Assert
        $this->assertStringContainsString('events_processed_total', $output);
    }
}
