<?php

namespace App\Domain\Batch\Services;

use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Domain\Batch\DataObjects\BatchJob as BatchJobData;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Workflows\ProcessBatchJobWorkflow;
use Workflow\WorkflowStub;

class BatchProcessingService
{
    /**
     * Create a new batch job
     */
    public function createBatchJob(
        string $userUuid,
        string $name,
        string $type,
        array $items,
        ?string $scheduledAt = null,
        array $metadata = []
    ): BatchJob {
        // Create batch job data object
        $batchJobData = BatchJobData::create(
            userUuid: $userUuid,
            name: $name,
            type: $type,
            items: $items,
            scheduledAt: $scheduledAt,
            metadata: $metadata
        );
        
        // Create batch job through event sourcing
        BatchAggregate::retrieve($batchJobData->uuid)
            ->createBatchJob($batchJobData)
            ->persist();
        
        // If not scheduled, process immediately
        if (!$scheduledAt || $scheduledAt <= now()) {
            $this->processBatch($batchJobData->uuid);
        }
        
        return BatchJob::where('uuid', $batchJobData->uuid)->first();
    }
    
    /**
     * Process a batch job
     */
    public function processBatch(string $batchJobUuid): void
    {
        $workflow = WorkflowStub::make(ProcessBatchJobWorkflow::class);
        $workflow->start($batchJobUuid);
    }
    
    /**
     * Cancel a batch job
     */
    public function cancelBatchJob(string $batchJobUuid, string $reason): void
    {
        BatchAggregate::retrieve($batchJobUuid)
            ->cancelBatchJob($reason)
            ->persist();
    }
    
    /**
     * Retry failed items in a batch job
     */
    public function retryFailedItems(string $batchJobUuid): void
    {
        $batchJob = BatchJob::where('uuid', $batchJobUuid)->first();
        
        if (!$batchJob) {
            throw new \InvalidArgumentException("Batch job not found: {$batchJobUuid}");
        }
        
        // Get failed items
        $failedItems = $batchJob->items()
            ->where('status', 'failed')
            ->get()
            ->map(function($item) {
                return $item->data;
            })
            ->toArray();
        
        if (empty($failedItems)) {
            return;
        }
        
        // Create a new batch job for retry
        $this->createBatchJob(
            userUuid: $batchJob->user_uuid,
            name: $batchJob->name . ' (Retry)',
            type: $batchJob->type,
            items: $failedItems,
            metadata: [
                'original_batch_job_uuid' => $batchJobUuid,
                'is_retry' => true,
            ]
        );
    }
}