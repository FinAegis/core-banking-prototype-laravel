<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Custodian\Services\DailyReconciliationService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Daily Reconciliation",
 *     description="Bank account reconciliation and reporting system (Admin only)"
 * )
 */
class DailyReconciliationController extends Controller
{
    public function __construct(
        private readonly DailyReconciliationService $reconciliationService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    /**
     * Trigger daily reconciliation process
     * 
     * @OA\Post(
     *     path="/api/reconciliation/trigger",
     *     operationId="triggerReconciliation",
     *     tags={"Daily Reconciliation"},
     *     summary="Trigger manual reconciliation process",
     *     description="Manually triggers the daily bank account reconciliation process (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Reconciliation completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="reconciliation_triggered", type="boolean", example=true),
     *                 @OA\Property(property="triggered_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *                 @OA\Property(property="report", type="object", description="Reconciliation report details")
     *             ),
     *             @OA\Property(property="message", type="string", example="Daily reconciliation completed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Reconciliation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Reconciliation failed"),
     *             @OA\Property(property="message", type="string", example="Connection to custodian failed"),
     *             @OA\Property(property="triggered_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function triggerReconciliation(): JsonResponse
    {
        try {
            Log::info('Manual reconciliation triggered via API');
            
            $report = $this->reconciliationService->performDailyReconciliation();
            
            return response()->json([
                'data' => [
                    'reconciliation_triggered' => true,
                    'triggered_at' => now()->toISOString(),
                    'report' => $report,
                ],
                'message' => 'Daily reconciliation completed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Manual reconciliation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Reconciliation failed',
                'message' => $e->getMessage(),
                'triggered_at' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get latest reconciliation report
     * 
     * @OA\Get(
     *     path="/api/reconciliation/latest",
     *     operationId="getLatestReconciliationReport",
     *     tags={"Daily Reconciliation"},
     *     summary="Get the latest reconciliation report",
     *     description="Retrieves the most recent reconciliation report (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Latest report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="report", type="object", description="Full reconciliation report details"),
     *                 @OA\Property(property="retrieved_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No reconciliation reports found",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="null"),
     *             @OA\Property(property="message", type="string", example="No reconciliation reports found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve report",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function getLatestReport(): JsonResponse
    {
        try {
            $report = $this->reconciliationService->getLatestReport();
            
            if (!$report) {
                return response()->json([
                    'data' => null,
                    'message' => 'No reconciliation reports found'
                ], 404);
            }
            
            return response()->json([
                'data' => [
                    'report' => $report,
                    'retrieved_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve latest report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reconciliation history
     * 
     * @OA\Get(
     *     path="/api/reconciliation/history",
     *     operationId="getReconciliationHistory",
     *     tags={"Daily Reconciliation"},
     *     summary="Get reconciliation history",
     *     description="Retrieves historical reconciliation reports with filtering options (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         required=false,
     *         description="Number of days to look back (1-90, default: 30)",
     *         @OA\Schema(type="integer", minimum=1, maximum=90, default=30)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of reports to return (1-50, default: 20)",
     *         @OA\Schema(type="integer", minimum=1, maximum=50, default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="History retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="reports",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
     *                         @OA\Property(property="summary", type="object"),
     *                         @OA\Property(property="discrepancy_count", type="integer", example=2),
     *                         @OA\Property(property="recommendations_count", type="integer", example=3),
     *                         @OA\Property(property="file_path", type="string", example="reconciliation-2024-01-15.json"),
     *                         @OA\Property(property="file_size", type="integer", example=15248),
     *                         @OA\Property(property="generated_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=15),
     *                 @OA\Property(property="period_days", type="integer", example=30),
     *                 @OA\Property(property="retrieved_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve history"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function getHistory(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'sometimes|integer|min:1|max:90',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        try {
            $days = $request->get('days', 30);
            $limit = $request->get('limit', 20);
            
            $files = glob(storage_path('app/reconciliation/reconciliation-*.json'));
            
            if (empty($files)) {
                return response()->json([
                    'data' => [
                        'reports' => [],
                        'total' => 0,
                    ],
                    'message' => 'No reconciliation reports found'
                ]);
            }
            
            // Sort by filename (date) descending
            rsort($files);
            
            $reports = [];
            $cutoffDate = now()->subDays($days);
            
            foreach (array_slice($files, 0, $limit) as $file) {
                $content = file_get_contents($file);
                $reportData = json_decode($content, true);
                
                if (!$reportData) {
                    continue;
                }
                
                $reportDate = Carbon::parse($reportData['summary']['date'] ?? 'now');
                
                if ($reportDate->isBefore($cutoffDate)) {
                    break;
                }
                
                $reports[] = [
                    'date' => $reportDate->toDateString(),
                    'summary' => $reportData['summary'] ?? [],
                    'discrepancy_count' => count($reportData['discrepancies'] ?? []),
                    'recommendations_count' => count($reportData['recommendations'] ?? []),
                    'file_path' => basename($file),
                    'file_size' => filesize($file),
                    'generated_at' => $reportData['generated_at'] ?? null,
                ];
            }
            
            return response()->json([
                'data' => [
                    'reports' => $reports,
                    'total' => count($reports),
                    'period_days' => $days,
                    'retrieved_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve reconciliation history',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific reconciliation report by date
     * 
     * @OA\Get(
     *     path="/api/reconciliation/report/{date}",
     *     operationId="getReconciliationReportByDate",
     *     tags={"Daily Reconciliation"},
     *     summary="Get reconciliation report for specific date",
     *     description="Retrieves the reconciliation report for a specific date (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="date",
     *         in="path",
     *         required=true,
     *         description="Date in YYYY-MM-DD format",
     *         @OA\Schema(type="string", format="date", example="2024-01-15")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="report", type="object", description="Full reconciliation report"),
     *                 @OA\Property(
     *                     property="file_info",
     *                     type="object",
     *                     @OA\Property(property="size", type="integer"),
     *                     @OA\Property(property="modified_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="retrieved_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid date format",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid date format. Use YYYY-MM-DD format.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Report not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="date", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve report"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function getReportByDate(string $date): JsonResponse
    {
        try {
            // Validate date format
            $reportDate = Carbon::createFromFormat('Y-m-d', $date);
            if (!$reportDate) {
                return response()->json([
                    'error' => 'Invalid date format. Use YYYY-MM-DD format.'
                ], 400);
            }
            
            $filename = sprintf('reconciliation-%s.json', $date);
            $filePath = storage_path("app/reconciliation/{$filename}");
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'error' => 'Reconciliation report not found for the specified date',
                    'date' => $date
                ], 404);
            }
            
            $content = file_get_contents($filePath);
            $reportData = json_decode($content, true);
            
            if (!$reportData) {
                return response()->json([
                    'error' => 'Invalid report format'
                ], 500);
            }
            
            return response()->json([
                'data' => [
                    'date' => $date,
                    'report' => $reportData,
                    'file_info' => [
                        'size' => filesize($filePath),
                        'modified_at' => Carbon::createFromTimestamp(filemtime($filePath))->toISOString(),
                    ],
                    'retrieved_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve reconciliation report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reconciliation metrics summary
     * 
     * @OA\Get(
     *     path="/api/reconciliation/metrics",
     *     operationId="getReconciliationMetrics",
     *     tags={"Daily Reconciliation"},
     *     summary="Get reconciliation metrics and analytics",
     *     description="Retrieves summary metrics and analytics for reconciliation processes (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         required=false,
     *         description="Number of days to analyze (1-90, default: 30)",
     *         @OA\Schema(type="integer", minimum=1, maximum=90, default=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="metrics",
     *                     type="object",
     *                     @OA\Property(property="total_reconciliations", type="integer"),
     *                     @OA\Property(property="successful_reconciliations", type="integer"),
     *                     @OA\Property(property="failed_reconciliations", type="integer"),
     *                     @OA\Property(property="total_discrepancies", type="integer"),
     *                     @OA\Property(property="total_discrepancy_amount", type="number"),
     *                     @OA\Property(property="average_duration_minutes", type="number"),
     *                     @OA\Property(property="accounts_checked_total", type="integer"),
     *                     @OA\Property(property="average_discrepancies_per_run", type="number"),
     *                     @OA\Property(property="success_rate", type="number"),
     *                     @OA\Property(
     *                         property="discrepancy_types",
     *                         type="object",
     *                         additionalProperties={"type": "integer"}
     *                     ),
     *                     @OA\Property(
     *                         property="daily_trends",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="date", type="string", format="date"),
     *                             @OA\Property(property="discrepancies", type="integer"),
     *                             @OA\Property(property="accounts_checked", type="integer"),
     *                             @OA\Property(property="duration_minutes", type="number"),
     *                             @OA\Property(property="status", type="string")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="period_days", type="integer"),
     *                 @OA\Property(property="period_start", type="string", format="date"),
     *                 @OA\Property(property="period_end", type="string", format="date"),
     *                 @OA\Property(property="calculated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to calculate metrics"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'sometimes|integer|min:1|max:90',
        ]);

        try {
            $days = $request->get('days', 30);
            $cutoffDate = now()->subDays($days);
            
            $files = glob(storage_path('app/reconciliation/reconciliation-*.json'));
            
            if (empty($files)) {
                return response()->json([
                    'data' => [
                        'metrics' => [
                            'total_reconciliations' => 0,
                            'successful_reconciliations' => 0,
                            'failed_reconciliations' => 0,
                            'total_discrepancies' => 0,
                            'total_discrepancy_amount' => 0,
                            'average_duration_minutes' => 0,
                            'accounts_checked_total' => 0,
                        ],
                        'period_days' => $days,
                    ],
                    'message' => 'No reconciliation data found'
                ]);
            }
            
            $metrics = [
                'total_reconciliations' => 0,
                'successful_reconciliations' => 0,
                'failed_reconciliations' => 0,
                'total_discrepancies' => 0,
                'total_discrepancy_amount' => 0,
                'total_duration_minutes' => 0,
                'accounts_checked_total' => 0,
                'discrepancy_types' => [],
                'daily_trends' => [],
            ];
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $reportData = json_decode($content, true);
                
                if (!$reportData || !isset($reportData['summary'])) {
                    continue;
                }
                
                $summary = $reportData['summary'];
                $reportDate = Carbon::parse($summary['date'] ?? 'now');
                
                if ($reportDate->isBefore($cutoffDate)) {
                    continue;
                }
                
                $metrics['total_reconciliations']++;
                
                if (($summary['status'] ?? '') === 'completed') {
                    $metrics['successful_reconciliations']++;
                } else {
                    $metrics['failed_reconciliations']++;
                }
                
                $metrics['total_discrepancies'] += $summary['discrepancies_found'] ?? 0;
                $metrics['total_discrepancy_amount'] += $summary['total_discrepancy_amount'] ?? 0;
                $metrics['total_duration_minutes'] += $summary['duration_minutes'] ?? 0;
                $metrics['accounts_checked_total'] += $summary['accounts_checked'] ?? 0;
                
                // Track discrepancy types
                if (isset($reportData['discrepancies'])) {
                    foreach ($reportData['discrepancies'] as $discrepancy) {
                        $type = $discrepancy['type'] ?? 'unknown';
                        $metrics['discrepancy_types'][$type] = ($metrics['discrepancy_types'][$type] ?? 0) + 1;
                    }
                }
                
                // Daily trends
                $metrics['daily_trends'][] = [
                    'date' => $reportDate->toDateString(),
                    'discrepancies' => $summary['discrepancies_found'] ?? 0,
                    'accounts_checked' => $summary['accounts_checked'] ?? 0,
                    'duration_minutes' => $summary['duration_minutes'] ?? 0,
                    'status' => $summary['status'] ?? 'unknown',
                ];
            }
            
            // Calculate averages
            $metrics['average_duration_minutes'] = $metrics['total_reconciliations'] > 0 
                ? round($metrics['total_duration_minutes'] / $metrics['total_reconciliations'], 2)
                : 0;
            
            $metrics['average_discrepancies_per_run'] = $metrics['total_reconciliations'] > 0
                ? round($metrics['total_discrepancies'] / $metrics['total_reconciliations'], 2)
                : 0;
            
            $metrics['success_rate'] = $metrics['total_reconciliations'] > 0
                ? round(($metrics['successful_reconciliations'] / $metrics['total_reconciliations']) * 100, 2)
                : 0;
            
            // Sort daily trends by date
            usort($metrics['daily_trends'], function ($a, $b) {
                return strcmp($a['date'], $b['date']);
            });
            
            return response()->json([
                'data' => [
                    'metrics' => $metrics,
                    'period_days' => $days,
                    'period_start' => $cutoffDate->toDateString(),
                    'period_end' => now()->toDateString(),
                    'calculated_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to calculate reconciliation metrics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reconciliation status (whether process is currently running)
     * 
     * @OA\Get(
     *     path="/api/reconciliation/status",
     *     operationId="getReconciliationStatus",
     *     tags={"Daily Reconciliation"},
     *     summary="Get current reconciliation process status",
     *     description="Checks if reconciliation process is currently running and provides status information (Admin only)",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="is_running", type="boolean", example=false),
     *                 @OA\Property(property="last_run_date", type="string", format="date", nullable=true),
     *                 @OA\Property(property="next_scheduled_run", type="string", format="date-time"),
     *                 @OA\Property(property="status_checked_at", type="string", format="date-time"),
     *                 @OA\Property(property="started_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="running_duration_minutes", type="number", nullable=true),
     *                 @OA\Property(
     *                     property="last_run_summary",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="accounts_checked", type="integer"),
     *                     @OA\Property(property="discrepancies_found", type="integer"),
     *                     @OA\Property(property="duration_minutes", type="number")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to get status"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function getStatus(): JsonResponse
    {
        try {
            // Check if reconciliation process is currently running
            // This would typically check a lock file or database flag
            $lockFile = storage_path('app/locks/reconciliation.lock');
            $isRunning = file_exists($lockFile);
            
            $latestReport = $this->reconciliationService->getLatestReport();
            $lastRunDate = $latestReport ? ($latestReport['summary']['date'] ?? null) : null;
            
            $status = [
                'is_running' => $isRunning,
                'last_run_date' => $lastRunDate,
                'next_scheduled_run' => now()->addDay()->startOfDay()->setHour(2)->toISOString(), // Assuming daily at 2 AM
                'status_checked_at' => now()->toISOString(),
            ];
            
            if ($isRunning && file_exists($lockFile)) {
                $status['started_at'] = Carbon::createFromTimestamp(filemtime($lockFile))->toISOString();
                $status['running_duration_minutes'] = Carbon::createFromTimestamp(filemtime($lockFile))->diffInMinutes(now());
            }
            
            if ($latestReport) {
                $status['last_run_summary'] = [
                    'status' => $latestReport['summary']['status'] ?? 'unknown',
                    'accounts_checked' => $latestReport['summary']['accounts_checked'] ?? 0,
                    'discrepancies_found' => $latestReport['summary']['discrepancies_found'] ?? 0,
                    'duration_minutes' => $latestReport['summary']['duration_minutes'] ?? 0,
                ];
            }
            
            return response()->json([
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get reconciliation status',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}