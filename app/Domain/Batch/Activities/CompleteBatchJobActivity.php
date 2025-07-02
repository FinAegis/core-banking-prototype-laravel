<?php

namespace App\Domain\Batch\Activities;

use App\Domain\Batch\Aggregates\BatchAggregate;
use Workflow\Activity;

class CompleteBatchJobActivity extends Activity
{
    /**
     * @param string $batchJobUuid
     * @param array $results
     * @return void
     */
    public function execute(string $batchJobUuid, array $results): void
    {
        // Complete the batch job
        BatchAggregate::retrieve($batchJobUuid)
            ->completeBatchJob()
            ->persist();
    }
}