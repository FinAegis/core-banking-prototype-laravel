<?php

namespace App\Http\Controllers\Api\Documentation;

/**
 * @OA\Schema(
 *     schema="WorkflowExecution",
 *     required={"id", "workflow_name", "status", "started_at"},
 *     @OA\Property(property="id", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="workflow_name", type="string", example="TransferWorkflow"),
 *     @OA\Property(property="status", type="string", enum={"running", "completed", "failed", "compensating", "compensated"}, example="completed"),
 *     @OA\Property(property="input_data", type="object", description="Workflow input parameters"),
 *     @OA\Property(property="output_data", type="object", description="Workflow output data", nullable=true),
 *     @OA\Property(property="error", type="string", description="Error message if failed", nullable=true),
 *     @OA\Property(property="started_at", type="string", format="date-time"),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="duration_ms", type="integer", example=250),
 *     @OA\Property(property="steps", type="array", @OA\Items(
 *         @OA\Property(property="name", type="string", example="ValidateTransfer"),
 *         @OA\Property(property="status", type="string", enum={"pending", "running", "completed", "failed", "compensated"}),
 *         @OA\Property(property="started_at", type="string", format="date-time"),
 *         @OA\Property(property="completed_at", type="string", format="date-time"),
 *         @OA\Property(property="error", type="string", nullable=true)
 *     ))
 * )
 */

/**
 * @OA\Schema(
 *     schema="WorkflowStatistics",
 *     required={"workflow_name", "period", "statistics"},
 *     @OA\Property(property="workflow_name", type="string", example="TransferWorkflow"),
 *     @OA\Property(property="period", type="string", example="last_24_hours"),
 *     @OA\Property(property="statistics", type="object",
 *         @OA\Property(property="total_executions", type="integer", example=1250),
 *         @OA\Property(property="successful", type="integer", example=1200),
 *         @OA\Property(property="failed", type="integer", example=45),
 *         @OA\Property(property="compensated", type="integer", example=5),
 *         @OA\Property(property="average_duration_ms", type="number", example=185.5),
 *         @OA\Property(property="min_duration_ms", type="integer", example=50),
 *         @OA\Property(property="max_duration_ms", type="integer", example=2500),
 *         @OA\Property(property="success_rate", type="number", example=0.96)
 *     ),
 *     @OA\Property(property="failure_reasons", type="array", @OA\Items(
 *         @OA\Property(property="reason", type="string", example="Insufficient balance"),
 *         @OA\Property(property="count", type="integer", example=30),
 *         @OA\Property(property="percentage", type="number", example=0.667)
 *     ))
 * )
 */

/**
 * @OA\Schema(
 *     schema="CircuitBreakerStatus",
 *     required={"service", "state", "failure_count", "last_checked"},
 *     @OA\Property(property="service", type="string", example="paysera_connector"),
 *     @OA\Property(property="state", type="string", enum={"closed", "open", "half_open"}, example="closed"),
 *     @OA\Property(property="failure_count", type="integer", example=0),
 *     @OA\Property(property="success_count", type="integer", example=150),
 *     @OA\Property(property="threshold", type="integer", example=5, description="Failures before opening"),
 *     @OA\Property(property="timeout", type="integer", example=60, description="Seconds before half-open"),
 *     @OA\Property(property="last_failure", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="last_success", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="last_checked", type="string", format="date-time"),
 *     @OA\Property(property="next_retry", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="metadata", type="object",
 *         @OA\Property(property="error_rate", type="number", example=0.01),
 *         @OA\Property(property="average_response_time_ms", type="number", example=120.5)
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="EventReplayRequest",
 *     required={"aggregate_uuid", "from_version"},
 *     @OA\Property(property="aggregate_uuid", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="from_version", type="integer", example=1, description="Starting version to replay from"),
 *     @OA\Property(property="to_version", type="integer", example=100, description="Ending version to replay to", nullable=true),
 *     @OA\Property(property="event_types", type="array", @OA\Items(type="string"), description="Filter by specific event types", nullable=true),
 *     @OA\Property(property="dry_run", type="boolean", example=false, description="Simulate replay without applying changes")
 * )
 */

/**
 * @OA\Schema(
 *     schema="EventReplayResult",
 *     required={"aggregate_uuid", "events_replayed", "status"},
 *     @OA\Property(property="aggregate_uuid", type="string", format="uuid"),
 *     @OA\Property(property="events_replayed", type="integer", example=50),
 *     @OA\Property(property="status", type="string", enum={"completed", "failed", "partial"}, example="completed"),
 *     @OA\Property(property="final_state", type="object", description="Final aggregate state after replay"),
 *     @OA\Property(property="errors", type="array", @OA\Items(type="string"), description="Any errors encountered"),
 *     @OA\Property(property="duration_ms", type="integer", example=450),
 *     @OA\Property(property="dry_run", type="boolean", example=false)
 * )
 */

/**
 * @OA\Schema(
 *     schema="QueueMetrics",
 *     required={"queue_name", "metrics"},
 *     @OA\Property(property="queue_name", type="string", example="transactions"),
 *     @OA\Property(property="metrics", type="object",
 *         @OA\Property(property="size", type="integer", example=125, description="Current queue size"),
 *         @OA\Property(property="processing_rate", type="number", example=15.5, description="Jobs per second"),
 *         @OA\Property(property="average_wait_time_ms", type="number", example=850),
 *         @OA\Property(property="failed_jobs_24h", type="integer", example=12),
 *         @OA\Property(property="workers", type="integer", example=4, description="Active workers"),
 *         @OA\Property(property="memory_usage_mb", type="number", example=256.5)
 *     ),
 *     @OA\Property(property="job_types", type="array", @OA\Items(
 *         @OA\Property(property="type", type="string", example="ProcessTransferJob"),
 *         @OA\Property(property="count", type="integer", example=45),
 *         @OA\Property(property="average_duration_ms", type="number", example=120)
 *     ))
 * )
 */

class WorkflowSchemas
{
    // This class only contains OpenAPI schema definitions
}