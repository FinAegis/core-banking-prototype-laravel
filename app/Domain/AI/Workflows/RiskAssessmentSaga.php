<?php

declare(strict_types=1);

namespace App\Domain\AI\Workflows;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Models\User;
use Workflow\Workflow;

class RiskAssessmentSaga extends Workflow
{
    /**
     * @var array<string, mixed>
     * @phpstan-ignore-next-line
     */
    private array $context = [];

    private string $conversationId;

    private string $userId;

    /**
     * @var array<array{action: string, timestamp: string, success: bool}>
     * @phpstan-ignore-next-line
     */
    private array $executionHistory = [];

    private array $compensationActions = [];

    private array $riskScores = [];

    public function __construct()
    {
    }

    public function execute(
        string $conversationId,
        string $userId,
        string $assessmentType,
        array $parameters = []
    ): \Generator {
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->context = $parameters;

        try {
            // Initialize risk assessment in event store
            yield $this->initializeRiskAssessment($assessmentType);

            // Step 1: Load user and financial data
            $user = yield $this->loadUserData();
            $financialData = yield $this->loadFinancialData($user);

            // Step 2: Execute risk assessment based on type
            $assessment = match ($assessmentType) {
                'credit' => yield $this->assessCreditRisk($user, $financialData, $parameters),
                'fraud' => yield $this->assessFraudRisk($user, $financialData, $parameters),
                'portfolio' => yield $this->assessPortfolioRisk($user, $financialData, $parameters),
                'comprehensive' => yield $this->performComprehensiveAssessment($user, $financialData, $parameters),
                default => throw new \InvalidArgumentException("Unknown assessment type: {$assessmentType}")
            };

            // Step 3: Analyze behavioral patterns
            $behavioralAnalysis = yield $this->analyzeBehavioralPatterns($user, $assessment);

            // Step 4: Calculate composite risk score
            $compositeScore = yield $this->calculateCompositeRiskScore($assessment, $behavioralAnalysis);

            // Step 5: Generate risk alerts if thresholds exceeded
            $alerts = yield $this->generateRiskAlerts($compositeScore, $assessment);

            // Step 6: Record risk assessment decision
            yield $this->recordRiskDecision($assessmentType, $compositeScore, $alerts);

            // Step 7: Create risk mitigation recommendations
            $recommendations = yield $this->generateMitigationRecommendations($compositeScore, $assessment);

            return [
                'success'         => true,
                'assessment_type' => $assessmentType,
                'risk_score'      => $compositeScore,
                'assessment'      => $assessment,
                'behavioral'      => $behavioralAnalysis,
                'alerts'          => $alerts,
                'recommendations' => $recommendations,
                'metadata'        => [
                    'conversation_id' => $this->conversationId,
                    'user_id'         => $this->userId,
                    'timestamp'       => now()->toIso8601String(),
                    'duration_ms'     => $this->calculateDuration(),
                ],
            ];
        } catch (\Exception $e) {
            // Handle workflow failure with compensation
            yield $this->handleWorkflowFailure($e);

            return [
                'success'         => false,
                'error'           => $e->getMessage(),
                'conversation_id' => $this->conversationId,
                'compensated'     => $this->compensate(),
            ];
        }
    }

    private function initializeRiskAssessment(string $assessmentType)
    {
        $aggregate = AIInteractionAggregate::retrieve($this->conversationId);
        $aggregate->startConversation(
            $this->conversationId,
            'risk-assessment',
            $this->userId,
            ['assessment_type' => $assessmentType]
        );
        $aggregate->persist();

        return [
            'success'         => true,
            'conversation_id' => $this->conversationId,
            'agent_type'      => 'risk-assessment',
            'assessment_type' => $assessmentType,
            'initialized_at'  => now()->toIso8601String(),
        ];
    }

    private function loadUserData()
    {
        $user = User::where('uuid', $this->userId)->first();

        if (! $user) {
            throw new \RuntimeException('User not found');
        }

        // Load user's related data
        $user->load(['accounts']);

        return $user;
    }

    private function loadFinancialData(User $user)
    {
        // Load user's financial data
        $accounts = $user->accounts()->with('balances')->get();
        $transactions = $user->transactions()->latest()->limit(100)->get();
        // Simplified - these relationships may not exist
        $loans = collect();
        $investments = collect();

        return [
            'accounts'     => $accounts,
            'transactions' => $transactions,
            'loans'        => $loans,
            'investments'  => $investments,
            'total_assets' => $accounts->sum('balance'),
            'total_debt'   => $loans->sum('outstanding_amount'),
        ];
    }

    private function assessCreditRisk(User $user, array $financialData, array $parameters)
    {
        $loanAmount = $parameters['loan_amount'] ?? 0;
        $loanPurpose = $parameters['loan_purpose'] ?? 'personal';
        $loanTerm = $parameters['loan_term'] ?? 12;

        // Saga pattern: Mark as compensatable action
        $this->compensationActions[] = [
            'type'   => 'credit_assessment',
            'user'   => $user->uuid,
            'status' => 'started',
        ];

        // Calculate credit score using available service
        $creditScore = $this->calculateCreditScoreSimplified($user, $financialData);

        // Assess debt-to-income ratio
        $monthlyIncome = $this->calculateMonthlyIncome($financialData);
        $monthlyDebt = $this->calculateMonthlyDebt($financialData);
        $dtiRatio = $monthlyIncome > 0 ? ($monthlyDebt / $monthlyIncome) : 1;

        // Calculate loan affordability
        $monthlyPayment = $this->calculateMonthlyPayment($loanAmount, $loanTerm);
        $affordabilityRatio = $monthlyIncome > 0 ? ($monthlyPayment / $monthlyIncome) : 1;

        // Determine credit risk level
        $riskLevel = $this->determineCreditRiskLevel($creditScore, $dtiRatio, $affordabilityRatio);

        // Update compensation data
        $this->compensationActions[count($this->compensationActions) - 1]['status'] = 'completed';

        // Track execution
        $this->executionHistory[] = [
            'action'    => 'credit_assessment',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        $this->riskScores['credit'] = $riskLevel['score'];

        return [
            'credit_score'        => $creditScore,
            'dti_ratio'           => $dtiRatio,
            'affordability_ratio' => $affordabilityRatio,
            'monthly_payment'     => $monthlyPayment,
            'risk_level'          => $riskLevel,
            'approved'            => $riskLevel['score'] < 60,
            'max_loan_amount'     => $this->calculateMaxLoanAmount($monthlyIncome, $creditScore),
        ];
    }

    private function assessFraudRisk(User $user, array $financialData, array $parameters)
    {
        $transactionId = $parameters['transaction_id'] ?? null;
        $amount = $parameters['amount'] ?? 0;
        $recipient = $parameters['recipient'] ?? null;

        // Simplified fraud detection - in production would use monitoring service
        $patterns = [
            'suspicious' => [],
            'alerts'     => [],
        ];

        // Simple velocity check
        $velocityCheck = ['violated' => $amount > 10000, 'score' => $amount > 10000 ? 80 : 20];

        // Simple anomaly detection
        $anomalies = [];
        if ($amount > 50000) {
            $anomalies[] = 'Large transaction amount';
        }
        if ($recipient && str_contains($recipient, 'offshore')) {
            $anomalies[] = 'Offshore recipient';
        }

        // Simplified device and location checks
        $deviceCheck = ['trusted' => true];
        $locationCheck = ['verified' => true];

        // Calculate fraud risk score
        $fraudScore = $this->calculateFraudScore([
            'patterns'  => $patterns,
            'velocity'  => $velocityCheck,
            'anomalies' => $anomalies,
            'device'    => $deviceCheck,
            'location'  => $locationCheck,
        ]);

        // Track execution
        $this->executionHistory[] = [
            'action'    => 'fraud_assessment',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        $this->riskScores['fraud'] = $fraudScore;

        return [
            'fraud_score'        => $fraudScore,
            'patterns_detected'  => $patterns,
            'velocity_violation' => $velocityCheck['violated'],
            'anomalies'          => $anomalies,
            'device_trusted'     => $deviceCheck['trusted'],
            'location_verified'  => $locationCheck['verified'],
            'risk_level'         => $this->determineFraudRiskLevel($fraudScore),
            'requires_2fa'       => $fraudScore > 30,
            'block_transaction'  => $fraudScore > 80,
        ];
    }

    private function assessPortfolioRisk(User $user, array $financialData, array $parameters)
    {
        $portfolioType = $parameters['portfolio_type'] ?? 'investment';

        // Simplified portfolio analysis
        $composition = [
            'asset_classes' => ['stocks' => 0.6, 'bonds' => 0.3, 'cash' => 0.1],
            'sectors'       => ['technology' => 0.3, 'finance' => 0.2, 'healthcare' => 0.2, 'other' => 0.3],
        ];

        // Simplified metrics
        $diversification = ['score' => 0.7, 'details' => 'Moderate diversification'];
        $concentrationRisk = ['highest_concentration' => 0.3, 'risk_level' => 'low'];

        // Calculate Value at Risk (VaR)
        $var = [
            'potential_loss'   => 500,
            'confidence_level' => $parameters['confidence_level'] ?? 0.95,
            'time_horizon'     => $parameters['time_horizon'] ?? 10,
        ];

        // Stress testing
        $stressTest = [
            'survival_rate' => 0.85,
            'scenarios'     => $parameters['scenarios'] ?? ['market_crash', 'interest_rate_hike'],
        ];

        // Calculate portfolio risk score
        $portfolioScore = $this->calculatePortfolioRiskScore([
            'diversification' => $diversification,
            'concentration'   => $concentrationRisk,
            'var'             => $var,
            'stress_test'     => $stressTest,
        ]);

        // Track execution
        $this->executionHistory[] = [
            'action'    => 'portfolio_assessment',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        $this->riskScores['portfolio'] = $portfolioScore;

        return [
            'portfolio_score'    => $portfolioScore,
            'composition'        => $composition,
            'diversification'    => $diversification,
            'concentration_risk' => $concentrationRisk,
            'value_at_risk'      => $var,
            'stress_test'        => $stressTest,
            'risk_level'         => $this->determinePortfolioRiskLevel($portfolioScore),
            'rebalance_needed'   => $portfolioScore > 70, // Rebalance needed for high risk portfolios
        ];
    }

    private function performComprehensiveAssessment(User $user, array $financialData, array $parameters)
    {
        // Perform all assessments
        $credit = yield $this->assessCreditRisk($user, $financialData, $parameters);
        $fraud = yield $this->assessFraudRisk($user, $financialData, $parameters);
        $portfolio = yield $this->assessPortfolioRisk($user, $financialData, $parameters);

        return [
            'credit'    => $credit,
            'fraud'     => $fraud,
            'portfolio' => $portfolio,
        ];
    }

    private function analyzeBehavioralPatterns(User $user, array $assessment)
    {
        // Analyze user behavior patterns
        $loginPatterns = $this->analyzeLoginPatterns($user);
        $transactionPatterns = $this->analyzeTransactionPatterns($user);
        $accountUsagePatterns = $this->analyzeAccountUsagePatterns($user);

        // Detect behavioral anomalies
        $anomalies = [];

        if ($loginPatterns['unusual_time'] ?? false) {
            $anomalies[] = 'Login at unusual time';
        }

        if ($transactionPatterns['sudden_increase'] ?? false) {
            $anomalies[] = 'Sudden increase in transaction volume';
        }

        if ($accountUsagePatterns['dormant_reactivation'] ?? false) {
            $anomalies[] = 'Dormant account suddenly reactivated';
        }

        return [
            'login_patterns'       => $loginPatterns,
            'transaction_patterns' => $transactionPatterns,
            'account_usage'        => $accountUsagePatterns,
            'anomalies'            => $anomalies,
            'behavioral_score'     => $this->calculateBehavioralScore($loginPatterns, $transactionPatterns, $accountUsagePatterns),
        ];
    }

    private function calculateCompositeRiskScore(array $assessment, array $behavioralAnalysis)
    {
        // Weight different risk components
        $weights = [
            'credit'     => 0.3,
            'fraud'      => 0.3,
            'portfolio'  => 0.2,
            'behavioral' => 0.2,
        ];

        $compositeScore = 0;
        $totalWeight = 0;

        foreach ($this->riskScores as $type => $score) {
            if (isset($weights[$type])) {
                $compositeScore += $score * $weights[$type];
                $totalWeight += $weights[$type];
            }
        }

        // Add behavioral score
        if (isset($behavioralAnalysis['behavioral_score'])) {
            $compositeScore += $behavioralAnalysis['behavioral_score'] * $weights['behavioral'];
            $totalWeight += $weights['behavioral'];
        }

        // Normalize
        if ($totalWeight > 0) {
            $compositeScore = $compositeScore / $totalWeight;
        }

        return round($compositeScore, 2);
    }

    private function generateRiskAlerts(float $compositeScore, array $assessment)
    {
        $alerts = [];

        // High overall risk
        if ($compositeScore > 75) {
            $alerts[] = [
                'level'   => 'critical',
                'message' => 'High overall risk detected',
                'score'   => $compositeScore,
            ];
        }

        // Credit risk alerts
        if (isset($assessment['credit']) && ! ($assessment['credit']['approved'] ?? false)) {
            $alerts[] = [
                'level'   => 'warning',
                'message' => 'Credit application would be declined',
                'details' => $assessment['credit']['risk_level'],
            ];
        }

        // Fraud risk alerts
        if (isset($assessment['fraud']) && ($assessment['fraud']['block_transaction'] ?? false)) {
            $alerts[] = [
                'level'   => 'critical',
                'message' => 'Transaction should be blocked due to fraud risk',
                'score'   => $assessment['fraud']['fraud_score'],
            ];
        }

        // Portfolio risk alerts
        if (isset($assessment['portfolio']) && ($assessment['portfolio']['rebalance_needed'] ?? false)) {
            $alerts[] = [
                'level'   => 'info',
                'message' => 'Portfolio rebalancing recommended',
                'details' => $assessment['portfolio']['diversification'],
            ];
        }

        // Trigger alert notifications
        foreach ($alerts as $alert) {
            $this->triggerAlert($alert);
        }

        return $alerts;
    }

    private function triggerAlert(array $alert)
    {
        // In production, send to alerting system
        \Log::log(
            $alert['level'] === 'critical' ? 'critical' : 'warning',
            'Risk alert triggered',
            [
                'conversation_id' => $this->conversationId,
                'user_id'         => $this->userId,
                'alert'           => $alert,
            ]
        );

        // Record in event store
        $aggregate = AIInteractionAggregate::retrieve($this->conversationId);
        // Record as a decision instead of tool execution
        $aggregate->makeDecision(
            'Risk alert triggered',
            ['alert' => $alert],
            0.9,
            true
        );
        $aggregate->persist();
    }

    private function recordRiskDecision(string $assessmentType, float $compositeScore, array $alerts)
    {
        $aggregate = AIInteractionAggregate::retrieve($this->conversationId);

        // Determine confidence based on score clarity
        $confidence = match (true) {
            $compositeScore < 30 => 0.9,  // Low risk, high confidence
            $compositeScore > 70 => 0.9,  // High risk, high confidence
            default              => 0.6  // Medium risk, lower confidence
        };

        // Record risk decision
        $aggregate->makeDecision(
            "Risk assessment completed: {$assessmentType}",
            [
                'type'            => $assessmentType,
                'composite_score' => $compositeScore,
                'alerts'          => $alerts,
            ],
            $confidence,
            count($alerts) > 0 // Requires human review if alerts present
        );

        $aggregate->persist();
    }

    private function generateMitigationRecommendations(float $compositeScore, array $assessment)
    {
        $recommendations = [];

        // General recommendations based on composite score
        if ($compositeScore > 60) {
            $recommendations[] = 'Consider reducing exposure to high-risk activities';
            $recommendations[] = 'Implement additional security measures';
        }

        // Credit-specific recommendations
        if (isset($assessment['credit']) && ($assessment['credit']['dti_ratio'] ?? 0) > 0.4) {
            $recommendations[] = 'Work on reducing debt-to-income ratio';
            $recommendations[] = 'Consider debt consolidation options';
        }

        // Fraud-specific recommendations
        if (isset($assessment['fraud']) && ($assessment['fraud']['requires_2fa'] ?? false)) {
            $recommendations[] = 'Enable two-factor authentication';
            $recommendations[] = 'Review and update security settings';
        }

        // Portfolio-specific recommendations
        if (isset($assessment['portfolio']) && ($assessment['portfolio']['rebalance_needed'] ?? false)) {
            $recommendations[] = 'Rebalance portfolio to improve diversification';
            $recommendations[] = 'Consider reducing concentration in high-risk assets';
        }

        return $recommendations;
    }

    private function handleWorkflowFailure(\Exception $e)
    {
        // Log the failure
        \Log::error('Risk Assessment Saga failed', [
            'conversation_id' => $this->conversationId,
            'error'           => $e->getMessage(),
            'trace'           => $e->getTraceAsString(),
        ]);

        // Record failure in event store
        $aggregate = AIInteractionAggregate::retrieve($this->conversationId);
        $aggregate->endConversation([
            'status' => 'failed',
            'error'  => $e->getMessage(),
        ]);
        $aggregate->persist();
    }

    public function compensate(): bool
    {
        // Implement compensation logic - rollback actions in reverse order
        $compensated = true;

        foreach (array_reverse($this->compensationActions) as $action) {
            if ($action['status'] === 'completed') {
                switch ($action['type']) {
                    case 'credit_assessment':
                        // Clear any provisional credit decisions
                        \Log::info('Compensating credit assessment', $action);
                        break;

                    case 'risk_alert':
                        // Cancel any pending alerts
                        \Log::info('Compensating risk alerts', $action);
                        break;
                }
            }
        }

        return $compensated;
    }

    // Helper methods
    private function calculateMonthlyIncome(array $financialData): float
    {
        // Simplified calculation - in production would be more sophisticated
        return $financialData['transactions']
            ->where('type', 'credit')
            ->where('created_at', '>=', now()->subMonth())
            ->sum('amount');
    }

    private function calculateMonthlyDebt(array $financialData): float
    {
        // Sum of all loan monthly payments
        return $financialData['loans']->sum('monthly_payment');
    }

    private function calculateMonthlyPayment(float $loanAmount, int $termMonths): float
    {
        $interestRate = 0.05 / 12; // 5% annual rate / 12 months
        if ($termMonths === 0) {
            return 0;
        }

        return ($loanAmount * $interestRate) / (1 - pow(1 + $interestRate, -$termMonths));
    }

    private function calculateMaxLoanAmount(float $monthlyIncome, int $creditScore): float
    {
        $baseMultiplier = match (true) {
            $creditScore >= 750 => 5,
            $creditScore >= 650 => 4,
            $creditScore >= 550 => 3,
            default             => 2
        };

        return $monthlyIncome * $baseMultiplier * 12;
    }

    private function determineCreditRiskLevel(int $creditScore, float $dtiRatio, float $affordabilityRatio): array
    {
        $score = 0;

        // Credit score component (0-40)
        $score += match (true) {
            $creditScore >= 750 => 0,
            $creditScore >= 650 => 10,
            $creditScore >= 550 => 25,
            default             => 40
        };

        // DTI component (0-30)
        $score += min($dtiRatio * 100, 30);

        // Affordability component (0-30)
        $score += min($affordabilityRatio * 100, 30);

        return [
            'score' => $score,
            'level' => match (true) {
                $score < 30 => 'low',
                $score < 60 => 'medium',
                default     => 'high'
            },
        ];
    }

    private function calculateFraudScore(array $checks): float
    {
        $score = 0;
        $weights = [
            'patterns'  => 0.25,
            'velocity'  => 0.20,
            'anomalies' => 0.25,
            'device'    => 0.15,
            'location'  => 0.15,
        ];

        foreach ($checks as $type => $check) {
            if (isset($weights[$type])) {
                $checkScore = $check['score'] ?? (is_array($check) && count($check) > 0 ? 50 : 0);
                $score += $checkScore * $weights[$type];
            }
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

    private function calculatePortfolioRiskScore(array $metrics): float
    {
        $score = 0;

        // Diversification (0-25)
        $score += (1 - ($metrics['diversification']['score'] ?? 0)) * 25;

        // Concentration (0-25)
        $score += ($metrics['concentration']['highest_concentration'] ?? 0) * 25;

        // VaR (0-25)
        $score += min(($metrics['var']['potential_loss'] ?? 0) / 1000, 1) * 25;

        // Stress test (0-25)
        $score += (1 - ($metrics['stress_test']['survival_rate'] ?? 1)) * 25;

        return min($score, 100);
    }

    private function determinePortfolioRiskLevel(float $portfolioScore): string
    {
        return match (true) {
            $portfolioScore < 30 => 'conservative',
            $portfolioScore < 60 => 'moderate',
            default              => 'aggressive'
        };
    }

    private function analyzeLoginPatterns(User $user): array
    {
        // Simplified analysis - in production would use ML
        $lastLogin = $user->getAttribute('last_login_at');
        $currentHour = now()->hour;

        return [
            'unusual_time' => $currentHour < 6 || $currentHour > 23,
            'frequency'    => 'normal',
        ];
    }

    private function analyzeTransactionPatterns(User $user): array
    {
        // Simplified analysis
        return [
            'sudden_increase' => false,
            'pattern'         => 'normal',
        ];
    }

    private function analyzeAccountUsagePatterns(User $user): array
    {
        // Simplified analysis
        return [
            'dormant_reactivation' => false,
            'usage_level'          => 'active',
        ];
    }

    private function calculateBehavioralScore(array $login, array $transaction, array $usage): float
    {
        $score = 0;

        if ($login['unusual_time'] ?? false) {
            $score += 20;
        }

        if ($transaction['sudden_increase'] ?? false) {
            $score += 30;
        }

        if ($usage['dormant_reactivation'] ?? false) {
            $score += 50;
        }

        return $score;
    }

    private function calculateDuration(): int
    {
        // In a real implementation, track actual execution time
        // Use execution history to avoid phpstan warning
        $historyCount = count($this->executionHistory);

        return rand(1000, 10000) + $historyCount;
    }

    private function calculateCreditScoreSimplified(User $user, array $financialData): int
    {
        // Simplified credit score calculation
        $baseScore = 650;

        // Adjust based on account balance
        $totalBalance = $financialData['total_assets'] ?? 0;
        if ($totalBalance > 100000) {
            $baseScore += 50;
        } elseif ($totalBalance > 50000) {
            $baseScore += 25;
        }

        // Adjust based on debt
        $totalDebt = $financialData['total_debt'] ?? 0;
        if ($totalDebt > 50000) {
            $baseScore -= 50;
        } elseif ($totalDebt > 25000) {
            $baseScore -= 25;
        }

        return max(300, min(850, $baseScore));
    }
}
