<?php

namespace App\Domain\Batch\Actions;

use App\Domain\Batch\Events\BatchJobCreated;
use App\Models\BatchJob;
use App\Models\BatchJobItem;

class CreateBatchJob
{
    /**
     * @param BatchJobCreated $event
     * @return void
     */
    public function __invoke(BatchJobCreated $event): void
    {
        $batchJob = BatchJob::create([
            'uuid' => $event->aggregateRootUuid(),
            'user_uuid' => $event->batchJob->userUuid,
            'name' => $event->batchJob->name,
            'type' => $event->batchJob->type,
            'status' => 'pending',
            'total_items' => count($event->batchJob->items),
            'processed_items' => 0,
            'failed_items' => 0,
            'scheduled_at' => $event->batchJob->scheduledAt ?? now(),
            'metadata' => $event->batchJob->metadata,
        ]);
        
        // Create batch items
        foreach ($event->batchJob->items as $index => $item) {
            BatchJobItem::create([
                'batch_job_uuid' => $batchJob->uuid,
                'sequence' => $index + 1,
                'status' => 'pending',
                'data' => $item,
            ]);
        }
    }
}