<?php

declare(strict_types=1);

namespace App\Domain\AI\ChildWorkflows\Risk;

use App\Domain\AI\Activities\Risk\AnalyzeTransactionVelocityActivity;
use App\Domain\AI\Activities\Risk\DetectAnomaliesActivity;
use App\Domain\AI\Activities\Risk\VerifyDeviceAndLocationActivity;
use App\Domain\AI\Events\Risk\FraudAssessedEvent;
use App\Models\User;
use Generator;
use Workflow\Workflow;

/**
 * Fraud Detection Child Workflow.
 *
 * Orchestrates fraud risk detection activities.
 */
class FraudDetectionWorkflow extends Workflow
{
    /**
     * Execute fraud detection workflow.
     *
     * @param string $conversationId
     * @param User $user
     * @param array $parameters
     *
     * @return Generator
     */
    public function execute(
        string $conversationId,
        User $user,
        array $parameters
    ): Generator {
        $amount = $parameters['amount'] ?? 0;
        $recipient = $parameters['recipient'] ?? null;
        $transactionId = $parameters['transaction_id'] ?? null;

        // Analyze transaction velocity
        $velocityAnalysis = yield app(AnalyzeTransactionVelocityActivity::class)->execute([
            'user_id' => (string) $user->id,
            'amount'  => (float) $amount,
        ]);

        // Detect anomalies
        $anomalies = yield app(DetectAnomaliesActivity::class)->execute([
            'user_id'   => (string) $user->id,
            'amount'    => (float) $amount,
            'recipient' => $recipient,
        ]);

        // Verify device and location
        $verification = yield app(VerifyDeviceAndLocationActivity::class)->execute([
            'user_id' => (string) $user->id,
            'request' => is_array($parameters['request'] ?? null) ? $parameters['request'] : null,
        ]);

        // Calculate fraud score
        $fraudScore = $this->calculateFraudScore(
            $velocityAnalysis,
            $anomalies,
            $verification
        );

        // Compile assessment
        $assessment = [
            'fraud_score'       => $fraudScore,
            'velocity_analysis' => $velocityAnalysis,
            'anomalies'         => $anomalies,
            'verification'      => $verification,
            'risk_level'        => $this->determineFraudRiskLevel($fraudScore),
            'requires_2fa'      => $fraudScore > 30,
            'block_transaction' => $fraudScore > 80,
            'recommendations'   => $this->generateRecommendations($fraudScore, $anomalies),
        ];

        // Emit event
        event(new FraudAssessedEvent(
            $conversationId,
            (string) $user->id,
            $transactionId,
            $assessment
        ));

        return $assessment;
    }

    private function calculateFraudScore(
        array $velocityAnalysis,
        array $anomalies,
        array $verification
    ): float {
        $score = 0;

        // Velocity component (0-40)
        if ($velocityAnalysis['violated']) {
            $score += $velocityAnalysis['severity'] * 40;
        }

        // Anomalies component (0-40)
        $anomalyCount = count($anomalies['detected']);
        $score += min($anomalyCount * 10, 40);

        // Verification component (0-20)
        if (! $verification['device_trusted']) {
            $score += 10;
        }
        if (! $verification['location_verified']) {
            $score += 10;
        }

        return min($score, 100);
    }

    private function determineFraudRiskLevel(float $fraudScore): string
    {
        return match (true) {
            $fraudScore < 30 => 'low',
            $fraudScore < 60 => 'medium',
            default          => 'high'
        };
    }

    private function generateRecommendations(float $fraudScore, array $anomalies): array
    {
        $recommendations = [];

        if ($fraudScore > 30) {
            $recommendations[] = 'Enable two-factor authentication';
        }

        if ($fraudScore > 60) {
            $recommendations[] = 'Review recent account activity';
            $recommendations[] = 'Update security settings';
        }

        if (count($anomalies['detected']) > 2) {
            $recommendations[] = 'Contact customer for verification';
        }

        return $recommendations;
    }
}
