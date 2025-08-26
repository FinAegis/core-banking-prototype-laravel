<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Treasury\Services\LiquidityForecastingService;
use App\Domain\Treasury\Workflows\LiquidityForecastingWorkflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Workflow\WorkflowStub;

/**
 * API Controller for Liquidity Forecasting.
 *
 * @group Treasury Management
 */
class LiquidityForecastController extends Controller
{
    public function __construct(
        private readonly LiquidityForecastingService $forecastingService
    ) {
    }

    /**
     * Generate liquidity forecast.
     *
     * Generate a comprehensive liquidity forecast with risk metrics and scenarios
     *
     * @bodyParam treasury_id string required The treasury account ID
     * @bodyParam forecast_days integer The number of days to forecast (default: 30, max: 365)
     * @bodyParam scenarios array Optional custom scenarios for stress testing
     *
     * @response 200 {
     *   "treasury_id": "uuid",
     *   "forecast_period": 30,
     *   "generated_at": "2024-01-01T00:00:00Z",
     *   "base_forecast": [...],
     *   "scenarios": {...},
     *   "risk_metrics": {...},
     *   "alerts": [...],
     *   "confidence_level": 0.85,
     *   "recommendations": [...]
     * }
     */
    public function generateForecast(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'treasury_id'                    => 'required|uuid',
            'forecast_days'                  => 'integer|min:1|max:365',
            'scenarios'                      => 'array',
            'scenarios.*.description'        => 'required_with:scenarios|string',
            'scenarios.*.inflow_adjustment'  => 'required_with:scenarios|numeric|min:0|max:2',
            'scenarios.*.outflow_adjustment' => 'required_with:scenarios|numeric|min:0|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'    => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $forecast = $this->forecastingService->generateForecast(
                $request->input('treasury_id'),
                $request->input('forecast_days', 30),
                $request->input('scenarios', [])
            );

            return response()->json($forecast);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to generate forecast',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current liquidity position.
     *
     * Calculate real-time liquidity metrics and coverage ratios
     *
     * @urlParam treasury_id string required The treasury account ID
     *
     * @response 200 {
     *   "timestamp": "2024-01-01T00:00:00Z",
     *   "available_liquidity": 1000000.00,
     *   "committed_outflows_24h": 50000.00,
     *   "expected_inflows_24h": 75000.00,
     *   "net_position_24h": 1025000.00,
     *   "coverage_ratio": 20.0,
     *   "status": "excellent",
     *   "buffer_days": 45
     * }
     */
    public function getCurrentLiquidity(string $treasuryId): JsonResponse
    {
        try {
            $liquidity = $this->forecastingService->calculateCurrentLiquidity($treasuryId);

            return response()->json($liquidity);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to calculate liquidity',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start automated forecasting workflow.
     *
     * Initialize continuous liquidity monitoring and forecasting
     *
     * @bodyParam treasury_id string required The treasury account ID
     * @bodyParam forecast_days integer Days to forecast (default: 30)
     * @bodyParam update_interval_hours integer Hours between updates (default: 6)
     * @bodyParam auto_mitigation boolean Enable automatic mitigation (default: false)
     *
     * @response 200 {
     *   "workflow_id": "workflow-uuid",
     *   "status": "started",
     *   "treasury_id": "uuid",
     *   "config": {...}
     * }
     */
    public function startForecastingWorkflow(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'treasury_id'           => 'required|uuid',
            'forecast_days'         => 'integer|min:1|max:365',
            'update_interval_hours' => 'integer|min:1|max:24',
            'auto_mitigation'       => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error'    => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        try {
            $workflow = WorkflowStub::make(LiquidityForecastingWorkflow::class);
            $workflowId = uniqid('liq-forecast-');

            $workflow->start(
                $request->input('treasury_id'),
                [
                    'forecast_days'         => $request->input('forecast_days', 30),
                    'update_interval_hours' => $request->input('update_interval_hours', 6),
                    'auto_mitigation'       => $request->input('auto_mitigation', false),
                ]
            );

            return response()->json([
                'workflow_id' => $workflowId,
                'status'      => 'started',
                'treasury_id' => $request->input('treasury_id'),
                'config'      => $request->only(['forecast_days', 'update_interval_hours', 'auto_mitigation']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to start workflow',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get forecast alerts.
     *
     * Retrieve active liquidity alerts for a treasury account
     *
     * @urlParam treasury_id string required The treasury account ID
     * @queryParam level string Filter by alert level (critical, warning, info)
     *
     * @response 200 {
     *   "alerts": [
     *     {
     *       "level": "critical",
     *       "type": "lcr_breach",
     *       "message": "Liquidity Coverage Ratio below regulatory minimum",
     *       "value": 0.85,
     *       "threshold": 1.0,
     *       "action_required": true
     *     }
     *   ],
     *   "count": 1,
     *   "has_critical": true
     * }
     */
    public function getAlerts(Request $request, string $treasuryId): JsonResponse
    {
        $level = $request->query('level');

        try {
            // Generate fresh forecast to get current alerts
            $forecast = $this->forecastingService->generateForecast($treasuryId, 7);
            $alerts = $forecast['alerts'] ?? [];

            // Filter by level if specified
            if ($level) {
                $alerts = array_filter($alerts, fn ($alert) => $alert['level'] === $level);
            }

            return response()->json([
                'alerts'       => array_values($alerts),
                'count'        => count($alerts),
                'has_critical' => ! empty(array_filter($alerts, fn ($a) => $a['level'] === 'critical')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to retrieve alerts',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
