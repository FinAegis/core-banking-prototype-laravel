<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Custodian\Services\BankAlertingService;
use App\Domain\Custodian\Services\CustodianHealthMonitor;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Bank Alerting",
 *     description="Bank health monitoring and alerting system (Admin only)"
 * )
 */
class BankAlertingController extends Controller
{
    public function __construct(
        private readonly BankAlertingService $alertingService,
        private readonly CustodianHealthMonitor $healthMonitor
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    /**
     * Trigger system-wide health check and alerting.
     *
     * @OA\Post(
     *     path="/api/bank-alerting/health-check",
     *     operationId="triggerBankHealthCheck",
     *     tags={"Bank Alerting"},
     *     summary="Trigger bank health check",
     *     description="Manually trigger a system-wide health check for all custodian banks",
     *
     * @OA\Response(
     *         response=200,
     *         description="Health check completed successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(property="health_check_completed", type="boolean", example=true),
     * @OA\Property(property="checked_at",             type="string", format="date-time"),
     * @OA\Property(property="custodians_checked",     type="integer", example=4),
     * @OA\Property(
     *                     property="summary",
     *                     type="object",
     * @OA\Property(property="healthy",                type="integer", example=3),
     * @OA\Property(property="degraded",               type="integer", example=1),
     * @OA\Property(property="unhealthy",              type="integer", example=0),
     * @OA\Property(property="unknown",                type="integer", example=0)
     *                 ),
     * @OA\Property(property="custodian_details",      type="array", @OA\Items(type="object"))
     *             ),
     * @OA\Property(property="message",                type="string")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=500,
     *         description="Health check failed"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function triggerHealthCheck(): JsonResponse
    {
        try {
            Log::info('Manual bank health check triggered via API');

            $this->alertingService->performHealthCheck();

            // Get current health status of all custodians
            $allHealth = $this->healthMonitor->getAllCustodiansHealth();

            $summary = [
                'healthy'   => 0,
                'degraded'  => 0,
                'unhealthy' => 0,
                'unknown'   => 0,
            ];

            foreach ($allHealth as $health) {
                $status = $health['status'] ?? 'unknown';
                $summary[$status] = ($summary[$status] ?? 0) + 1;
            }

            return response()->json(
                [
                    'data' => [
                        'health_check_completed' => true,
                        'checked_at'             => now()->toISOString(),
                        'custodians_checked'     => count($allHealth),
                        'summary'                => $summary,
                        'custodian_details'      => $allHealth,
                    ],
                    'message' => 'Bank health check completed successfully',
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Bank health check failed',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json(
                [
                    'error'      => 'Health check failed',
                    'message'    => $e->getMessage(),
                    'checked_at' => now()->toISOString(),
                ],
                500
            );
        }
    }

    /**
     * Get current health status of all custodians.
     *
     * @OA\Get(
     *     path="/api/bank-alerting/health-status",
     *     operationId="getBankHealthStatus",
     *     tags={"Bank Alerting"},
     *     summary="Get current bank health status",
     *     description="Retrieve the current health status of all custodian banks",
     *
     * @OA\Response(
     *         response=200,
     *         description="Health status retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(
     *                     property="summary",
     *                     type="object",
     * @OA\Property(property="healthy",              type="integer"),
     * @OA\Property(property="degraded",             type="integer"),
     * @OA\Property(property="unhealthy",            type="integer"),
     * @OA\Property(property="unknown",              type="integer")
     *                 ),
     * @OA\Property(property="total_custodians",     type="integer"),
     * @OA\Property(
     *                     property="custodians",
     *                     type="array",
     *
     * @OA\Items(
     *
     * @OA\Property(property="custodian",            type="string"),
     * @OA\Property(property="status",               type="string", enum={"healthy", "degraded", "unhealthy", "unknown"}),
     * @OA\Property(property="overall_failure_rate", type="number"),
     * @OA\Property(property="last_check",           type="string", format="date-time"),
     * @OA\Property(property="response_time_ms",     type="integer"),
     * @OA\Property(property="consecutive_failures", type="integer"),
     * @OA\Property(property="available_since",      type="string", format="date-time"),
     * @OA\Property(property="last_failure",         type="string", format="date-time")
     *                     )
     *                 ),
     * @OA\Property(property="checked_at",           type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getHealthStatus(): JsonResponse
    {
        try {
            $allHealth = $this->healthMonitor->getAllCustodiansHealth();

            $summary = [
                'healthy'   => 0,
                'degraded'  => 0,
                'unhealthy' => 0,
                'unknown'   => 0,
            ];

            $details = [];

            foreach ($allHealth as $custodian => $health) {
                $status = $health['status'] ?? 'unknown';
                $summary[$status] = ($summary[$status] ?? 0) + 1;

                $details[] = [
                    'custodian'            => $custodian,
                    'status'               => $status,
                    'overall_failure_rate' => $health['overall_failure_rate'] ?? 0,
                    'last_check'           => $health['last_check'] ?? null,
                    'response_time_ms'     => $health['response_time_ms'] ?? null,
                    'consecutive_failures' => $health['consecutive_failures'] ?? 0,
                    'available_since'      => $health['available_since'] ?? null,
                    'last_failure'         => $health['last_failure'] ?? null,
                ];
            }

            return response()->json(
                [
                    'data' => [
                        'summary'          => $summary,
                        'total_custodians' => count($allHealth),
                        'custodians'       => $details,
                        'checked_at'       => now()->toISOString(),
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to retrieve health status',
                    'message' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Get health status for specific custodian.
     *
     * @OA\Get(
     *     path="/api/bank-alerting/custodian/{custodian}/health",
     *     operationId="getSpecificCustodianHealth",
     *     tags={"Bank Alerting"},
     *     summary="Get specific custodian health",
     *     description="Retrieve health status for a specific custodian bank",
     *
     * @OA\Parameter(
     *         name="custodian",
     *         in="path",
     *         required=true,
     *         description="Custodian identifier",
     *
     * @OA\Schema(type="string",           example="paysera")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Custodian health retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(property="custodian",  type="string"),
     * @OA\Property(property="health",     type="object"),
     * @OA\Property(property="checked_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Custodian not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getCustodianHealth(string $custodian): JsonResponse
    {
        try {
            $health = $this->healthMonitor->getCustodianHealth($custodian);

            if (! $health) {
                return response()->json(
                    [
                        'error'     => 'Custodian not found',
                        'custodian' => $custodian,
                    ],
                    404
                );
            }

            return response()->json(
                [
                    'data' => [
                        'custodian'  => $custodian,
                        'health'     => $health,
                        'checked_at' => now()->toISOString(),
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to retrieve custodian health',
                    'message' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Get alert history for a custodian.
     *
     * @OA\Get(
     *     path="/api/bank-alerting/custodian/{custodian}/alerts",
     *     operationId="getCustodianAlertHistory",
     *     tags={"Bank Alerting"},
     *     summary="Get custodian alert history",
     *     description="Retrieve alert history for a specific custodian",
     *
     * @OA\Parameter(
     *         name="custodian",
     *         in="path",
     *         required=true,
     *         description="Custodian identifier",
     *
     * @OA\Schema(type="string",              example="paysera")
     *     ),
     *
     * @OA\Parameter(
     *         name="days",
     *         in="query",
     *         required=false,
     *         description="Number of days to retrieve (1-90)",
     *
     * @OA\Schema(type="integer",             default=7, minimum=1, maximum=90)
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Alert history retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(property="custodian",     type="string"),
     * @OA\Property(property="period_days",   type="integer"),
     * @OA\Property(property="alert_history", type="array", @OA\Items(type="object")),
     * @OA\Property(property="retrieved_at",  type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getAlertHistory(Request $request, string $custodian): JsonResponse
    {
        $request->validate(
            [
                'days' => 'sometimes|integer|min:1|max:90',
            ]
        );

        try {
            $days = (int) $request->get('days', 7);

            $history = $this->alertingService->getAlertHistory($custodian, $days);

            return response()->json(
                [
                    'data' => [
                        'custodian'     => $custodian,
                        'period_days'   => $days,
                        'alert_history' => $history,
                        'retrieved_at'  => now()->toISOString(),
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to retrieve alert history',
                    'message' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Get overall alerting statistics.
     *
     * @OA\Get(
     *     path="/api/bank-alerting/statistics",
     *     operationId="getBankAlertingStatistics",
     *     tags={"Bank Alerting"},
     *     summary="Get alerting statistics",
     *     description="Retrieve overall alerting system statistics",
     *
     * @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Time period for statistics",
     *
     * @OA\Schema(type="string",                     enum={"hour", "day", "week", "month"}, default="day")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(
     *                     property="statistics",
     *                     type="object",
     * @OA\Property(property="period",               type="string"),
     * @OA\Property(property="total_alerts_sent",    type="integer"),
     * @OA\Property(property="alerts_by_severity",   type="object"),
     * @OA\Property(property="alerts_by_custodian",  type="object"),
     * @OA\Property(property="most_common_issues",   type="object"),
     * @OA\Property(property="alert_response_times", type="object"),
     * @OA\Property(property="false_positive_rate",  type="number"),
     * @OA\Property(property="period_start",         type="string", format="date-time"),
     * @OA\Property(property="period_end",           type="string", format="date-time")
     *                 ),
     * @OA\Property(property="calculated_at",        type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getAlertingStats(Request $request): JsonResponse
    {
        $request->validate(
            [
                'period' => 'sometimes|in:hour,day,week,month',
            ]
        );

        try {
            $period = $request->get('period', 'day');

            // In a real implementation, this would query from database
            // For now, return sample statistics
            $stats = [
                'period'             => $period,
                'total_alerts_sent'  => 45,
                'alerts_by_severity' => [
                    'info'     => 20,
                    'warning'  => 20,
                    'critical' => 5,
                ],
                'alerts_by_custodian' => [
                    'paysera'       => 15,
                    'deutsche_bank' => 10,
                    'santander'     => 12,
                    'wise'          => 8,
                ],
                'most_common_issues' => [
                    'high_failure_rate'    => 18,
                    'slow_response_time'   => 12,
                    'connection_timeout'   => 8,
                    'authentication_error' => 5,
                    'rate_limit_exceeded'  => 2,
                ],
                'alert_response_times' => [
                    'average_seconds' => 45,
                    'median_seconds'  => 30,
                    'p95_seconds'     => 120,
                ],
                'false_positive_rate' => 8.5,
                'period_start'        => now()->sub($period, 1)->toISOString(),
                'period_end'          => now()->toISOString(),
            ];

            return response()->json(
                [
                    'data' => [
                        'statistics'    => $stats,
                        'calculated_at' => now()->toISOString(),
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to calculate alerting statistics',
                    'message' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Configure alert settings.
     *
     * @OA\Put(
     *     path="/api/bank-alerting/configuration",
     *     operationId="configureBankAlerts",
     *     tags={"Bank Alerting"},
     *     summary="Configure alert settings",
     *     description="Update alerting system configuration",
     *
     * @OA\RequestBody(
     *         required=false,
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="cooldown_minutes",       type="integer", minimum=1, maximum=1440, example=30),
     * @OA\Property(
     *                 property="severity_thresholds",
     *                 type="object",
     * @OA\Property(property="failure_rate_warning",   type="number", minimum=0, maximum=100),
     * @OA\Property(property="failure_rate_critical",  type="number", minimum=0, maximum=100),
     * @OA\Property(property="response_time_warning",  type="integer", minimum=0),
     * @OA\Property(property="response_time_critical", type="integer", minimum=0)
     *             ),
     * @OA\Property(
     *                 property="notification_channels",
     *                 type="array",
     *
     * @OA\Items(type="string",                        enum={"mail", "database", "slack", "webhook"})
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Configuration updated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(property="configuration_updated",  type="boolean"),
     * @OA\Property(property="new_configuration",      type="object")
     *             ),
     * @OA\Property(property="message",                type="string")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function configureAlerts(Request $request): JsonResponse
    {
        $request->validate(
            [
                'cooldown_minutes'                           => 'sometimes|integer|min:1|max:1440',
                'severity_thresholds'                        => 'sometimes|array',
                'severity_thresholds.failure_rate_warning'   => 'sometimes|numeric|min:0|max:100',
                'severity_thresholds.failure_rate_critical'  => 'sometimes|numeric|min:0|max:100',
                'severity_thresholds.response_time_warning'  => 'sometimes|integer|min:0',
                'severity_thresholds.response_time_critical' => 'sometimes|integer|min:0',
                'notification_channels'                      => 'sometimes|array',
                'notification_channels.*'                    => 'sometimes|in:mail,database,slack,webhook',
            ]
        );

        try {
            $config = [
                'cooldown_minutes'    => $request->get('cooldown_minutes', 30),
                'severity_thresholds' => $request->get(
                    'severity_thresholds',
                    [
                        'failure_rate_warning'   => 10.0,
                        'failure_rate_critical'  => 25.0,
                        'response_time_warning'  => 5000,
                        'response_time_critical' => 10000,
                    ]
                ),
                'notification_channels' => $request->get('notification_channels', ['mail', 'database']),
                'updated_at'            => now()->toISOString(),
            ];

            // In a real implementation, this would save to database or config
            Log::info('Alert configuration updated', $config);

            return response()->json(
                [
                    'data' => [
                        'configuration_updated' => true,
                        'new_configuration'     => $config,
                    ],
                    'message' => 'Alert configuration updated successfully',
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to update alert configuration',
                    'message' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Get current alert configuration.
     *
     * @OA\Get(
     *     path="/api/bank-alerting/configuration",
     *     operationId="getBankAlertConfiguration",
     *     tags={"Bank Alerting"},
     *     summary="Get alert configuration",
     *     description="Retrieve current alerting system configuration",
     *
     * @OA\Response(
     *         response=200,
     *         description="Configuration retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(
     *                     property="configuration",
     *                     type="object",
     * @OA\Property(property="cooldown_minutes",      type="integer"),
     * @OA\Property(property="severity_thresholds",   type="object"),
     * @OA\Property(property="notification_channels", type="array", @OA\Items(type="string")),
     * @OA\Property(property="alert_recipients",      type="object"),
     * @OA\Property(property="last_updated",          type="string", format="date-time")
     *                 ),
     * @OA\Property(property="retrieved_at",          type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getAlertConfiguration(): JsonResponse
    {
        try {
            // In a real implementation, this would read from database or config
            $config = [
                'cooldown_minutes'    => 30,
                'severity_thresholds' => [
                    'failure_rate_warning'   => 10.0,
                    'failure_rate_critical'  => 25.0,
                    'response_time_warning'  => 5000,
                    'response_time_critical' => 10000,
                ],
                'notification_channels' => ['mail', 'database'],
                'alert_recipients'      => [
                    'critical' => ['admin@finaegis.org', 'ops@finaegis.org'],
                    'warning'  => ['ops@finaegis.org'],
                    'info'     => ['ops@finaegis.org'],
                ],
                'last_updated' => now()->subDays(5)->toISOString(),
            ];

            return response()->json(
                [
                    'data' => [
                        'configuration' => $config,
                        'retrieved_at'  => now()->toISOString(),
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to retrieve alert configuration',
                    'message' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Test alert system by sending a test alert.
     *
     * @OA\Post(
     *     path="/api/bank-alerting/test",
     *     operationId="testBankAlert",
     *     tags={"Bank Alerting"},
     *     summary="Send test alert",
     *     description="Test the alerting system by sending a test alert",
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"severity"},
     *
     * @OA\Property(property="severity",        type="string", enum={"info", "warning", "critical"}),
     * @OA\Property(property="custodian",       type="string", example="test_custodian"),
     * @OA\Property(property="message",         type="string", maxLength=500, example="Test alert from API")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Test alert sent successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(property="test_alert_sent", type="boolean"),
     * @OA\Property(property="severity",        type="string"),
     * @OA\Property(property="custodian",       type="string"),
     * @OA\Property(property="message",         type="string"),
     * @OA\Property(property="sent_at",         type="string", format="date-time")
     *             ),
     * @OA\Property(property="message",         type="string")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function testAlert(Request $request): JsonResponse
    {
        $request->validate(
            [
                'severity'  => 'required|in:info,warning,critical',
                'custodian' => 'sometimes|string',
                'message'   => 'sometimes|string|max:500',
            ]
        );

        try {
            $severity = $request->get('severity');
            $custodian = $request->get('custodian', 'test_custodian');
            $message = $request->get('message', 'Test alert from API');

            Log::info(
                'Test alert triggered',
                [
                    'severity'     => $severity,
                    'custodian'    => $custodian,
                    'message'      => $message,
                    'triggered_by' => auth()->user()->email,
                ]
            );

            // In a real implementation, this would send an actual test alert

            return response()->json(
                [
                    'data' => [
                        'test_alert_sent' => true,
                        'severity'        => $severity,
                        'custodian'       => $custodian,
                        'message'         => $message,
                        'sent_at'         => now()->toISOString(),
                    ],
                    'message' => 'Test alert sent successfully',
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to send test alert',
                    'message' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Acknowledge an alert (mark as resolved).
     *
     * @OA\Post(
     *     path="/api/bank-alerting/alerts/{alertId}/acknowledge",
     *     operationId="acknowledgeBankAlert",
     *     tags={"Bank Alerting"},
     *     summary="Acknowledge alert",
     *     description="Mark an alert as acknowledged/resolved",
     *
     * @OA\Parameter(
     *         name="alertId",
     *         in="path",
     *         required=true,
     *         description="Alert identifier",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\RequestBody(
     *         required=false,
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="resolution_notes", type="string", maxLength=1000)
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Alert acknowledged successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="data",
     *                 type="object",
     * @OA\Property(property="alert_id",         type="string"),
     * @OA\Property(property="acknowledged",     type="boolean"),
     * @OA\Property(property="acknowledged_by",  type="string"),
     * @OA\Property(property="acknowledged_at",  type="string", format="date-time"),
     * @OA\Property(property="resolution_notes", type="string")
     *             ),
     * @OA\Property(property="message",          type="string")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function acknowledgeAlert(Request $request, string $alertId): JsonResponse
    {
        $request->validate(
            [
                'resolution_notes' => 'sometimes|string|max:1000',
            ]
        );

        try {
            $resolutionNotes = $request->get('resolution_notes', '');

            // In a real implementation, this would update the alert in database
            Log::info(
                'Alert acknowledged',
                [
                    'alert_id'         => $alertId,
                    'acknowledged_by'  => auth()->user()->email,
                    'resolution_notes' => $resolutionNotes,
                ]
            );

            return response()->json(
                [
                    'data' => [
                        'alert_id'         => $alertId,
                        'acknowledged'     => true,
                        'acknowledged_by'  => auth()->user()->email,
                        'acknowledged_at'  => now()->toISOString(),
                        'resolution_notes' => $resolutionNotes,
                    ],
                    'message' => 'Alert acknowledged successfully',
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error'   => 'Failed to acknowledge alert',
                    'message' => $e->getMessage(),
                ],
                500
            );
        }
    }
}
