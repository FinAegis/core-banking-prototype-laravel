<?php

declare(strict_types=1);

namespace App\Domain\AI\Sagas;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\ChildWorkflows\Risk\CreditRiskWorkflow;
use App\Domain\AI\ChildWorkflows\Risk\FraudDetectionWorkflow;
use App\Models\User;
use Exception;
use Generator;
use InvalidArgumentException;
use Log;
use RuntimeException;
use Workflow\Workflow;

/**
 * Risk Assessment Saga.
 *
 * Orchestrates comprehensive risk assessment with compensation support.
 * Refactored to use Child Workflows for better separation of concerns.
 */
class RiskAssessmentSaga extends Workflow
{
    /**
     * @var array<callable>
     */
    protected array $compensationStack = [];

    /**
     * Execute risk assessment saga.
     *
     * @param string $conversationId
     * @param string $userId
     * @param string $assessmentType
     * @param array $parameters
     *
     * @return Generator
     */
    public function execute(
        string $conversationId,
        string $userId,
        string $assessmentType,
        array $parameters = []
    ): Generator {
        $aggregate = AIInteractionAggregate::retrieve($conversationId);

        try {
            // Initialize assessment
            $aggregate->startConversation(
                $conversationId,
                'risk-assessment',
                $userId,
                ['assessment_type' => $assessmentType]
            );
            $aggregate->persist();

            // Step 1: Load user and financial data
            $user = yield $this->loadUserData($userId);
            $this->compensationStack[] = fn () => $this->logCompensation('user_load', $userId);

            $financialData = yield $this->loadFinancialData($user);
            $this->compensationStack[] = fn () => $this->logCompensation('financial_load', $userId);

            // Step 2: Execute assessment based on type
            $assessment = yield from $this->performAssessment(
                $assessmentType,
                $conversationId,
                $user,
                $financialData,
                $parameters
            );

            // Step 3: Calculate composite risk score
            $compositeScore = $this->calculateCompositeScore($assessment);

            // Step 4: Generate alerts if needed
            $alerts = $this->generateAlerts($compositeScore, $assessment);

            // Step 5: Generate recommendations
            $recommendations = $this->generateRecommendations($compositeScore, $assessment);

            // Record successful assessment
            $aggregate->makeDecision(
                decision: 'risk_assessment_completed',
                reasoning: [
                    'type'   => $assessmentType,
                    'score'  => $compositeScore,
                    'alerts' => $alerts,
                ],
                confidence: $this->calculateConfidence($compositeScore)
            );
            $aggregate->persist();

            return [
                'success'         => true,
                'assessment_type' => $assessmentType,
                'risk_score'      => $compositeScore,
                'assessment'      => $assessment,
                'alerts'          => $alerts,
                'recommendations' => $recommendations,
                'metadata'        => [
                    'conversation_id' => $conversationId,
                    'user_id'         => $userId,
                    'timestamp'       => now()->toIso8601String(),
                ],
            ];
        } catch (Exception $e) {
            // Execute compensation in reverse order
            yield from $this->compensate();

            // Record failure
            $aggregate->makeDecision(
                decision: 'saga_failed',
                reasoning: [
                    'saga'  => 'risk_assessment',
                    'error' => $e->getMessage(),
                    'type'  => $assessmentType,
                ],
                confidence: 0.0
            );
            $aggregate->persist();

            throw new RuntimeException(
                "Risk assessment failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Load user data.
     */
    private function loadUserData(string $userId): User
    {
        $user = User::find($userId);

        if (! $user) {
            throw new RuntimeException("User not found: {$userId}");
        }

        $user->load(['accounts', 'transactions']);

        return $user;
    }

    /**
     * Load financial data for user.
     */
    private function loadFinancialData(User $user): array
    {
        $accounts = $user->accounts()->with('balances')->get();
        $transactions = $user->transactions()->latest()->limit(100)->get();

        return [
            'accounts'     => $accounts,
            'transactions' => $transactions,
            'loans'        => collect(), // Would load from loan service
            'investments'  => collect(), // Would load from investment service
            'total_assets' => $accounts->sum(fn ($account) => $account->balances->sum('balance')),
            'total_debt'   => 0, // Would calculate from loans
        ];
    }

    /**
     * Perform assessment based on type.
     *
     * @return Generator
     */
    private function performAssessment(
        string $type,
        string $conversationId,
        User $user,
        array $financialData,
        array $parameters
    ): Generator {
        return match ($type) {
            'credit'        => yield from $this->assessCredit($conversationId, $user, $financialData, $parameters),
            'fraud'         => yield from $this->assessFraud($conversationId, $user, $parameters),
            'comprehensive' => yield from $this->assessComprehensive($conversationId, $user, $financialData, $parameters),
            default         => throw new InvalidArgumentException("Unknown assessment type: {$type}")
        };
    }

    /**
     * Assess credit risk using Child Workflow.
     *
     * @return Generator
     */
    private function assessCredit(
        string $conversationId,
        User $user,
        array $financialData,
        array $parameters
    ): Generator {
        $creditAssessment = yield from app(CreditRiskWorkflow::class)->execute(
            $conversationId,
            $user,
            $financialData,
            $parameters
        );

        $this->compensationStack[] = fn () => $this->logCompensation('credit_assessment', (string) $user->id);

        return ['credit' => $creditAssessment];
    }

    /**
     * Assess fraud risk using Child Workflow.
     *
     * @return Generator
     */
    private function assessFraud(
        string $conversationId,
        User $user,
        array $parameters
    ): Generator {
        $fraudAssessment = yield from app(FraudDetectionWorkflow::class)->execute(
            $conversationId,
            $user,
            $parameters
        );

        $this->compensationStack[] = fn () => $this->logCompensation('fraud_assessment', (string) $user->id);

        return ['fraud' => $fraudAssessment];
    }

    /**
     * Perform comprehensive assessment.
     *
     * @return Generator
     */
    private function assessComprehensive(
        string $conversationId,
        User $user,
        array $financialData,
        array $parameters
    ): Generator {
        // Run both assessments
        $creditResult = yield from $this->assessCredit($conversationId, $user, $financialData, $parameters);
        $fraudResult = yield from $this->assessFraud($conversationId, $user, $parameters);

        return array_merge($creditResult, $fraudResult);
    }

    /**
     * Calculate composite risk score.
     */
    private function calculateCompositeScore(array $assessment): float
    {
        $scores = [];
        $weights = [];

        if (isset($assessment['credit'])) {
            // Convert risk level to score
            $creditScore = match ($assessment['credit']['risk_level']) {
                'low'    => 20,
                'medium' => 50,
                'high'   => 80,
                default  => 50
            };
            $scores['credit'] = $creditScore;
            $weights['credit'] = 0.5;
        }

        if (isset($assessment['fraud'])) {
            $scores['fraud'] = $assessment['fraud']['fraud_score'];
            $weights['fraud'] = 0.5;
        }

        // Calculate weighted average
        $totalScore = 0;
        $totalWeight = 0;

        foreach ($scores as $type => $score) {
            $totalScore += $score * $weights[$type];
            $totalWeight += $weights[$type];
        }

        return $totalWeight > 0 ? round($totalScore / $totalWeight, 2) : 0;
    }

    /**
     * Generate alerts based on assessment.
     */
    private function generateAlerts(float $compositeScore, array $assessment): array
    {
        $alerts = [];

        if ($compositeScore > 75) {
            $alerts[] = [
                'level'   => 'critical',
                'message' => 'High overall risk detected',
                'score'   => $compositeScore,
            ];
        }

        if (isset($assessment['credit']) && ! $assessment['credit']['approved']) {
            $alerts[] = [
                'level'   => 'warning',
                'message' => 'Credit application would be declined',
            ];
        }

        if (isset($assessment['fraud']) && $assessment['fraud']['block_transaction']) {
            $alerts[] = [
                'level'   => 'critical',
                'message' => 'Transaction should be blocked due to fraud risk',
            ];
        }

        return $alerts;
    }

    /**
     * Generate recommendations based on assessment.
     */
    private function generateRecommendations(float $compositeScore, array $assessment): array
    {
        $recommendations = [];

        if ($compositeScore > 60) {
            $recommendations[] = 'Consider additional verification measures';
            $recommendations[] = 'Review account activity regularly';
        }

        if (isset($assessment['credit']) && $assessment['credit']['dti_ratio'] > 0.4) {
            $recommendations[] = 'Work on reducing debt-to-income ratio';
        }

        if (isset($assessment['fraud']) && $assessment['fraud']['requires_2fa']) {
            $recommendations[] = 'Enable two-factor authentication';
        }

        return $recommendations;
    }

    /**
     * Calculate confidence based on score clarity.
     */
    private function calculateConfidence(float $compositeScore): float
    {
        return match (true) {
            $compositeScore < 30 => 0.9,  // Low risk, high confidence
            $compositeScore > 70 => 0.9,  // High risk, high confidence
            default              => 0.6                 // Medium risk, lower confidence
        };
    }

    /**
     * Log compensation action.
     */
    private function logCompensation(string $action, string $entityId): void
    {
        Log::info('Risk assessment compensation', [
            'action'    => $action,
            'entity_id' => $entityId,
        ]);
    }

    /**
     * Execute compensation actions in reverse order.
     *
     * @return Generator
     */
    public function compensate(): Generator
    {
        while ($compensation = array_pop($this->compensationStack)) {
            try {
                yield $compensation();
            } catch (Exception $e) {
                Log::error('Compensation failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
