<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Workflows;

use App\Domain\Treasury\Activities\AllocateCashActivity;
use App\Domain\Treasury\Activities\AnalyzeLiquidityActivity;
use App\Domain\Treasury\Activities\OptimizeYieldActivity;
use App\Domain\Treasury\Activities\ValidateAllocationActivity;
use App\Domain\Treasury\Aggregates\TreasuryAggregate;
use App\Domain\Treasury\ValueObjects\AllocationStrategy;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use Illuminate\Support\Str;
use Workflow\Activity;
use Workflow\Workflow;

class CashManagementWorkflow extends Workflow
{
    private string $accountId;

    private float $totalAmount;

    private string $strategy;

    private array $constraints;

    private array $allocationResults = [];

    public function execute(
        string $accountId,
        float $totalAmount,
        string $strategy,
        array $constraints = []
    ) {
        $this->accountId = $accountId;
        $this->totalAmount = $totalAmount;
        $this->strategy = $strategy;
        $this->constraints = $constraints;

        try {
            // Step 1: Analyze current liquidity position
            $liquidityAnalysis = yield Activity::make(AnalyzeLiquidityActivity::class, [
                'account_id' => $this->accountId,
                'amount'     => $this->totalAmount,
            ]);

            // Step 2: Validate allocation strategy
            $validation = yield Activity::make(ValidateAllocationActivity::class, [
                'strategy'    => $this->strategy,
                'amount'      => $this->totalAmount,
                'liquidity'   => $liquidityAnalysis,
                'constraints' => $this->constraints,
            ]);

            if (! $validation['is_valid']) {
                throw new \RuntimeException('Allocation validation failed: ' . $validation['reason']);
            }

            // Step 3: Allocate cash based on strategy
            $allocation = yield Activity::make(AllocateCashActivity::class, [
                'account_id'  => $this->accountId,
                'strategy'    => $this->strategy,
                'amount'      => $this->totalAmount,
                'constraints' => $this->constraints,
            ]);

            $this->allocationResults = $allocation;

            // Step 4: Optimize yield for allocated cash
            $optimization = yield Activity::make(OptimizeYieldActivity::class, [
                'account_id'   => $this->accountId,
                'allocations'  => $allocation,
                'risk_profile' => $validation['risk_profile'],
                'target_yield' => $constraints['target_yield'] ?? 5.0,
            ]);

            // Step 5: Update Treasury Aggregate
            $aggregate = TreasuryAggregate::retrieve($this->accountId);

            $allocationStrategy = new AllocationStrategy($this->strategy, $allocation['allocations']);
            $riskProfile = RiskProfile::fromScore(
                $validation['risk_score'],
                $validation['risk_factors'] ?? []
            );

            $aggregate->allocateCash(
                Str::uuid()->toString(),
                $allocationStrategy,
                $this->totalAmount,
                'system'
            );

            $aggregate->startYieldOptimization(
                Str::uuid()->toString(),
                $optimization['strategy'],
                $optimization['expected_yield'],
                $riskProfile,
                $this->constraints,
                'system'
            );

            $aggregate->persist();

            return [
                'success'      => true,
                'account_id'   => $this->accountId,
                'allocation'   => $allocation,
                'optimization' => $optimization,
                'liquidity'    => $liquidityAnalysis,
                'risk_profile' => $validation['risk_profile'],
            ];
        } catch (\Exception $e) {
            // Compensation: Reverse allocations if any step fails
            yield from $this->compensate();

            throw new \RuntimeException('Cash management workflow failed: ' . $e->getMessage());
        }
    }

    public function compensate()
    {
        if (! empty($this->allocationResults)) {
            // Reverse the allocation
            yield Activity::make(AllocateCashActivity::class, [
                'account_id'  => $this->accountId,
                'strategy'    => 'reverse',
                'amount'      => $this->totalAmount,
                'allocations' => $this->allocationResults,
                'reverse'     => true,
            ]);
        }
    }
}
