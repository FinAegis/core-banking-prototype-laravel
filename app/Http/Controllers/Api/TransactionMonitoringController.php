<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\MonitoringRule;
use App\Domain\Compliance\Services\AlertManagementService;
use App\Domain\Compliance\Services\TransactionMonitoringService;
use App\Domain\Compliance\Streaming\TransactionStreamProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TransactionMonitoringController extends Controller
{
    public function __construct(
        private readonly TransactionMonitoringService $monitoringService,
        private readonly AlertManagementService $alertService,
        private readonly TransactionStreamProcessor $streamProcessor
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/transaction-monitoring",
     *     operationId="getMonitoredTransactions",
     *     tags={"Transaction Monitoring"},
     *     summary="Get monitored transactions",
     *     description="Retrieve transactions that are being monitored for compliance",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "reviewing", "cleared", "flagged"})
     *     ),
     *     @OA\Parameter(
     *         name="risk_level",
     *         in="query",
     *         description="Filter by risk level",
     *         required=false,
     *         @OA\Schema(type="string", enum={"low", "medium", "high", "critical"})
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of monitored transactions",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function getMonitoredTransactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'     => ['sometimes', 'string', Rule::in(['pending', 'reviewing', 'cleared', 'flagged'])],
            'risk_level' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'page'       => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Transaction::query()
            ->with(['account', 'account.user']);

        if (isset($validated['status'])) {
            /** @phpstan-ignore-next-line */
            $query->where('compliance_status', $validated['status']);
        }

        if (isset($validated['risk_level'])) {
            /** @phpstan-ignore-next-line */
            $query->where('risk_level', $validated['risk_level']);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'total'        => $transactions->total(),
                'per_page'     => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/transaction-monitoring/{id}",
     *     operationId="getTransactionDetails",
     *     tags={"Transaction Monitoring"},
     *     summary="Get transaction monitoring details",
     *     description="Retrieve detailed monitoring information for a specific transaction",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Transaction ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction monitoring details",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found"
     *     )
     * )
     */
    public function getTransactionDetails(string $id): JsonResponse
    {
        $transaction = Transaction::findOrFail($id);

        // Get monitoring details
        $monitoringDetails = $this->monitoringService->analyzeTransaction($transaction);

        // Get related alerts
        $alerts = ComplianceAlert::where('entity_type', 'transaction')
            ->where('entity_id', $transaction->id)
            ->get();

        return response()->json([
            'data' => [
                'transaction'       => $transaction,
                'monitoring'        => $monitoringDetails,
                'alerts'            => $alerts,
                'risk_score'        => $transaction->risk_score ?? 0,
                'patterns_detected' => is_string($transaction->patterns_detected)
                    ? json_decode($transaction->patterns_detected, true)
                    : ($transaction->patterns_detected ?? []),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/transaction-monitoring/{id}/flag",
     *     operationId="flagTransaction",
     *     tags={"Transaction Monitoring"},
     *     summary="Flag a transaction",
     *     description="Flag a transaction for compliance review",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Transaction ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", description="Reason for flagging"),
     *             @OA\Property(property="severity", type="string", enum={"low", "medium", "high", "critical"}),
     *             @OA\Property(property="notes", type="string", description="Additional notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction flagged successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function flagTransaction(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason'   => ['required', 'string', 'max:500'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'notes'    => ['sometimes', 'string', 'max:1000'],
        ]);

        $transaction = Transaction::findOrFail($id);

        DB::transaction(function () use ($transaction, $validated) {
            // Update transaction status
            $transaction->update([
                'compliance_status' => 'flagged',
                'risk_level'        => $validated['severity'],
                'flagged_at'        => now(),
                'flagged_by'        => auth()->id(),
                'flag_reason'       => $validated['reason'],
            ]);

            // Flag using the monitoring service to create/update the aggregate
            $this->monitoringService->flagTransaction(
                (string) $transaction->id,
                $validated['reason'],
                $validated['severity']
            );

            // Create alert
            $this->alertService->createAlert([
                'type'        => 'manual_flag',
                'severity'    => $validated['severity'],
                'entity_type' => 'transaction',
                'entity_id'   => $transaction->id,
                'description' => $validated['reason'],
                'details'     => [
                    'transaction_id' => $transaction->id,
                    'amount'         => $transaction->amount,
                    'notes'          => $validated['notes'] ?? null,
                    'flagged_by'     => auth()->user()->name,
                ],
            ]);
        });

        return response()->json([
            'message' => 'Transaction flagged successfully',
            'data'    => [
                'id'       => $transaction->id,
                'status'   => 'flagged',
                'severity' => $validated['severity'],
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/transaction-monitoring/{id}/clear",
     *     operationId="clearTransaction",
     *     tags={"Transaction Monitoring"},
     *     summary="Clear a transaction",
     *     description="Clear a transaction from compliance monitoring",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Transaction ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", description="Reason for clearing"),
     *             @OA\Property(property="reviewer", type="string", description="Reviewer ID"),
     *             @OA\Property(property="notes", type="string", description="Additional notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction cleared successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function clearTransaction(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reviewer' => ['required'],
            'notes'    => ['required', 'string', 'max:1000'],
        ]);

        $transaction = Transaction::findOrFail($id);

        // Clear the transaction - the service method expects ($transactionId, $reason, $notes)
        // The 'notes' field from the request is actually the reason for clearing
        $this->monitoringService->clearTransaction(
            (string) $transaction->id,
            $validated['notes'],  // This is the reason for clearing
            'Reviewed by: ' . $validated['reviewer']  // Additional notes about who reviewed it
        );

        return response()->json([
        'message' => 'Transaction cleared successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/transaction-monitoring/rules",
     *     operationId="getMonitoringRules",
     *     tags={"Transaction Monitoring"},
     *     summary="Get monitoring rules",
     *     description="Retrieve all transaction monitoring rules",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of monitoring rules",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function getRules(): JsonResponse
    {
        $rules = MonitoringRule::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        return response()->json([
            'data' => $rules->map(function ($rule) {
                return [
                    'id'                  => $rule->id,
                    'name'                => $rule->name,
                    'type'                => $rule->type,
                    'conditions'          => $rule->conditions,
                    'threshold'           => $rule->threshold,
                    'severity'            => $rule->severity,
                    'is_active'           => $rule->is_active,
                    'effectiveness_score' => $rule->effectiveness_score,
                    'false_positive_rate' => $rule->false_positive_rate,
                    'last_triggered_at'   => $rule->last_triggered_at,
                ];
            }),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/transaction-monitoring/rules",
     *     operationId="createMonitoringRule",
     *     tags={"Transaction Monitoring"},
     *     summary="Create monitoring rule",
     *     description="Create a new transaction monitoring rule",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "type", "conditions", "severity"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="conditions", type="object"),
     *             @OA\Property(property="threshold", type="number"),
     *             @OA\Property(property="severity", type="string", enum={"low", "medium", "high", "critical"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Rule created successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function createRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'string', 'max:50'],
            'conditions'  => ['required', 'array'],
            'threshold'   => ['sometimes', 'numeric', 'min:0'],
            'severity'    => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['sometimes', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $rule = MonitoringRule::create([
            'name'                => $validated['name'],
            'type'                => $validated['type'],
            'conditions'          => $validated['conditions'],
            'threshold'           => $validated['threshold'] ?? null,
            'severity'            => $validated['severity'],
            'description'         => $validated['description'] ?? null,
            'is_active'           => $validated['is_active'] ?? true,
            'priority'            => $this->calculateRulePriority($validated['severity']),
            'effectiveness_score' => 50.0, // Start with neutral score
            'false_positive_rate' => 0.0,
            'created_by'          => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Rule created successfully',
            'data'    => $rule,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/transaction-monitoring/rules/{id}",
     *     operationId="updateMonitoringRule",
     *     tags={"Transaction Monitoring"},
     *     summary="Update monitoring rule",
     *     description="Update an existing transaction monitoring rule",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rule ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rule updated successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function updateRule(string $id, Request $request): JsonResponse
    {
        $rule = MonitoringRule::findOrFail($id);

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'type'        => ['sometimes', 'string', 'max:50'],
            'conditions'  => ['sometimes', 'array'],
            'threshold'   => ['sometimes', 'numeric', 'min:0'],
            'severity'    => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['sometimes', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['severity'])) {
            $validated['priority'] = $this->calculateRulePriority($validated['severity']);
        }

        $rule->update($validated);

        return response()->json([
            'message' => 'Rule updated successfully',
            'data'    => $rule,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/transaction-monitoring/rules/{id}",
     *     operationId="deleteMonitoringRule",
     *     tags={"Transaction Monitoring"},
     *     summary="Delete monitoring rule",
     *     description="Delete a transaction monitoring rule",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Rule ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rule deleted successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function deleteRule(string $id): JsonResponse
    {
        $rule = MonitoringRule::findOrFail($id);

        // Soft delete to maintain audit trail
        $rule->update(['is_active' => false]);
        $rule->delete();

        return response()->json([
            'message' => 'Rule deleted successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/transaction-monitoring/patterns",
     *     operationId="getDetectedPatterns",
     *     tags={"Transaction Monitoring"},
     *     summary="Get detected patterns",
     *     description="Retrieve patterns detected in transaction monitoring",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Pattern type filter",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of detected patterns",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function getPatterns(Request $request): JsonResponse
    {
        $type = $request->query('type');

        // Get pattern statistics from cache
        $patterns = Cache::get('compliance:patterns:statistics', []);

        if ($type) {
            $patterns = array_filter($patterns, function ($pattern) use ($type) {
                return $pattern['type'] === $type;
            });
        }

        // Get available pattern types from the engine
        $availableTypes = [
            'structuring',
            'rapid_movement',
            'round_amount',
            'unusual_pattern',
            'high_risk_country',
            'dormant_account',
            'sudden_activity',
            'cross_border',
        ];

        return response()->json([
            'data' => [
                'patterns'        => array_values($patterns),
                'available_types' => $availableTypes,
                'statistics'      => [
                    'total_detected' => count($patterns),
                    'last_updated'   => Cache::get('compliance:patterns:last_updated', now()),
                ],
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/transaction-monitoring/thresholds",
     *     operationId="getMonitoringThresholds",
     *     tags={"Transaction Monitoring"},
     *     summary="Get monitoring thresholds",
     *     description="Retrieve current transaction monitoring thresholds",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current monitoring thresholds",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function getThresholds(): JsonResponse
    {
        // Get current thresholds from configuration or database
        $thresholds = [
            'single_transaction' => [
                'amount'   => config('compliance.thresholds.single_transaction', 10000),
                'currency' => 'USD',
            ],
            'daily_aggregate' => [
                'amount'   => config('compliance.thresholds.daily_aggregate', 50000),
                'currency' => 'USD',
            ],
            'monthly_aggregate' => [
                'amount'   => config('compliance.thresholds.monthly_aggregate', 100000),
                'currency' => 'USD',
            ],
            'velocity' => [
                'count_per_hour' => config('compliance.thresholds.velocity.count_per_hour', 10),
                'count_per_day'  => config('compliance.thresholds.velocity.count_per_day', 50),
            ],
            'structuring' => [
                'threshold'   => config('compliance.thresholds.structuring', 9000),
                'count'       => 3,
                'time_window' => 3600, // 1 hour
            ],
        ];

        return response()->json([
            'data' => $thresholds,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/transaction-monitoring/thresholds",
     *     operationId="updateMonitoringThresholds",
     *     tags={"Transaction Monitoring"},
     *     summary="Update monitoring thresholds",
     *     description="Update transaction monitoring thresholds",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Thresholds updated successfully",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function updateThresholds(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'single_transaction.amount' => ['sometimes', 'numeric', 'min:0'],
            'daily_aggregate.amount'    => ['sometimes', 'numeric', 'min:0'],
            'monthly_aggregate.amount'  => ['sometimes', 'numeric', 'min:0'],
            'velocity.count_per_hour'   => ['sometimes', 'integer', 'min:1'],
            'velocity.count_per_day'    => ['sometimes', 'integer', 'min:1'],
            'structuring.threshold'     => ['sometimes', 'numeric', 'min:0'],
            'structuring.count'         => ['sometimes', 'integer', 'min:2'],
            'structuring.time_window'   => ['sometimes', 'integer', 'min:60'],
        ]);

        // In production, save to database or configuration
        // For now, cache the updated values
        foreach ($validated as $key => $value) {
            $cacheKey = 'compliance:thresholds:' . str_replace('.', ':', $key);
            Cache::put($cacheKey, $value, now()->addDays(30));
        }

        // Log threshold changes for audit
        Log::info('Monitoring thresholds updated', [
            'user_id' => auth()->id(),
            'changes' => $validated,
        ]);

        return response()->json([
            'message' => 'Thresholds updated successfully',
            'data'    => $validated,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/transaction-monitoring/analyze/realtime",
     *     operationId="analyzeTransactionRealtime",
     *     tags={"Transaction Monitoring"},
     *     summary="Analyze transaction in real-time",
     *     description="Perform real-time analysis of a transaction",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"transaction_id"},
     *             @OA\Property(property="transaction_id", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analysis completed",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function analyzeRealtime(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
        ]);

        /** @var Transaction $transaction */
        $transaction = Transaction::with(['account', 'account.user'])
            ->findOrFail($validated['transaction_id']);

        // Process through stream processor
        $result = $this->streamProcessor->processTransaction($transaction);

        // Get analysis from monitoring service
        $analysis = $this->monitoringService->analyzeTransaction($transaction);

        return response()->json([
            'data' => [
                'analysis_id'      => uniqid('rtm_'),
                'status'           => 'completed',
                'transaction_id'   => $transaction->id,
                'risk_score'       => $result['risk_score'] ?? 0,
                'patterns'         => $result['patterns'] ?? [],
                'alerts_generated' => $result['alerts_count'] ?? 0,
                'recommendation'   => $analysis['recommendation'] ?? 'monitor',
                'details'          => $analysis,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/transaction-monitoring/analyze/batch",
     *     operationId="analyzeTransactionBatch",
     *     tags={"Transaction Monitoring"},
     *     summary="Analyze transactions in batch",
     *     description="Perform batch analysis of multiple transactions",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"transaction_ids"},
     *             @OA\Property(
     *                 property="transaction_ids",
     *                 type="array",
     *                 @OA\Items(type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch analysis started",
     *         @OA\JsonContent()
     *     )
     * )
     */
    public function analyzeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'transaction_ids.*' => ['string'],
        ]);

        $transactions = Transaction::with(['account', 'account.user'])
            ->whereIn('id', $validated['transaction_ids'])
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'error' => 'No valid transactions found',
            ], 404);
        }

        $batchId = uniqid('batch_');

        // Process batch through stream processor
        $result = $this->streamProcessor->processBatch($transactions);

        // Store batch status in cache
        Cache::put("batch_analysis:{$batchId}", [
            'status'            => 'completed',
            'total'             => $transactions->count(),
            'processed'         => $transactions->count(),
            'alerts_generated'  => $result['total_alerts'] ?? 0,
            'patterns_detected' => $result['patterns'] ?? [],
            'completed_at'      => now(),
        ], now()->addHours(24));

        return response()->json([
            'data' => [
                'batch_id'           => $batchId,
                'status'             => 'completed',
                'total_transactions' => $transactions->count(),
                'result'             => $result,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/transaction-monitoring/analysis/{analysisId}",
     *     operationId="getAnalysisStatus",
     *     tags={"Transaction Monitoring"},
     *     summary="Get analysis status",
     *     description="Get the status of a batch analysis",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="analysisId",
     *         in="path",
     *         description="Analysis ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analysis status",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Analysis not found"
     *     )
     * )
     */
    public function getAnalysisStatus(string $analysisId): JsonResponse
    {
        $status = Cache::get("batch_analysis:{$analysisId}");

        if (! $status) {
            return response()->json([
                'error' => 'Analysis not found',
            ], 404);
        }

        return response()->json([
            'data' => array_merge(
                ['analysis_id' => $analysisId],
                $status
            ),
        ]);
    }

    /**
     * Calculate rule priority based on severity.
     */
    private function calculateRulePriority(string $severity): int
    {
        return match ($severity) {
            'critical' => 100,
            'high'     => 75,
            'medium'   => 50,
            'low'      => 25,
            default    => 10,
        };
    }
}
