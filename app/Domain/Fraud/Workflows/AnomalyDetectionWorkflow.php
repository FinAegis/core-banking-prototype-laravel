<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Workflows;

use App\Domain\Fraud\Services\AnomalyDetectionOrchestrator;
use Exception;
use Generator;
use Illuminate\Support\Facades\Log;
use Workflow\Workflow;

class AnomalyDetectionWorkflow extends Workflow
{
    /**
     * Execute anomaly detection workflow.
     *
     * @param  array  $context  The analysis context (transaction data, user data, device data)
     * @param  string|null  $entityId  The entity being analyzed
     * @param  string|null  $entityType  The entity class
     * @param  int|null  $userId  The user ID
     * @param  string|null  $fraudScoreId  The associated fraud score ID
     * @return Generator
     */
    public function execute(
        array $context,
        ?string $entityId = null,
        ?string $entityType = null,
        ?int $userId = null,
        ?string $fraudScoreId = null,
    ): Generator {
        try {
            $orchestrator = app(AnomalyDetectionOrchestrator::class);

            $result = yield $orchestrator->detectAnomalies(
                $context,
                $entityId,
                $entityType,
                $userId,
                $fraudScoreId,
            );

            return $result;
        } catch (Exception $e) {
            Log::error('Anomaly detection workflow failed', [
                'error'     => $e->getMessage(),
                'entity_id' => $entityId,
            ]);

            return [
                'anomalies'     => [],
                'highest_score' => 0.0,
                'has_critical'  => false,
                'error'         => $e->getMessage(),
            ];
        }
    }
}
