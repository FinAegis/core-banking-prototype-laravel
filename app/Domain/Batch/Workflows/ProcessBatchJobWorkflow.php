<?php

namespace App\Domain\Batch\Workflows;

use App\Domain\Batch\Activities\CompleteBatchJobActivity;
use App\Domain\Batch\Activities\ProcessBatchItemActivity;
use App\Domain\Batch\Activities\ValidateBatchJobActivity;
use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ProcessBatchJobWorkflow extends Workflow
{
    public function execute(string $batchJobUuid): Generator
    {
        try {
            // Validate batch job
            $batchJob = yield ActivityStub::make(
                ValidateBatchJobActivity::class,
                $batchJobUuid
            );

            // Process each item
            $results = [];
            foreach ($batchJob->items as $index => $item) {
                try {
                    $result = yield ActivityStub::make(
                        ProcessBatchItemActivity::class,
                        $batchJobUuid,
                        $index,
                        $item
                    );
                    $results[$index] = $result;
                } catch (Throwable $e) {
                    // Continue processing other items even if one fails
                    $results[$index] = [
                        'status' => 'failed',
                        'error'  => $e->getMessage(),
                    ];
                }
            }

            // Complete the batch job
            yield ActivityStub::make(
                CompleteBatchJobActivity::class,
                $batchJobUuid,
                $results
            );

            return $results;
        } catch (Throwable $th) {
            // Compensate if needed
            yield from $this->compensate();
            throw $th;
        }
    }
}
