<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Monitoring\Services\HealthChecker;
use App\Domain\Monitoring\Services\PrometheusExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="System Monitoring",
 *     description="System health monitoring and metrics"
 * )
 */
class MonitoringController extends Controller
{
    /**
     * @OA\Get(
     *     path="/monitoring/metrics",
     *     operationId="systemMonitoringMetrics",
     *     tags={"System Monitoring"},
     *     summary="Get system metrics",
     *     description="Returns Prometheus-compatible system metrics",
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function metrics(PrometheusExporter $exporter): Response
    {
        $metrics = $exporter->export();

        return response($metrics, 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }

    /**
     * @OA\Get(
     *     path="/monitoring/health",
     *     operationId="systemMonitoringHealth",
     *     tags={"System Monitoring"},
     *     summary="Health check",
     *     description="Returns system health status",
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function health(HealthChecker $checker): JsonResponse
    {
        $health = $checker->check();

        $status = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $status);
    }

    /**
     * @OA\Get(
     *     path="/monitoring/ready",
     *     operationId="systemMonitoringReady",
     *     tags={"System Monitoring"},
     *     summary="Readiness check",
     *     description="Returns system readiness status",
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function ready(HealthChecker $checker): JsonResponse
    {
        $readiness = $checker->checkReadiness();

        $status = $readiness['ready'] ? 200 : 503;

        return response()->json($readiness, $status);
    }

    /**
     * @OA\Get(
     *     path="/monitoring/alive",
     *     operationId="systemMonitoringAlive",
     *     tags={"System Monitoring"},
     *     summary="Liveness check",
     *     description="Returns system liveness status",
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
