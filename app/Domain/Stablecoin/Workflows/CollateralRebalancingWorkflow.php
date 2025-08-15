<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows;

use App\Domain\Stablecoin\Activities\AnalyzeCollateralPortfolioActivity;
use App\Domain\Stablecoin\Activities\CalculateRebalancingStrategyActivity;
use App\Domain\Stablecoin\Activities\ExecuteCollateralSwapActivity;
use App\Domain\Stablecoin\Activities\ValidateRebalancingActivity;
use App\Domain\Stablecoin\Aggregates\CollateralPositionAggregate;
use Illuminate\Support\Facades\Log;
use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class CollateralRebalancingWorkflow extends Workflow
{
    private array $compensations = [];

    /**
     * Execute collateral rebalancing workflow.
     */
    public function execute(
        string $positionId,
        array $targetAllocation,
        array $rebalancingParams = []
    ): \Generator {
        Log::info('Starting collateral rebalancing workflow', [
            'position_id'       => $positionId,
            'target_allocation' => $targetAllocation,
        ]);

        try {
            // Step 1: Analyze current collateral portfolio
            $currentPortfolio = yield ActivityStub::make(
                AnalyzeCollateralPortfolioActivity::class,
                $positionId
            );

            Log::info('Current portfolio analyzed', [
                'position_id'        => $positionId,
                'current_allocation' => $currentPortfolio['allocation'],
                'total_value'        => $currentPortfolio['total_value'],
            ]);

            // Step 2: Calculate optimal rebalancing strategy
            $rebalancingStrategy = yield ActivityStub::make(
                CalculateRebalancingStrategyActivity::class,
                $currentPortfolio,
                $targetAllocation,
                $rebalancingParams
            );

            Log::info('Rebalancing strategy calculated', [
                'position_id'    => $positionId,
                'swaps_required' => count($rebalancingStrategy['swaps']),
                'estimated_cost' => $rebalancingStrategy['estimated_cost'],
            ]);

            // Step 3: Validate rebalancing won't cause liquidation
            $validation = yield ActivityStub::make(
                ValidateRebalancingActivity::class,
                $positionId,
                $rebalancingStrategy
            );

            if (! $validation['is_safe']) {
                Log::warning('Rebalancing would make position unhealthy', [
                    'position_id'        => $positionId,
                    'health_ratio_after' => $validation['health_ratio_after'],
                ]);

                throw new \DomainException(
                    'Rebalancing would reduce collateral ratio below safe threshold'
                );
            }

            // Step 4: Execute collateral swaps
            foreach ($rebalancingStrategy['swaps'] as $index => $swap) {
                Log::info('Executing swap', [
                    'position_id' => $positionId,
                    'swap_index'  => $index + 1,
                    'from_asset'  => $swap['from_asset'],
                    'to_asset'    => $swap['to_asset'],
                    'amount'      => $swap['amount'],
                ]);

                $swapResult = yield ActivityStub::make(
                    ExecuteCollateralSwapActivity::class,
                    $positionId,
                    $swap
                );

                // Add compensation for rollback if needed
                $this->compensations[] = function () use ($positionId, $swap, $swapResult) {
                    return ActivityStub::make(
                        ExecuteCollateralSwapActivity::class,
                        $positionId,
                        [
                            'from_asset'      => $swap['to_asset'],
                            'to_asset'        => $swap['from_asset'],
                            'amount'          => $swapResult['amount_received'],
                            'is_compensation' => true,
                        ]
                    );
                };

                Log::info('Swap executed successfully', [
                    'position_id'     => $positionId,
                    'swap_index'      => $index + 1,
                    'amount_received' => $swapResult['amount_received'],
                ]);
            }

            // Step 5: Update position with new allocation
            $aggregate = CollateralPositionAggregate::retrieve($positionId);
            $aggregate->rebalanceCollateral($rebalancingStrategy['final_allocation']);
            $aggregate->persist();

            // Step 6: Verify final health status
            $finalHealth = yield ActivityStub::make(
                ValidateRebalancingActivity::class,
                $positionId,
                ['final_allocation' => $rebalancingStrategy['final_allocation']]
            );

            Log::info('Rebalancing completed successfully', [
                'position_id'        => $positionId,
                'final_health_ratio' => $finalHealth['health_ratio_after'],
                'total_cost'         => $rebalancingStrategy['estimated_cost'],
            ]);

            return [
                'success'             => true,
                'position_id'         => $positionId,
                'previous_allocation' => $currentPortfolio['allocation'],
                'final_allocation'    => $rebalancingStrategy['final_allocation'],
                'swaps_executed'      => count($rebalancingStrategy['swaps']),
                'total_cost'          => $rebalancingStrategy['estimated_cost'],
                'final_health_ratio'  => $finalHealth['health_ratio_after'],
            ];
        } catch (\Throwable $e) {
            Log::error('Rebalancing workflow failed', [
                'position_id' => $positionId,
                'error'       => $e->getMessage(),
            ]);

            // Execute compensations in reverse order
            yield from $this->compensate();

            throw $e;
        }
    }

    /**
     * Execute compensation actions in reverse order.
     */
    public function compensate(): \Generator
    {
        Log::info('Executing compensations', [
            'compensation_count' => count($this->compensations),
        ]);

        foreach (array_reverse($this->compensations) as $compensation) {
            try {
                yield $compensation();
            } catch (\Throwable $e) {
                Log::error('Compensation failed', [
                    'error' => $e->getMessage(),
                ]);
                // Continue with other compensations
            }
        }
    }

    /**
     * Rebalance multiple positions in parallel.
     */
    public function rebalanceMultiplePositions(
        array $positions,
        array $globalTargetAllocation
    ): \Generator {
        $workflows = [];

        foreach ($positions as $position) {
            $workflows[] = ChildWorkflowStub::make(
                self::class,
                $position['id'],
                $globalTargetAllocation,
                $position['params'] ?? []
            );
        }

        // Execute all rebalancing workflows in parallel
        $results = yield $workflows;

        $summary = [
            'total_positions' => count($positions),
            'successful'      => 0,
            'failed'          => 0,
            'results'         => [],
        ];

        foreach ($results as $index => $result) {
            if ($result['success'] ?? false) {
                $summary['successful']++;
            } else {
                $summary['failed']++;
            }
            $summary['results'][] = $result;
        }

        Log::info('Multiple position rebalancing completed', $summary);

        return $summary;
    }
}
