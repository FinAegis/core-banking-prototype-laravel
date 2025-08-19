<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Monitoring\Services\HealthChecker;
use App\Domain\Monitoring\Services\PrometheusExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MonitoringController extends Controller
{
    /**
     * Prometheus metrics endpoint.
     */
    public function metrics(PrometheusExporter $exporter): Response
    {
        $metrics = $exporter->export();

        return response($metrics, 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }

    /**
     * Health check endpoint.
     */
    public function health(HealthChecker $checker): JsonResponse
    {
        $health = $checker->check();

        $status = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $status);
    }

    /**
     * Readiness check endpoint.
     */
    public function ready(HealthChecker $checker): JsonResponse
    {
        $readiness = $checker->checkReadiness();

        $status = $readiness['ready'] ? 200 : 503;

        return response()->json($readiness, $status);
    }

    /**
     * Liveness check endpoint.
     */
    public function alive(): JsonResponse
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $uptime = microtime(true) - $startTime;

        return response()->json([
            'alive'        => true,
            'timestamp'    => now()->toIso8601String(),
            'uptime'       => round($uptime, 3),
            'memory_usage' => memory_get_usage(true),
        ], 200);
    }
}
