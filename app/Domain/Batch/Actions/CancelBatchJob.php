<?php

namespace App\Domain\Batch\Actions;

use App\Domain\Batch\Events\BatchJobCancelled;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Models\BatchJobItem;

class CancelBatchJob
{
    public function __invoke(BatchJobCancelled $event): void
    {
        /** @var BatchJob|null $batchJob */
        $batchJob = BatchJob::where('uuid', $event->aggregateRootUuid())->first();

        if (! $batchJob) {
            return;
        }

        // Update batch job status
        $batchJob->update(
            [
                'status'       => 'cancelled',
                'completed_at' => $event->cancelledAt,
                'metadata'     => array_merge(
                    $batchJob->metadata ?? [],
                    [
                        'cancellation_reason' => $event->reason,
                    ]
                ),
            ]
        );

        // Cancel all pending items (stored in batch_job_items, like
        // CreateBatchJob and UpdateBatchItem use)
        BatchJobItem::where('batch_job_uuid', $batchJob->uuid)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }
}
