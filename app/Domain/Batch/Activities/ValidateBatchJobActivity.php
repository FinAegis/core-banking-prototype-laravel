<?php

namespace App\Domain\Batch\Activities;

use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Domain\Batch\DataObjects\BatchJob;
use App\Models\BatchJob as BatchJobModel;
use Workflow\Activity;

class ValidateBatchJobActivity extends Activity
{
    /**
     * @param string $batchJobUuid
     * @return BatchJob
     */
    public function execute(string $batchJobUuid): BatchJob
    {
        $batchJobModel = BatchJobModel::where('uuid', $batchJobUuid)->with('items')->first();
        
        if (!$batchJobModel) {
            throw new \InvalidArgumentException("Batch job not found: {$batchJobUuid}");
        }
        
        if ($batchJobModel->status !== 'pending') {
            throw new \InvalidArgumentException("Batch job is not in pending status: {$batchJobModel->status}");
        }
        
        // Start the batch job
        BatchAggregate::retrieve($batchJobUuid)
            ->startBatchJob()
            ->persist();
        
        // Convert to DataObject
        return BatchJob::create(
            userUuid: $batchJobModel->user_uuid,
            name: $batchJobModel->name,
            type: $batchJobModel->type,
            items: $batchJobModel->items->map(fn($item) => $item->data)->toArray(),
            scheduledAt: $batchJobModel->scheduled_at?->toIso8601String(),
            metadata: $batchJobModel->metadata ?? []
        );
    }
}