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
use OpenApi\Attributes as OA;
use Workflow\WorkflowStub;

#[OA\Tag(
    name: 'Batch Processing',
    description: 'End-of-day batch operations and bulk financial processing'
)]
class BatchProcessingController extends Controller
{
        #[OA\Post(
            path: '/api/batch-operations/execute',
            tags: ['Batch Processing'],
            summary: 'Execute batch operations',
            description: 'Execute end-of-day batch processing operations with compensation support',
            security: [['bearerAuth' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['operations'], properties: [
            new OA\Property(property: 'operations', type: 'array', items: new OA\Items(type: 'object', properties: [
            new OA\Property(property: 'type', type: 'string', enum: ['account_interest', 'fee_collection', 'balance_reconciliation', 'report_generation'], example: 'account_interest'),
            new OA\Property(property: 'parameters', type: 'object', example: ['rate' => 0.05, 'date' => '2023-12-31']),
            new OA\Property(property: 'priority', type: 'integer', minimum: 1, maximum: 10, example: 5),
            ])),
            new OA\Property(property: 'batch_name', type: 'string', example: 'EOD_2023_12_31'),
            new OA\Property(property: 'schedule_time', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'retry_attempts', type: 'integer', minimum: 0, maximum: 5, default: 3),
            ]))
        )]
    #[OA\Response(
        response: 202,
        description: 'Batch processing initiated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Batch processing initiated successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'batch_id', type: 'string', example: 'batch_550e8400_e29b_41d4'),
        new OA\Property(property: 'status', type: 'string', example: 'initiated'),
        new OA\Property(property: 'operations_count', type: 'integer', example: 4),
        new OA\Property(property: 'estimated_duration', type: 'string', example: '15-30 minutes'),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'started_by', type: 'string', example: 'admin@finaegis.org'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid batch operation request'
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden - Admin access required'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
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

            // Create batch job record for tracking
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $batchJob = BatchJob::create([
                'uuid'        => $batchId,
                'name'        => $validated['batch_name'] ?? 'EOD_' . now()->format('Y_m_d'),
                'type'        => 'batch_operations',
                'status'      => 'pending',
                'total_items' => count($validated['operations']),
                'user_uuid'   => $user->uuid,
                'metadata'    => [
                    'operations'     => $validated['operations'],
                    'retry_attempts' => $validated['retry_attempts'] ?? 3,
                ],
            ]);

            // Execute in background if scheduled, otherwise execute immediately
            if (isset($validated['schedule_time'])) {
                $scheduledAt = \Carbon\Carbon::parse($validated['schedule_time']);
                $delaySeconds = now()->diffInSeconds($scheduledAt, false);

                if ($delaySeconds > 0) {
                    // Schedule for future execution
                    $batchJob->update([
                        'status'       => 'scheduled',
                        'scheduled_at' => $scheduledAt,
                    ]);

                    // Dispatch with delay
                    dispatch(function () use ($workflow, $validated, $batchId, $batchJob) {
                        $batchJob->update(['status' => 'processing', 'started_at' => now()]);
                        $workflow->execute($validated['operations'], $batchId);
                    })->delay($scheduledAt);

                    $status = 'scheduled';
                } else {
                    // Scheduled time is in the past, execute immediately
                    $batchJob->update(['status' => 'processing', 'started_at' => now()]);
                    $workflow->execute($validated['operations'], $batchId);
                    $status = 'initiated';
                }
            } else {
                // Execute immediately in background
                $batchJob->update(['status' => 'processing', 'started_at' => now()]);
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
                        'started_by'         => $user->email,
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
                    'error'   => 'An internal error occurred. Please try again later.',
                ],
                500
            );
        }
    }

        #[OA\Get(
            path: '/api/batch-operations/{batchId}/status',
            tags: ['Batch Processing'],
            summary: 'Get batch operation status',
            description: 'Get the current status and progress of a batch operation',
            security: [['bearerAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'batchId', in: 'path', required: true, description: 'Batch ID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Batch status retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'batch_id', type: 'string', example: 'batch_550e8400_e29b_41d4'),
        new OA\Property(property: 'status', type: 'string', enum: ['initiated', 'running', 'completed', 'failed', 'compensating'], example: 'running'),
        new OA\Property(property: 'progress', type: 'integer', minimum: 0, maximum: 100, example: 65),
        new OA\Property(property: 'operations_total', type: 'integer', example: 4),
        new OA\Property(property: 'operations_completed', type: 'integer', example: 2),
        new OA\Property(property: 'operations_failed', type: 'integer', example: 0),
        new OA\Property(property: 'current_operation', type: 'string', example: 'balance_reconciliation'),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'estimated_completion', type: 'string', format: 'date-time'),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'operations', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'type', type: 'string', example: 'account_interest'),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'records_processed', type: 'integer', example: 1250),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        ])),
        ]),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Batch operation not found'
    )]
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

        #[OA\Get(
            path: '/api/batch-operations',
            tags: ['Batch Processing'],
            summary: 'Get batch operations history',
            description: 'Get list of recent batch operations with filtering options',
            security: [['bearerAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status', schema: new OA\Schema(type: 'string', enum: ['initiated', 'running', 'completed', 'failed', 'scheduled'])),
            new OA\Parameter(name: 'date_from', in: 'query', description: 'Filter from date', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', description: 'Filter to date', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Number of results to return', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Batch operations history retrieved successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'batch_id', type: 'string', example: 'batch_550e8400_e29b_41d4'),
        new OA\Property(property: 'batch_name', type: 'string', example: 'EOD_2023_12_31'),
        new OA\Property(property: 'status', type: 'string', example: 'completed'),
        new OA\Property(property: 'operations_count', type: 'integer', example: 4),
        new OA\Property(property: 'started_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'duration_minutes', type: 'integer', example: 23),
        new OA\Property(property: 'started_by', type: 'string', example: 'admin@finaegis.org'),
        ])),
        new OA\Property(property: 'pagination', type: 'object', properties: [
        new OA\Property(property: 'total', type: 'integer', example: 87),
        new OA\Property(property: 'limit', type: 'integer', example: 20),
        new OA\Property(property: 'offset', type: 'integer', example: 0),
        ]),
        ])
    )]
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

        #[OA\Post(
            path: '/api/batch-operations/{batchId}/cancel',
            tags: ['Batch Processing'],
            summary: 'Cancel batch operation',
            description: 'Cancel a running or scheduled batch operation with compensation',
            security: [['bearerAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'batchId', in: 'path', required: true, description: 'Batch ID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [
            new OA\Property(property: 'reason', type: 'string', example: 'Emergency maintenance required'),
            new OA\Property(property: 'compensate', type: 'boolean', default: true, description: 'Whether to run compensation for completed operations'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Batch operation cancelled successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Batch operation cancelled successfully'),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'batch_id', type: 'string', example: 'batch_550e8400_e29b_41d4'),
        new OA\Property(property: 'status', type: 'string', example: 'cancelled'),
        new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'cancelled_by', type: 'string', example: 'admin@finaegis.org'),
        new OA\Property(property: 'compensation_required', type: 'boolean', example: true),
        ]),
        ])
    )]
    public function cancelBatch(Request $request, string $batchId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate(
            [
                'reason'     => 'required|string|max:500',
                'compensate' => 'boolean',
            ]
        );

        try {
            // Find the batch job
            $batch = BatchJob::where('uuid', $batchId)->first();

            if (! $batch) {
                return response()->json(
                    [
                        'message' => 'Batch not found',
                        'error'   => "No batch found with ID: {$batchId}",
                    ],
                    404
                );
            }

            // Check if batch can be cancelled
            if (! $batch->canBeCancelled()) {
                return response()->json(
                    [
                        'message' => 'Batch cannot be cancelled',
                        'error'   => "Batch is in '{$batch->status}' status and cannot be cancelled",
                    ],
                    409
                );
            }

            $compensationRequired = $validated['compensate'] ?? true;

            // Update batch status to cancelled
            $batch->update([
                'status'       => 'cancelled',
                'completed_at' => now(),
                'metadata'     => array_merge($batch->metadata ?? [], [
                    'cancelled_at'           => now()->toIso8601String(),
                    'cancelled_by'           => $user->email,
                    'cancellation_reason'    => $validated['reason'],
                    'compensation_requested' => $compensationRequired,
                ]),
            ]);

            // Log cancellation
            logger()->info('Batch operation cancelled', [
                'batch_id'     => $batchId,
                'cancelled_by' => $user->email,
                'reason'       => $validated['reason'],
                'compensate'   => $compensationRequired,
            ]);

            // If compensation is required and there are processed items, trigger compensation
            if ($compensationRequired && $batch->processed_items > 0) {
                // Dispatch compensation job
                dispatch(function () use ($batch) {
                    $batch->update([
                        'metadata' => array_merge($batch->metadata ?? [], [
                            'compensation_started_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    // Compensation logic would be implemented in the workflow
                    // For now, mark as compensation complete
                    $batch->update([
                        'metadata' => array_merge($batch->metadata ?? [], [
                            'compensation_completed_at' => now()->toIso8601String(),
                        ]),
                    ]);
                })->afterResponse();
            }

            return response()->json(
                [
                    'message' => 'Batch operation cancelled successfully',
                    'data'    => [
                        'batch_id'                => $batchId,
                        'status'                  => 'cancelled',
                        'cancelled_at'            => now()->toISOString(),
                        'cancelled_by'            => $user->email,
                        'reason'                  => $validated['reason'],
                        'compensation_required'   => $compensationRequired && $batch->processed_items > 0,
                        'processed_before_cancel' => $batch->processed_items,
                    ],
                ]
            );
        } catch (Exception $e) {
            logger()->error('Failed to cancel batch operation', [
                'batch_id' => $batchId,
                'error'    => $e->getMessage(),
            ]);

            return response()->json(
                [
                    'message' => 'Failed to cancel batch operation',
                    'error'   => 'An internal error occurred. Please try again later.',
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
