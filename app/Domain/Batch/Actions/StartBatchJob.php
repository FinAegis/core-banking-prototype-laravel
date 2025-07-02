<?php

namespace App\Domain\Batch\Actions;

use App\Domain\Batch\Events\BatchJobStarted;
use App\Models\BatchJob;

class StartBatchJob
{
    /**
     * @param BatchJobStarted $event
     * @return void
     */
    public function __invoke(BatchJobStarted $event): void
    {
        BatchJob::where('uuid', $event->aggregateRootUuid())
            ->update([
                'status' => 'processing',
                'started_at' => $event->startedAt,
            ]);
    }
}