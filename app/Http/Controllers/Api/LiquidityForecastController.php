<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Treasury\Services\LiquidityForecastingService;
use App\Domain\Treasury\Workflows\LiquidityForecastingWorkflow;
use App\Http\Controllers\Controller;
use Exception;
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
     * @OA\Post(
     *     path="/api/v1/treasury/liquidity-forecast/generate",
     *     operationId="liquidityForecastGenerate",
     *     summary="Generate liquidity forecast",
     *     description="Generates a comprehensive liquidity forecast with risk metrics, scenario analysis, and actionable recommendations.",
     *     tags={"Treasury"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"treasury_id"},
     *             @OA\Property(property="treasury_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000", description="Treasury account ID"),
     *             @OA\Property(property="forecast_days", type="integer", minimum=1, maximum=365, example=30, description="Number of days to forecast (default: 30)"),
     *             @OA\Property(property="scenarios", type="array", description="Custom scenarios for stress testing",
     *                 @OA\Items(
     *                     @OA\Property(property="description", type="string", example="Market downturn"),
     *                     @OA\Property(property="inflow_adjustment", type="number", format="float", minimum=0, maximum=2, example=0.7),
     *                     @OA\Property(property="outflow_adjustment", type="number", format="float", minimum=0, maximum=3, example=1.5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forecast generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="treasury_id", type="string", format="uuid"),
     *             @OA\Property(property="forecast_period", type="integer", example=30),
     *             @OA\Property(property="generated_at", type="string", format="date-time"),
     *             @OA\Property(property="base_forecast", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="scenarios", type="object"),
     *             @OA\Property(property="risk_metrics", type="object"),
     *             @OA\Property(property="alerts", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="confidence_level", type="number", format="float", example=0.85),
     *             @OA\Property(property="recommendations", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Failed to generate forecast")
     * )
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
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to generate forecast',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current liquidity position.
     *
     * @OA\Get(
     *     path="/api/v1/treasury/liquidity-forecast/{treasuryId}/current",
     *     operationId="liquidityForecastCurrent",
     *     summary="Get current liquidity position",
     *     description="Calculates real-time liquidity metrics and coverage ratios for a treasury account.",
     *     tags={"Treasury"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="treasuryId",
     *         in="path",
     *         required=true,
     *         description="Treasury account ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Current liquidity position",
     *         @OA\JsonContent(
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="available_liquidity", type="number", format="float", example=1000000.00),
     *             @OA\Property(property="committed_outflows_24h", type="number", format="float", example=50000.00),
     *             @OA\Property(property="expected_inflows_24h", type="number", format="float", example=75000.00),
     *             @OA\Property(property="net_position_24h", type="number", format="float", example=1025000.00),
     *             @OA\Property(property="coverage_ratio", type="number", format="float", example=20.0),
     *             @OA\Property(property="status", type="string", example="excellent"),
     *             @OA\Property(property="buffer_days", type="integer", example=45)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Failed to calculate liquidity")
     * )
     */
    public function getCurrentLiquidity(string $treasuryId): JsonResponse
    {
        try {
            $liquidity = $this->forecastingService->calculateCurrentLiquidity($treasuryId);

            return response()->json($liquidity);
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to calculate liquidity',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start automated forecasting workflow.
     *
     * @OA\Post(
     *     path="/api/v1/treasury/liquidity-forecast/workflow/start",
     *     operationId="liquidityForecastWorkflowStart",
     *     summary="Start forecasting workflow",
     *     description="Initializes a continuous liquidity monitoring and forecasting workflow with configurable update intervals and automatic mitigation.",
     *     tags={"Treasury"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"treasury_id"},
     *             @OA\Property(property="treasury_id", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000", description="Treasury account ID"),
     *             @OA\Property(property="forecast_days", type="integer", minimum=1, maximum=365, example=30, description="Days to forecast (default: 30)"),
     *             @OA\Property(property="update_interval_hours", type="integer", minimum=1, maximum=24, example=6, description="Hours between forecast updates (default: 6)"),
     *             @OA\Property(property="auto_mitigation", type="boolean", example=false, description="Enable automatic mitigation actions (default: false)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workflow started",
     *         @OA\JsonContent(
     *             @OA\Property(property="workflow_id", type="string", example="liq-forecast-65a1b2c3"),
     *             @OA\Property(property="status", type="string", example="started"),
     *             @OA\Property(property="treasury_id", type="string", format="uuid"),
     *             @OA\Property(property="config", type="object",
     *                 @OA\Property(property="forecast_days", type="integer", example=30),
     *                 @OA\Property(property="update_interval_hours", type="integer", example=6),
     *                 @OA\Property(property="auto_mitigation", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Failed to start workflow")
     * )
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
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to start workflow',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get forecast alerts.
     *
     * @OA\Get(
     *     path="/api/v1/treasury/liquidity-forecast/{treasuryId}/alerts",
     *     operationId="liquidityForecastAlerts",
     *     summary="Get forecast alerts",
     *     description="Retrieves active liquidity alerts for a treasury account, optionally filtered by severity level.",
     *     tags={"Treasury"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="treasuryId",
     *         in="path",
     *         required=true,
     *         description="Treasury account ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="level",
     *         in="query",
     *         required=false,
     *         description="Filter by alert level",
     *         @OA\Schema(type="string", enum={"critical", "warning", "info"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of alerts",
     *         @OA\JsonContent(
     *             @OA\Property(property="alerts", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="level", type="string", example="critical"),
     *                     @OA\Property(property="type", type="string", example="lcr_breach"),
     *                     @OA\Property(property="message", type="string", example="Liquidity Coverage Ratio below regulatory minimum"),
     *                     @OA\Property(property="value", type="number", format="float", example=0.85),
     *                     @OA\Property(property="threshold", type="number", format="float", example=1.0),
     *                     @OA\Property(property="action_required", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=1),
     *             @OA\Property(property="has_critical", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Failed to retrieve alerts")
     * )
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
        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Failed to retrieve alerts',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
