<?php

declare(strict_types=1);

namespace App\Domain\Account\Workflows;

use DateTime;
use Workflow\Activity;

/**
 * Activity to create a summary of completed batch operations.
 */
class CreateBatchSummaryActivity extends Activity
{
    /**
     * Create a summary of all completed batch operations.
     */
    public function execute(array $completedOperations, string $batchId): array
    {
        $startTime = null;
        $endTime = null;
        $successfulOperations = 0;
        $failedOperations = 0;
        $operationDetails = [];

        foreach ($completedOperations as $operation) {
            // Track start and end times
            if ($startTime === null || $operation['result']['start_time'] < $startTime) {
                $startTime = $operation['result']['start_time'];
            }
            if ($endTime === null || $operation['result']['end_time'] > $endTime) {
                $endTime = $operation['result']['end_time'];
            }

            // Count successes and failures
            if ($operation['result']['status'] === 'success') {
                $successfulOperations++;
            } else {
                $failedOperations++;
            }

            // Build operation details
            $operationDetails[] = [
                'operation' => $operation['operation'],
                'status'    => $operation['result']['status'],
                'result'    => $operation['result']['result'] ?? null,
            ];
        }

        // Calculate duration
        $duration = null;
        if ($startTime && $endTime) {
            $start = new DateTime($startTime);
            $end = new DateTime($endTime);
            $duration = $end->getTimestamp() - $start->getTimestamp();
        }

        $summary = [
            'batch_id'              => $batchId,
            'total_operations'      => count($completedOperations),
            'successful_operations' => $successfulOperations,
            'failed_operations'     => $failedOperations,
            'start_time'            => $startTime,
            'end_time'              => $endTime,
            'duration_seconds'      => $duration,
            'results'               => $operationDetails,
        ];

        logger()->info('Batch processing summary created', $summary);

        return $summary;
    }
}
