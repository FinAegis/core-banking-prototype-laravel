<?php

namespace App\Domain\Batch\Activities;

use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Domain\Batch\DataObjects\BatchJob;
use App\Domain\Batch\Models\BatchJob as BatchJobModel;
use Workflow\Activity;
use Workflow\Exceptions\NonRetryableException;

class ValidateBatchJobActivity extends Activity
{
    public function execute(string $batchJobUuid): BatchJob
    {
        /** @var mixed|null $batchJobModel */
        $batchJobModel = null;
        $batchJobModel = BatchJobModel::where('uuid', $batchJobUuid)->with('items')->first();

        if (! $batchJobModel) {
            throw new NonRetryableException("Batch job not found: {$batchJobUuid}");
        }

        if ($batchJobModel->status !== 'pending') {
            // A cancelled or already started batch is not started again, and
            // retrying cannot change that.
            throw new NonRetryableException("Batch job is not in pending status: {$batchJobModel->status}");
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
            items: $batchJobModel->items->map(fn ($item) => $item->data)->toArray(),
            scheduledAt: $batchJobModel->scheduled_at?->toIso8601String(),
            metadata: $batchJobModel->metadata ?? []
        );
    }
}
