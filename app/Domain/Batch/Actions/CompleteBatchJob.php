<?php

namespace App\Domain\Batch\Actions;

use App\Domain\Batch\Events\BatchJobCompleted;
use App\Models\BatchJob;

class CompleteBatchJob
{
    /**
     * @param BatchJobCompleted $event
     * @return void
     */
    public function __invoke(BatchJobCompleted $event): void
    {
        BatchJob::where('uuid', $event->aggregateRootUuid())
            ->update([
                'status' => $event->finalStatus,
                'completed_at' => $event->completedAt,
            ]);
    }
}