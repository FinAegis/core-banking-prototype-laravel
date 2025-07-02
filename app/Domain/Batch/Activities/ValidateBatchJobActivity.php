<?php

namespace App\Domain\Batch\Activities;

use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Domain\Batch\Models\BatchJob;
use Workflow\Activity;

class ValidateBatchJobActivity extends Activity
{
    /**
     * @param string $batchJobUuid
     * @return BatchJob
     */
    public function execute(string $batchJobUuid): BatchJob
    {
        $batchJob = BatchJob::where('uuid', $batchJobUuid)->first();
        
        if (!$batchJob) {
            throw new \InvalidArgumentException("Batch job not found: {$batchJobUuid}");
        }
        
        if ($batchJob->status !== 'pending') {
            throw new \InvalidArgumentException("Batch job is not in pending status: {$batchJob->status}");
        }
        
        // Start the batch job
        BatchAggregate::retrieve($batchJobUuid)
            ->startBatchJob()
            ->persist();
        
        return $batchJob;
    }
}