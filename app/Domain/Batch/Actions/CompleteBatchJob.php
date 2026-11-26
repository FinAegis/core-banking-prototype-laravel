<?php

namespace App\Domain\Batch\Actions;

use App\Domain\Batch\Events\BatchJobCompleted;
use App\Domain\Batch\Models\BatchJob;
use Illuminate\Support\Carbon;

class CompleteBatchJob
{
    /**
     * The event carries an ISO 8601 time; the query builder does not cast it,
     * and MySQL and MariaDB refuse that format for a DATETIME column.
     */
    public function __invoke(BatchJobCompleted $event): void
    {
        BatchJob::where('uuid', $event->aggregateRootUuid())
            ->update(
                [
                    'status'       => $event->finalStatus,
                    'completed_at' => Carbon::parse($event->completedAt),
                ]
            );
    }
}
