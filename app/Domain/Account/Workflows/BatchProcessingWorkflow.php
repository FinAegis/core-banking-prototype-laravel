<?php

namespace App\Domain\Account\Workflows;

use Workflow\ActivityStub;
use Workflow\Workflow;

class BatchProcessingWorkflow extends Workflow
{
    /**
     * Execute end-of-day batch processing operations with compensation
     * 
     * @param array $operations - array of batch operations to perform
     * @param string|null $batchId
     *
     * @return \Generator
     */
    public function execute(array $operations, ?string $batchId = null): \Generator
    {
        $batchId = $batchId ?? \Illuminate\Support\Str::uuid();
        $completedOperations = [];
        
        try {
            // Process each operation individually to allow for granular compensation
            foreach ($operations as $operation) {
                $result = yield ActivityStub::make(
                    SingleBatchOperationActivity::class,
                    $operation,
                    $batchId
                );
                
                $completedOperations[] = [
                    'operation' => $operation,
                    'result' => $result,
                ];
                
                // Add compensation for this specific operation
                $this->addCompensation(fn() => ActivityStub::make(
                    ReverseBatchOperationActivity::class,
                    $operation,
                    $batchId,
                    $result
                ));
            }
            
            // Create summary after all operations complete
            $summary = yield ActivityStub::make(
                CreateBatchSummaryActivity::class,
                $completedOperations,
                $batchId
            );
            
            return $summary;
            
        } catch (\Throwable $th) {
            // Execute compensations in reverse order
            yield from $this->compensate();
            
            // Log batch processing failure
            logger()->error('Batch processing failed - compensations executed', [
                'batch_id' => $batchId,
                'operations' => $operations,
                'completed_operations' => $completedOperations,
                'error' => $th->getMessage(),
            ]);
            
            throw $th;
        }
    }
}