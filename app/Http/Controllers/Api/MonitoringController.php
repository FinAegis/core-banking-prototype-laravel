<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Monitoring\Services\HealthChecker;
use App\Domain\Monitoring\Services\MetricsCollector;
use App\Domain\Monitoring\Services\PrometheusExporter;
use App\Domain\Monitoring\Services\DistributedTracer;
use App\Domain\Monitoring\Workflows\MonitoringWorkflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Workflow\WorkflowStub;

class MonitoringController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/monitoring/health",
     *     operationId="getHealthStatus",
     *     tags={"Monitoring"},
     *     summary="Get application health status",
     *     description="Returns comprehensive health check results",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Health status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"healthy", "degraded", "unhealthy"}),
     *             @OA\Property(property="healthy", type="boolean"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="checks", type="object"),
     *             @OA\Property(property="summary", type="object")
     *         )
     *     )
     * )
     */
    public function health(HealthChecker $healthChecker): JsonResponse
    {
        $health = $healthChecker->check();
        
        return response()->json($health, $health['healthy'] ? 200 : 503);
    }

    /**
     * @OA\Get(
     *     path="/api/monitoring/metrics",
     *     operationId="getMetrics",
     *     tags={"Monitoring"},
     *     summary="Get application metrics",
     *     description="Returns current application metrics in JSON format",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Metrics retrieved successfully",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function metrics(MetricsCollector $collector): JsonResponse
    {
        $collector->recordApplicationMetrics();
        $collector->recordBusinessMetrics();
        
        $metrics = $collector->getAllMetrics();
        
        return response()->json([
            'metrics' => $metrics,
            'timestamp' => now()->toIso8601String(),
            'count' => count($metrics),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/monitoring/prometheus",
     *     operationId="getPrometheusMetrics",
     *     tags={"Monitoring"},
     *     summary="Export metrics in Prometheus format",
     *     description="Returns metrics formatted for Prometheus scraping",
     *     @OA\Response(
     *         response=200,
     *         description="Prometheus metrics exported",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(type="string")
     *         )
     *     )
     * )
     */
    public function prometheus(PrometheusExporter $exporter): Response
    {
        $metrics = $exporter->export();
        
        return response($metrics, 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }

    /**
     * @OA\Get(
     *     path="/api/monitoring/traces",
     *     operationId="getTraces",
     *     tags={"Monitoring"},
     *     summary="Get distributed traces",
     *     description="Returns list of distributed traces",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of traces to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Traces retrieved successfully",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function traces(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 100);
        
        $traces = [];
        $traceKeys = Cache::get('monitoring:traces:keys', []);
        
        foreach (array_slice($traceKeys, -$limit) as $traceId) {
            $trace = Cache::get("trace:{$traceId}");
            if ($trace) {
                $traces[] = $trace;
            }
        }
        
        return response()->json([
            'traces' => $traces,
            'count' => count($traces),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/monitoring/trace/{traceId}",
     *     operationId="getTrace",
     *     tags={"Monitoring"},
     *     summary="Get specific trace details",
     *     description="Returns detailed information about a specific trace",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="traceId",
     *         in="path",
     *         description="Trace ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Trace details retrieved",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Trace not found"
     *     )
     * )
     */
    public function trace(string $traceId, DistributedTracer $tracer): JsonResponse
    {
        $summary = $tracer->getTraceSummary($traceId);
        
        if ($summary['spanCount'] === 0) {
            return response()->json(['error' => 'Trace not found'], 404);
        }
        
        $spans = Cache::get("trace:spans:{$traceId}", []);
        
        return response()->json([
            'summary' => $summary,
            'spans' => $spans,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/monitoring/alerts",
     *     operationId="getAlerts",
     *     tags={"Monitoring"},
     *     summary="Get active alerts",
     *     description="Returns list of active monitoring alerts",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="severity",
     *         in="query",
     *         description="Filter by severity",
     *         required=false,
     *         @OA\Schema(type="string", enum={"critical", "error", "warning", "info"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alerts retrieved successfully",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
    public function alerts(Request $request): JsonResponse
    {
        $query = \DB::table('monitoring_alerts')
            ->where('acknowledged', false)
            ->orderBy('created_at', 'desc');
        
        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        
        $alerts = $query->limit(100)->get();
        
        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/monitoring/alerts/{alertId}/acknowledge",
     *     operationId="acknowledgeAlert",
     *     tags={"Monitoring"},
     *     summary="Acknowledge an alert",
     *     description="Marks an alert as acknowledged",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="alertId",
     *         in="path",
     *         description="Alert ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Alert acknowledged",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="alert_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Alert not found"
     *     )
     * )
     */
    public function acknowledgeAlert(int $alertId): JsonResponse
    {
        $updated = \DB::table('monitoring_alerts')
            ->where('id', $alertId)
            ->update([
                'acknowledged' => true,
                'acknowledged_by' => auth()->id(),
                'acknowledged_at' => now(),
                'updated_at' => now(),
            ]);
        
        if (!$updated) {
            return response()->json(['error' => 'Alert not found'], 404);
        }
        
        return response()->json([
            'message' => 'Alert acknowledged',
            'alert_id' => $alertId,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/monitoring/workflow/start",
     *     operationId="startMonitoringWorkflow",
     *     tags={"Monitoring"},
     *     summary="Start monitoring workflow",
     *     description="Starts the automated monitoring workflow",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="interval", type="integer", default=60),
     *             @OA\Property(property="max_iterations", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workflow started",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="workflow_id", type="string")
     *         )
     *     )
     * )
     */
    public function startWorkflow(Request $request): JsonResponse
    {
        $config = $request->validate([
            'interval' => 'sometimes|integer|min:10|max:3600',
            'max_iterations' => 'sometimes|nullable|integer|min:1',
        ]);
        
        $workflow = WorkflowStub::make(MonitoringWorkflow::class);
        $workflowId = $workflow->id();
        
        $workflow->start($config);
        
        Cache::put('monitoring:workflow:id', $workflowId, now()->addDays(7));
        
        return response()->json([
            'message' => 'Monitoring workflow started',
            'workflow_id' => $workflowId,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/monitoring/workflow/stop",
     *     operationId="stopMonitoringWorkflow",
     *     tags={"Monitoring"},
     *     summary="Stop monitoring workflow",
     *     description="Stops the automated monitoring workflow",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Workflow stopped",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No active workflow found"
     *     )
     * )
     */
    public function stopWorkflow(): JsonResponse
    {
        $workflowId = Cache::get('monitoring:workflow:id');
        
        if (!$workflowId) {
            return response()->json(['error' => 'No active workflow found'], 404);
        }
        
        $workflow = WorkflowStub::load($workflowId);
        $workflow->signal('stop');
        
        Cache::forget('monitoring:workflow:id');
        
        return response()->json([
            'message' => 'Monitoring workflow stopped',
        ]);
    }
}