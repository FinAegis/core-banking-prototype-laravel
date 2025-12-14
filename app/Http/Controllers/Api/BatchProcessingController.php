<?php

namespace App\Http\Controllers\Api;

use App\Domain\Account\Workflows\BatchProcessingWorkflow;
use App\Domain\Batch\Models\BatchJob;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Workflow\WorkflowStub;

/**
 * @OA\Tag(
 *     name="Batch Processing",
 *     description="End-of-day batch operations and bulk financial processing"
 * )
 */
class BatchProcessingController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/batch-operations/execute",
     *     tags={"Batch Processing"},
     *     summary="Execute batch operations",
     *     description="Execute end-of-day batch processing operations with compensation support",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"operations"},
     *
     * @OA\Property(property="operations",         type="array",
     *
     * @OA\Items(type="object",
     *
     * @OA\Property(property="type",               type="string", enum={"account_interest", "fee_collection", "balance_reconciliation", "report_generation"}, example="account_interest"),
     * @OA\Property(property="parameters",         type="object", example={"rate": 0.05, "date": "2023-12-31"}),
     * @OA\Property(property="priority",           type="integer", minimum=1, maximum=10, example=5)
     *                 )
     *             ),
     * @OA\Property(property="batch_name",         type="string", example="EOD_2023_12_31"),
     * @OA\Property(property="schedule_time",      type="string", format="date-time", nullable=true),
     * @OA\Property(property="retry_attempts",     type="integer", minimum=0, maximum=5, default=3)
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=202,
     *         description="Batch processing initiated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",            type="string", example="Batch processing initiated successfully"),
     * @OA\Property(property="data",               type="object",
     * @OA\Property(property="batch_id",           type="string", example="batch_550e8400_e29b_41d4"),
     * @OA\Property(property="status",             type="string", example="initiated"),
     * @OA\Property(property="operations_count",   type="integer", example=4),
     * @OA\Property(property="estimated_duration", type="string", example="15-30 minutes"),
     * @OA\Property(property="started_at",         type="string", format="date-time"),
     * @OA\Property(property="started_by",         type="string", example="admin@finaegis.org")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=400,
     *         description="Invalid batch operation request"
     *     ),
     * @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     ),
     * @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function executeBatch(Request $request): JsonResponse
    {
        // Only admins can execute batch operations
        if (! Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate(
            [
                'operations'              => 'required|array|min:1',
                'operations.*.type'       => 'required|string|in:account_interest,fee_collection,balance_reconciliation,report_generation,maintenance_tasks',
                'operations.*.parameters' => 'required|array',
                'operations.*.priority'   => 'integer|min:1|max:10',
                'batch_name'              => 'nullable|string|max:255',
                'schedule_time'           => 'nullable|date_format:Y-m-d H:i:s',
                'retry_attempts'          => 'integer|min:0|max:5',
            ]
        );

        try {
            $batchId = 'batch_' . Str::uuid()->toString();

            // Validate each operation has required parameters
            foreach ($validated['operations'] as $index => $operation) {
                $this->validateOperationParameters($operation);
            }

            // Start the batch processing workflow
            $workflow = WorkflowStub::make(BatchProcessingWorkflow::class);

            // Execute in background if scheduled, otherwise execute immediately
            if (isset($validated['schedule_time'])) {
                // TODO: Implement scheduled execution
                $status = 'scheduled';
            } else {
                // Execute immediately in background
                $workflow->execute($validated['operations'], $batchId);
                $status = 'initiated';
            }

            return response()->json(
                [
                    'message' => 'Batch processing initiated successfully',
                    'data'    => [
                        'batch_id'           => $batchId,
                        'status'             => $status,
                        'operations_count'   => count($validated['operations']),
                        'batch_name'         => $validated['batch_name'] ?? 'EOD_' . now()->format('Y_m_d'),
                        'estimated_duration' => $this->estimateDuration($validated['operations']),
                        'started_at'         => now()->toISOString(),
                        'started_by'         => Auth::user()->email,
                        'retry_attempts'     => $validated['retry_attempts'] ?? 3,
                    ],
                ],
                202
            );
        } catch (Exception $e) {
            logger()->error(
                'Batch processing initiation failed',
                [
                    'operations' => $validated['operations'],
                    'error'      => $e->getMessage(),
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json(
                [
                    'message' => 'Batch processing initiation failed',
                    'error'   => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/batch-operations/{batchId}/status",
     *     tags={"Batch Processing"},
     *     summary="Get batch operation status",
     *     description="Get the current status and progress of a batch operation",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="batchId",
     *         in="path",
     *         required=true,
     *         description="Batch ID",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Batch status retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                 type="object",
     * @OA\Property(property="batch_id",             type="string", example="batch_550e8400_e29b_41d4"),
     * @OA\Property(property="status",               type="string", enum={"initiated", "running", "completed", "failed", "compensating"}, example="running"),
     * @OA\Property(property="progress",             type="integer", minimum=0, maximum=100, example=65),
     * @OA\Property(property="operations_total",     type="integer", example=4),
     * @OA\Property(property="operations_completed", type="integer", example=2),
     * @OA\Property(property="operations_failed",    type="integer", example=0),
     * @OA\Property(property="current_operation",    type="string", example="balance_reconciliation"),
     * @OA\Property(property="started_at",           type="string", format="date-time"),
     * @OA\Property(property="estimated_completion", type="string", format="date-time"),
     * @OA\Property(property="error_message",        type="string", nullable=true),
     * @OA\Property(property="operations",           type="array",
     *
     * @OA\Items(type="object",
     *
     * @OA\Property(property="type",                 type="string", example="account_interest"),
     * @OA\Property(property="status",               type="string", example="completed"),
     * @OA\Property(property="started_at",           type="string", format="date-time"),
     * @OA\Property(property="completed_at",         type="string", format="date-time"),
     * @OA\Property(property="records_processed",    type="integer", example=1250),
     * @OA\Property(property="error_message",        type="string", nullable=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Batch operation not found"
     *     )
     * )
     */
    public function getBatchStatus(string $batchId): JsonResponse
    {
        $batch = BatchJob::where('uuid', $batchId)->with('items')->first();

        if (! $batch) {
            return response()->json(
                [
                    'error'   => 'Batch not found',
                    'message' => "No batch found with ID: {$batchId}",
                ],
                404
            );
        }

        // Calculate progress percentage
        $progress = $batch->total_items > 0
            ? (int) round(($batch->processed_items / $batch->total_items) * 100)
            : 0;

        // Build operations list from batch items
        $operations = $batch->items->map(fn ($item) => [
            'type'              => $item->data['type'] ?? 'unknown',
            'status'            => $item->status,
            'started_at'        => $item->created_at?->toISOString(),
            'completed_at'      => $item->processed_at?->toISOString(),
            'records_processed' => $item->result['records_processed'] ?? 0,
            'error_message'     => $item->error_message,
        ])->toArray();

        $status = [
            'batch_id'             => $batch->uuid,
            'batch_name'           => $batch->name,
            'status'               => $batch->status,
            'progress'             => $progress,
            'operations_total'     => $batch->total_items,
            'operations_completed' => $batch->processed_items,
            'operations_failed'    => $batch->failed_items,
            'started_at'           => $batch->started_at?->toISOString(),
            'completed_at'         => $batch->completed_at?->toISOString(),
            'error_message'        => $batch->metadata['error_message'] ?? null,
            'operations'           => $operations,
        ];

        return response()->json(
            [
                'data' => $status,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/batch-operations",
     *     tags={"Batch Processing"},
     *     summary="Get batch operations history",
     *     description="Get list of recent batch operations with filtering options",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *
     * @OA\Schema(type="string",                 enum={"initiated", "running", "completed", "failed", "scheduled"})
     *     ),
     *
     * @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter from date",
     *
     * @OA\Schema(type="string",                 format="date")
     *     ),
     *
     * @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter to date",
     *
     * @OA\Schema(type="string",                 format="date")
     *     ),
     *
     * @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of results to return",
     *
     * @OA\Schema(type="integer",                minimum=1, maximum=100, default=20)
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Batch operations history retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",             type="array",
     *
     * @OA\Items(type="object",
     *
     * @OA\Property(property="batch_id",         type="string", example="batch_550e8400_e29b_41d4"),
     * @OA\Property(property="batch_name",       type="string", example="EOD_2023_12_31"),
     * @OA\Property(property="status",           type="string", example="completed"),
     * @OA\Property(property="operations_count", type="integer", example=4),
     * @OA\Property(property="started_at",       type="string", format="date-time"),
     * @OA\Property(property="completed_at",     type="string", format="date-time"),
     * @OA\Property(property="duration_minutes", type="integer", example=23),
     * @OA\Property(property="started_by",       type="string", example="admin@finaegis.org")
     *                 )
     *             ),
     * @OA\Property(property="pagination",       type="object",
     * @OA\Property(property="total",            type="integer", example=87),
     * @OA\Property(property="limit",            type="integer", example=20),
     * @OA\Property(property="offset",           type="integer", example=0)
     *             )
     *         )
     *     )
     * )
     */
    public function getBatchHistory(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'status'    => 'string|in:pending,processing,completed,failed,cancelled',
                'date_from' => 'date',
                'date_to'   => 'date',
                'limit'     => 'integer|min:1|max:100',
                'offset'    => 'integer|min:0',
            ]
        );

        $limit = $validated['limit'] ?? 20;
        $offset = $validated['offset'] ?? 0;

        $query = BatchJob::query()->with('user:id,uuid,name,email');

        // Apply filters
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $total = $query->count();
        $batches = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $history = $batches->map(function ($batch) {
            $durationMinutes = null;
            if ($batch->started_at && $batch->completed_at) {
                $durationMinutes = $batch->started_at->diffInMinutes($batch->completed_at);
            }

            return [
                'batch_id'         => $batch->uuid,
                'batch_name'       => $batch->name,
                'type'             => $batch->type,
                'status'           => $batch->status,
                'operations_count' => $batch->total_items,
                'processed_count'  => $batch->processed_items,
                'failed_count'     => $batch->failed_items,
                'started_at'       => $batch->started_at?->toISOString(),
                'completed_at'     => $batch->completed_at?->toISOString(),
                'duration_minutes' => $durationMinutes,
                'started_by'       => data_get($batch, 'user.email', 'system'),
            ];
        })->toArray();

        return response()->json(
            [
                'data'       => $history,
                'pagination' => [
                    'total'  => $total,
                    'limit'  => $limit,
                    'offset' => $offset,
                ],
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/batch-operations/{batchId}/cancel",
     *     tags={"Batch Processing"},
     *     summary="Cancel batch operation",
     *     description="Cancel a running or scheduled batch operation with compensation",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="batchId",
     *         in="path",
     *         required=true,
     *         description="Batch ID",
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"reason"},
     *
     * @OA\Property(property="reason",                type="string", example="Emergency maintenance required"),
     * @OA\Property(property="compensate",            type="boolean", default=true, description="Whether to run compensation for completed operations")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Batch operation cancelled successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",               type="string", example="Batch operation cancelled successfully"),
     * @OA\Property(property="data",                  type="object",
     * @OA\Property(property="batch_id",              type="string", example="batch_550e8400_e29b_41d4"),
     * @OA\Property(property="status",                type="string", example="cancelled"),
     * @OA\Property(property="cancelled_at",          type="string", format="date-time"),
     * @OA\Property(property="cancelled_by",          type="string", example="admin@finaegis.org"),
     * @OA\Property(property="compensation_required", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function cancelBatch(Request $request, string $batchId): JsonResponse
    {
        if (! Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate(
            [
                'reason'     => 'required|string|max:500',
                'compensate' => 'boolean',
            ]
        );

        try {
            // TODO: Implement actual batch cancellation logic
            // This would involve stopping the workflow and potentially running compensations

            return response()->json(
                [
                    'message' => 'Batch operation cancelled successfully',
                    'data'    => [
                        'batch_id'              => $batchId,
                        'status'                => 'cancelled',
                        'cancelled_at'          => now()->toISOString(),
                        'cancelled_by'          => Auth::user()->email,
                        'reason'                => $validated['reason'],
                        'compensation_required' => $validated['compensate'] ?? true,
                    ],
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'message' => 'Failed to cancel batch operation',
                    'error'   => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Validate operation-specific parameters.
     */
    private function validateOperationParameters(array $operation): void
    {
        $type = $operation['type'];
        $parameters = $operation['parameters'];

        switch ($type) {
            case 'account_interest':
                if (! isset($parameters['rate']) || ! is_numeric($parameters['rate'])) {
                    throw new InvalidArgumentException('Interest rate is required for account_interest operation');
                }
                break;
            case 'fee_collection':
                if (! isset($parameters['fee_type'])) {
                    throw new InvalidArgumentException('Fee type is required for fee_collection operation');
                }
                break;
            case 'balance_reconciliation':
                if (! isset($parameters['date'])) {
                    throw new InvalidArgumentException('Date is required for balance_reconciliation operation');
                }
                break;
            case 'report_generation':
                if (! isset($parameters['report_type'])) {
                    throw new InvalidArgumentException('Report type is required for report_generation operation');
                }
                break;
        }
    }

    /**
     * Estimate batch duration based on operations.
     */
    private function estimateDuration(array $operations): string
    {
        $estimatedMinutes = count($operations) * 5; // 5 minutes per operation on average

        if ($estimatedMinutes < 10) {
            return '5-10 minutes';
        } elseif ($estimatedMinutes < 30) {
            return '15-30 minutes';
        } elseif ($estimatedMinutes < 60) {
            return '30-60 minutes';
        } else {
            return '1-2 hours';
        }
    }
}
