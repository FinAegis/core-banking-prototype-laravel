<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PrometheusExporter
{
    /**
     * Export metrics in Prometheus format.
     */
    public function export(): string
    {
        $output = '';

        // Application metrics
        $output .= $this->exportApplicationMetrics();

        // Business metrics
        $output .= $this->exportBusinessMetrics();

        // Infrastructure metrics
        $output .= $this->exportInfrastructureMetrics();

        // HTTP metrics
        $output .= $this->exportHttpMetrics();

        // Cache metrics
        $output .= $this->exportCacheMetrics();

        // Queue metrics
        $output .= $this->exportQueueMetrics();

        // Database metrics
        $output .= $this->exportDatabaseMetrics();

        // Workflow metrics
        $output .= $this->exportWorkflowMetrics();

        // Event metrics
        $output .= $this->exportEventMetrics();

        return $output;
    }

    /**
     * Export application metrics.
     */
    private function exportApplicationMetrics(): string
    {
        $output = '';

        // User count
        $userCount = User::count();
        $output .= "# HELP app_users_total Total number of users\n";
        $output .= "# TYPE app_users_total gauge\n";
        $output .= "app_users_total {$userCount}\n";

        // Uptime
        $uptime = time() - strtotime('2024-01-01'); // Approximate uptime
        $output .= "# HELP app_uptime_seconds Application uptime in seconds\n";
        $output .= "# TYPE app_uptime_seconds counter\n";
        $output .= "app_uptime_seconds {$uptime}\n";

        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $output .= "# HELP app_memory_usage_bytes Memory usage in bytes\n";
        $output .= "# TYPE app_memory_usage_bytes gauge\n";
        $output .= "app_memory_usage_bytes {$memoryUsage}\n";

        // Cache metrics
        $cacheHits = Cache::get('metrics:cache:hits', 0);
        $cacheMisses = Cache::get('metrics:cache:misses', 0);
        $output .= "# HELP app_cache_hits_total Total cache hits\n";
        $output .= "# TYPE app_cache_hits_total counter\n";
        $output .= "app_cache_hits_total {$cacheHits}\n";
        $output .= "# HELP app_cache_misses_total Total cache misses\n";
        $output .= "# TYPE app_cache_misses_total counter\n";
        $output .= "app_cache_misses_total {$cacheMisses}\n";

        return $output;
    }

    /**
     * Export business metrics.
     */
    private function exportBusinessMetrics(): string
    {
        $output = '';

        // Account metrics
        try {
            $accountCount = DB::table('accounts')->count();
            $output .= "# HELP business_accounts_total Total number of accounts\n";
            $output .= "# TYPE business_accounts_total gauge\n";
            $output .= "business_accounts_total {$accountCount}\n";
        } catch (Exception $e) {
            // Skip if table doesn't exist
        }

        // Transaction metrics
        try {
            $transactionCount = DB::table('transactions')->count();
            $output .= "# HELP business_transactions_total Total number of transactions\n";
            $output .= "# TYPE business_transactions_total gauge\n";
            $output .= "business_transactions_total {$transactionCount}\n";
        } catch (Exception $e) {
            // Skip if table doesn't exist
        }

        return $output;
    }

    /**
     * Export infrastructure metrics.
     */
    private function exportInfrastructureMetrics(): string
    {
        $output = '';

        // Database connections
        try {
            $connections = DB::connection()->table('information_schema.processlist')->count();
            $output .= "# HELP infra_db_connections Current database connections\n";
            $output .= "# TYPE infra_db_connections gauge\n";
            $output .= "infra_db_connections {$connections}\n";
        } catch (Exception $e) {
            $output .= "infra_db_connections 0\n";
        }

        // Queue size
        try {
            $queueSize = DB::table('jobs')->count();
            $output .= "# HELP infra_queue_size Current queue size\n";
            $output .= "# TYPE infra_queue_size gauge\n";
            $output .= "infra_queue_size {$queueSize}\n";
        } catch (Exception $e) {
            $output .= "infra_queue_size 0\n";
        }

        // Failed jobs
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $output .= "# HELP infra_queue_failed_total Total failed jobs\n";
            $output .= "# TYPE infra_queue_failed_total counter\n";
            $output .= "infra_queue_failed_total {$failedJobs}\n";
        } catch (Exception $e) {
            $output .= "infra_queue_failed_total 0\n";
        }

        // Redis memory (mock)
        $output .= "# HELP infra_redis_memory_bytes Redis memory usage in bytes\n";
        $output .= "# TYPE infra_redis_memory_bytes gauge\n";
        $output .= "infra_redis_memory_bytes 0\n";

        // Database queries
        $queries = Cache::get('metrics:db:queries:total', 0);
        $output .= "# HELP infra_db_queries_total Total database queries\n";
        $output .= "# TYPE infra_db_queries_total counter\n";
        $output .= "infra_db_queries_total {$queries}\n";

        return $output;
    }

    /**
     * Export HTTP metrics.
     */
    private function exportHttpMetrics(): string
    {
        $output = '';

        $requestsTotal = Cache::get('metrics:http:requests:total', 0);
        $output .= "# HELP http_requests_total Total HTTP requests\n";
        $output .= "# TYPE http_requests_total counter\n";
        $output .= "http_requests_total {$requestsTotal}\n";

        // Status codes
        foreach ([200, 201, 204, 400, 401, 403, 404, 422, 500, 503] as $status) {
            $count = Cache::get("metrics:http:requests:status:{$status}", 0);
            if ($count > 0) {
                $output .= "http_requests_total{status=\"{$status}\"} {$count}\n";
            }
        }

        // HTTP methods
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $count = Cache::get("metrics:http:methods:{$method}", 0);
            if ($count > 0) {
                $output .= "http_requests_total{method=\"{$method}\"} {$count}\n";
            }
        }

        // Average duration
        $avgDuration = Cache::get('metrics:http:duration:average', 0);
        $output .= "# HELP http_request_duration_seconds HTTP request duration in seconds\n";
        $output .= "# TYPE http_request_duration_seconds gauge\n";
        $output .= "http_request_duration_seconds {$avgDuration}\n";

        return $output;
    }

    /**
     * Export cache metrics.
     */
    private function exportCacheMetrics(): string
    {
        $output = '';

        $hits = Cache::get('metrics:cache:hits', 0);
        $misses = Cache::get('metrics:cache:misses', 0);

        $output .= "# HELP cache_operations_total Total cache operations\n";
        $output .= "# TYPE cache_operations_total counter\n";
        $output .= "cache_operations_total{result=\"hit\"} {$hits}\n";
        $output .= "cache_operations_total{result=\"miss\"} {$misses}\n";

        return $output;
    }

    /**
     * Export queue metrics.
     */
    private function exportQueueMetrics(): string
    {
        $output = '';

        $completed = Cache::get('metrics:queue:completed', 0);
        $failed = Cache::get('metrics:queue:failed', 0);

        $output .= "# HELP queue_jobs_total Total queue jobs\n";
        $output .= "# TYPE queue_jobs_total counter\n";
        $output .= "queue_jobs_total{status=\"completed\"} {$completed}\n";
        $output .= "queue_jobs_total{status=\"failed\"} {$failed}\n";

        return $output;
    }

    /**
     * Export database metrics.
     */
    private function exportDatabaseMetrics(): string
    {
        $output = '';

        $queries = Cache::get('metrics:db:queries:total', 0);
        $output .= "# HELP database_queries_total Total database queries\n";
        $output .= "# TYPE database_queries_total counter\n";
        $output .= "database_queries_total {$queries}\n";

        return $output;
    }

    /**
     * Export workflow metrics.
     */
    private function exportWorkflowMetrics(): string
    {
        $output = '';

        $started = Cache::get('metrics:workflows:started', 0);
        $completed = Cache::get('metrics:workflows:completed', 0);
        $failed = Cache::get('metrics:workflows:failed', 0);

        $output .= "# HELP workflow_executions_total Total workflow executions\n";
        $output .= "# TYPE workflow_executions_total counter\n";
        $output .= "workflow_executions_total{status=\"started\"} {$started}\n";
        $output .= "workflow_executions_total{status=\"completed\"} {$completed}\n";
        $output .= "workflow_executions_total{status=\"failed\"} {$failed}\n";

        return $output;
    }

    /**
     * Export event metrics.
     */
    private function exportEventMetrics(): string
    {
        $output = '';

        $processed = Cache::get('metrics:events:processed', 0);
        $failed = Cache::get('metrics:events:failed', 0);

        $output .= "# HELP events_processed_total Total events processed\n";
        $output .= "# TYPE events_processed_total counter\n";
        $output .= "events_processed_total {$processed}\n";

        if ($failed > 0) {
            $output .= "events_processed_total{status=\"failed\"} {$failed}\n";
        }

        return $output;
    }
}
