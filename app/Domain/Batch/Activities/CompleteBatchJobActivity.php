<?php

namespace App\Domain\Batch\Activities;

use App\Domain\Batch\Aggregates\BatchAggregate;
use Workflow\Activity;

class CompleteBatchJobActivity extends Activity
{
    public function execute(string $batchJobUuid, array $results): void
    {
        $aggregate = BatchAggregate::retrieve($batchJobUuid);

        // A batch cancelled while it ran stays cancelled; completing it would
        // throw and the activity would be retried without end.
        if ($aggregate->getStatus() !== 'processing') {
            return;
        }

        $aggregate->completeBatchJob()->persist();
    }
}
