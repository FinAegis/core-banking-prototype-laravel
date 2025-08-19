<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Workflows\Activities;

use App\Domain\Monitoring\Services\PrometheusExporter;
use Illuminate\Support\Facades\Cache;

class ExportMetricsActivity
{
    private PrometheusExporter $exporter;

    public function __construct(PrometheusExporter $exporter)
    {
        $this->exporter = $exporter;
    }

    public function execute(array $data): array
    {
        // Export metrics in Prometheus format
        $prometheusData = $this->exporter->export();

        // Cache the exported data for scraping
        Cache::put('monitoring:prometheus:export', $prometheusData, now()->addMinutes(5));

        // Also export as JSON for API consumption
        $jsonData = $this->exporter->exportJson();
        Cache::put('monitoring:metrics:json', $jsonData, now()->addMinutes(5));

        // Store metrics snapshot in database for historical analysis
        $this->storeMetricsSnapshot($jsonData, $data['health_status'] ?? []);

        return [
            'exported_metrics_count' => count($jsonData),
            'export_timestamp'       => now()->toIso8601String(),
            'cache_key'              => 'monitoring:prometheus:export',
        ];
    }

    private function storeMetricsSnapshot(array $metrics, array $healthStatus): void
    {
        \DB::table('monitoring_metrics_snapshots')->insert([
            'metrics'        => json_encode($metrics),
            'health_status'  => json_encode($healthStatus),
            'metrics_count'  => count($metrics),
            'overall_health' => $healthStatus['status'] ?? 'unknown',
            'created_at'     => now(),
        ]);

        // Clean up old snapshots (keep last 7 days)
        \DB::table('monitoring_metrics_snapshots')
            ->where('created_at', '<', now()->subDays(7))
            ->delete();
    }
}
