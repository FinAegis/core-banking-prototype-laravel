<?php

namespace App\Domain\Batch\Actions;

use App\Domain\Batch\Events\BatchJobStarted;
use App\Domain\Batch\Models\BatchJob;
use Illuminate\Support\Carbon;

class StartBatchJob
{
    /**
     * The event carries an ISO 8601 time; the query builder does not cast it,
     * and MySQL and MariaDB refuse that format for a DATETIME column.
     */
    public function __invoke(BatchJobStarted $event): void
    {
        BatchJob::where('uuid', $event->aggregateRootUuid())
            ->update(
                [
                    'status'     => 'processing',
                    'started_at' => Carbon::parse($event->startedAt),
                ]
            );
    }
}
