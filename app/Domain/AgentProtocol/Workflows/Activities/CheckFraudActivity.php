<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Services\FraudDetectionService;
use Exception;
use Workflow\Activity;

class CheckFraudActivity extends Activity
{
    public function __construct(
        private readonly FraudDetectionService $fraudDetectionService
    ) {
    }

    public function execute(
        string $transactionId,
        string $agentId,
        float $amount,
        array $metadata = []
    ): array {
        try {
            $analysis = $this->fraudDetectionService->analyzeTransaction(
                transactionId: $transactionId,
                agentId: $agentId,
                amount: $amount,
                metadata: $metadata
            );

            return array_merge($analysis, ['success' => true]);
        } catch (Exception $e) {
            return [
                'success'      => false,
                'error'        => $e->getMessage(),
                'risk_score'   => 100.0, // Maximum risk on error
                'risk_level'   => 'critical',
                'decision'     => 'reject',
                'risk_factors' => ['error' => ['score' => 100, 'message' => $e->getMessage()]],
            ];
        }
    }
}
