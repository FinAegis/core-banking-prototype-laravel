<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Sagas;

use App\Domain\Treasury\Aggregates\TreasuryAggregate;
use App\Domain\Treasury\Events\RiskAssessmentCompleted;
use App\Domain\Treasury\Events\TreasuryAccountCreated;
use App\Domain\Treasury\Events\YieldOptimizationStarted;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;

class RiskManagementSaga extends Reactor
{
    private array $sagaData = [];

    private string $sagaId;

    public function __construct()
    {
        $this->sagaId = Str::uuid()->toString();
    }

    /**
     * Handle Treasury Account Created event - start risk assessment.
     */
    public function onTreasuryAccountCreated(TreasuryAccountCreated $event): void
    {
        $this->sagaData[$event->accountId] = [
            'saga_id'    => $this->sagaId,
            'account_id' => $event->accountId,
            'status'     => 'initiated',
            'created_at' => now(),
        ];

        // Trigger initial risk assessment
        $this->performRiskAssessment($event->accountId, $event->initialBalance);
    }

    /**
     * Handle Yield Optimization Started event - monitor risk.
     */
    public function onYieldOptimizationStarted(YieldOptimizationStarted $event): void
    {
        // Check if risk level requires intervention
        $riskProfile = RiskProfile::fromScore(
            $this->calculateRiskScoreFromOptimization($event)
        );

        if ($riskProfile->requiresApproval()) {
            $this->escalateForApproval($event->accountId, $riskProfile);
        } else {
            $this->monitorRisk($event->accountId, $riskProfile);
        }
    }

    /**
     * Handle Risk Assessment Completed event.
     */
    public function onRiskAssessmentCompleted(RiskAssessmentCompleted $event): void
    {
        $this->sagaData[$event->accountId]['last_assessment'] = [
            'assessment_id' => $event->assessmentId,
            'risk_score'    => $event->riskScore,
            'risk_level'    => $event->riskLevel,
            'timestamp'     => now(),
        ];

        // Take action based on risk level
        $this->handleRiskAssessmentResult($event);
    }

    private function performRiskAssessment(string $accountId, float $balance): void
    {
        try {
            // Calculate risk factors
            $riskFactors = $this->analyzeRiskFactors($accountId, $balance);

            // Calculate overall risk score
            $riskScore = $this->calculateRiskScore($riskFactors);

            // Create risk profile
            $riskProfile = RiskProfile::fromScore($riskScore, $riskFactors);

            // Generate recommendations
            $recommendations = $this->generateRecommendations($riskProfile, $balance);

            // Update Treasury Aggregate
            $aggregate = TreasuryAggregate::retrieve($accountId);
            $aggregate->completeRiskAssessment(
                Str::uuid()->toString(),
                $riskProfile,
                $recommendations,
                'risk_management_saga'
            );
            $aggregate->persist();

            Log::info('Risk assessment completed', [
                'saga_id'    => $this->sagaId,
                'account_id' => $accountId,
                'risk_score' => $riskScore,
            ]);
        } catch (Exception $e) {
            Log::error('Risk assessment failed', [
                'saga_id'    => $this->sagaId,
                'account_id' => $accountId,
                'error'      => $e->getMessage(),
            ]);

            // Compensate
            $this->compensate($accountId);
        }
    }

    private function analyzeRiskFactors(string $accountId, float $balance): array
    {
        $factors = [];

        // Market risk
        $factors['market_volatility'] = $this->assessMarketVolatility();

        // Credit risk
        $factors['counterparty_risk'] = $this->assessCounterpartyRisk($accountId);

        // Liquidity risk
        $factors['liquidity_risk'] = $this->assessLiquidityRisk($balance);

        // Operational risk
        $factors['operational_risk'] = $this->assessOperationalRisk();

        // Regulatory risk
        $factors['regulatory_risk'] = $this->assessRegulatoryRisk();

        return $factors;
    }

    private function calculateRiskScore(array $riskFactors): float
    {
        $weights = [
            'market_volatility' => 0.3,
            'counterparty_risk' => 0.25,
            'liquidity_risk'    => 0.2,
            'operational_risk'  => 0.15,
            'regulatory_risk'   => 0.1,
        ];

        $weightedScore = 0;
        foreach ($riskFactors as $factor => $score) {
            $weightedScore += $score * ($weights[$factor] ?? 0.1);
        }

        return min(100, $weightedScore);
    }

    private function assessMarketVolatility(): float
    {
        // In production, would fetch real market data
        // For demo, return simulated volatility score
        return rand(20, 60);
    }

    private function assessCounterpartyRisk(string $accountId): float
    {
        // In production, would analyze counterparty creditworthiness
        // For demo, return simulated risk score
        return rand(10, 40);
    }

    private function assessLiquidityRisk(float $balance): float
    {
        // Higher balance = lower liquidity risk
        if ($balance > 10000000) {
            return 15;
        } elseif ($balance > 5000000) {
            return 30;
        } elseif ($balance > 1000000) {
            return 45;
        } else {
            return 60;
        }
    }

    private function assessOperationalRisk(): float
    {
        // In production, would analyze operational metrics
        // For demo, return low operational risk
        return 20;
    }

    private function assessRegulatoryRisk(): float
    {
        // In production, would check compliance status
        // For demo, return low regulatory risk
        return 15;
    }

    private function generateRecommendations(RiskProfile $riskProfile, float $balance): array
    {
        $recommendations = [];

        if ($riskProfile->getScore() > 70) {
            $recommendations[] = 'Reduce exposure to high-risk instruments';
            $recommendations[] = 'Increase cash reserves to ' . ($balance * 0.4);
            $recommendations[] = 'Implement daily risk monitoring';
        } elseif ($riskProfile->getScore() > 50) {
            $recommendations[] = 'Diversify portfolio across asset classes';
            $recommendations[] = 'Maintain minimum 25% liquidity buffer';
            $recommendations[] = 'Review risk limits weekly';
        } else {
            $recommendations[] = 'Continue current risk management strategy';
            $recommendations[] = 'Consider yield optimization opportunities';
            $recommendations[] = 'Review risk profile quarterly';
        }

        return $recommendations;
    }

    private function calculateRiskScoreFromOptimization(YieldOptimizationStarted $event): float
    {
        $baseScore = match ($event->riskProfile) {
            'low'       => 20,
            'medium'    => 45,
            'high'      => 70,
            'very_high' => 85,
            default     => 50,
        };

        // Adjust based on target yield
        if ($event->targetYield > 10) {
            $baseScore += 15;
        } elseif ($event->targetYield > 7) {
            $baseScore += 10;
        }

        return min(100, $baseScore);
    }

    private function escalateForApproval(string $accountId, RiskProfile $riskProfile): void
    {
        Log::warning('High risk operation requires approval', [
            'saga_id'    => $this->sagaId,
            'account_id' => $accountId,
            'risk_level' => $riskProfile->getLevel(),
            'risk_score' => $riskProfile->getScore(),
        ]);

        // In production, would trigger approval workflow
        // For demo, log the escalation
        $this->sagaData[$accountId]['escalations'][] = [
            'timestamp'  => now(),
            'risk_level' => $riskProfile->getLevel(),
            'status'     => 'pending_approval',
        ];
    }

    private function monitorRisk(string $accountId, RiskProfile $riskProfile): void
    {
        $this->sagaData[$accountId]['monitoring'][] = [
            'timestamp'  => now(),
            'risk_level' => $riskProfile->getLevel(),
            'risk_score' => $riskProfile->getScore(),
        ];

        // Schedule next risk assessment if needed
        if ($riskProfile->getScore() > 50) {
            // In production, would schedule job for daily monitoring
            Log::info('Scheduling daily risk monitoring', [
                'saga_id'    => $this->sagaId,
                'account_id' => $accountId,
            ]);
        }
    }

    private function handleRiskAssessmentResult(RiskAssessmentCompleted $event): void
    {
        $riskProfile = RiskProfile::fromScore($event->riskScore, $event->riskFactors);

        if (! $riskProfile->isAcceptable()) {
            // Trigger risk mitigation
            $this->mitigateRisk($event->accountId, $riskProfile);
        } else {
            // Continue normal operations
            $this->sagaData[$event->accountId]['status'] = 'monitoring';
        }
    }

    private function mitigateRisk(string $accountId, RiskProfile $riskProfile): void
    {
        Log::info('Initiating risk mitigation', [
            'saga_id'    => $this->sagaId,
            'account_id' => $accountId,
            'risk_level' => $riskProfile->getLevel(),
        ]);

        // In production, would trigger risk mitigation workflows
        // For demo, log the mitigation
        $this->sagaData[$accountId]['mitigations'][] = [
            'timestamp'  => now(),
            'risk_level' => $riskProfile->getLevel(),
            'actions'    => [
                'reduce_exposure',
                'increase_liquidity',
                'hedge_positions',
            ],
        ];
    }

    public function compensate(string $accountId): void
    {
        Log::info('Compensating risk management saga', [
            'saga_id'    => $this->sagaId,
            'account_id' => $accountId,
        ]);

        // Mark saga as compensated
        $this->sagaData[$accountId]['status'] = 'compensated';
        $this->sagaData[$accountId]['compensated_at'] = now();

        // In production, would reverse any partial operations
    }

    public function getSagaId(): string
    {
        return $this->sagaId;
    }

    public function getSagaData(): array
    {
        return $this->sagaData;
    }
}
