<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Services;

use App\Domain\Treasury\Aggregates\PortfolioAggregate;
use App\Domain\Treasury\Models\PortfolioEvent;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class RebalancingService
{
    private const CACHE_TTL = 300; // 5 minutes cache

    private const MIN_REBALANCE_AMOUNT = 1000.0; // Minimum amount to trigger rebalancing

    private const TRANSACTION_COST_RATE = 0.001; // 0.1% transaction cost

    public function __construct(
        private readonly PortfolioManagementService $portfolioService
    ) {
    }

    public function checkRebalancingNeeded(string $portfolioId): bool
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        $cacheKey = "rebalancing_needed:{$portfolioId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId) {
            try {
                $portfolio = $this->portfolioService->getPortfolio($portfolioId);

                if (empty($portfolio['strategy']) || $portfolio['is_rebalancing']) {
                    return false;
                }

                $threshold = $portfolio['strategy']['rebalanceThreshold'] ?? 5.0;

                foreach ($portfolio['asset_allocations'] as $allocation) {
                    $drift = $allocation['drift'] ?? 0.0;
                    if ($drift > $threshold) {
                        return true;
                    }
                }

                return false;
            } catch (Exception $e) {
                throw new RuntimeException("Failed to check rebalancing need: {$e->getMessage()}", 0, $e);
            }
        });
    }

    public function calculateRebalancingPlan(string $portfolioId): array
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId);

            if (empty($portfolio['strategy'])) {
                throw new InvalidArgumentException('Portfolio must have an investment strategy');
            }

            if ($portfolio['total_value'] <= 0) {
                throw new InvalidArgumentException('Portfolio must have positive value for rebalancing');
            }

            $currentAllocations = $portfolio['asset_allocations'];
            $totalValue = $portfolio['total_value'];
            $threshold = $portfolio['strategy']['rebalanceThreshold'];

            $rebalancingActions = [];
            $totalTransactionCost = 0.0;

            foreach ($currentAllocations as $allocation) {
                $currentWeight = $allocation['currentWeight'] ?? 0.0;
                $targetWeight = $allocation['targetWeight'] ?? 0.0;
                $drift = $allocation['drift'] ?? 0.0;

                $currentValue = ($currentWeight / 100) * $totalValue;
                $targetValue = ($targetWeight / 100) * $totalValue;
                $difference = $targetValue - $currentValue;

                if ($drift > $threshold && abs($difference) >= self::MIN_REBALANCE_AMOUNT) {
                    $action = [
                        'asset_class'    => $allocation['assetClass'] ?? 'unknown',
                        'current_weight' => $currentWeight,
                        'target_weight'  => $targetWeight,
                        'current_value'  => $currentValue,
                        'target_value'   => $targetValue,
                        'difference'     => $difference,
                        'action_type'    => $difference > 0 ? 'buy' : 'sell',
                        'amount'         => abs($difference),
                        'drift'          => $drift,
                        'priority'       => $this->calculateRebalancingPriority($drift, $threshold),
                    ];

                    $transactionCost = $action['amount'] * self::TRANSACTION_COST_RATE;
                    $action['transaction_cost'] = $transactionCost;
                    $totalTransactionCost += $transactionCost;

                    $rebalancingActions[] = $action;
                }
            }

            // Sort by priority (highest drift first)
            usort($rebalancingActions, fn ($a, $b) => $b['priority'] <=> $a['priority']);

            return [
                'portfolio_id'              => $portfolioId,
                'total_portfolio_value'     => $totalValue,
                'rebalance_threshold'       => $threshold,
                'actions'                   => $rebalancingActions,
                'total_transaction_cost'    => $totalTransactionCost,
                'estimated_completion_time' => $this->estimateCompletionTime(count($rebalancingActions)),
                'net_benefit'               => $this->calculateNetBenefit($rebalancingActions, $totalTransactionCost),
                'risk_impact'               => $this->assessRiskImpact($currentAllocations, $portfolio['strategy']),
                'recommended'               => $this->shouldRecommendRebalancing($rebalancingActions, $totalTransactionCost),
            ];
        } catch (Exception $e) {
            throw new RuntimeException("Failed to calculate rebalancing plan: {$e->getMessage()}", 0, $e);
        }
    }

    public function executeRebalancing(string $portfolioId, array $plan): void
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        if (empty($plan) || empty($plan['actions'])) {
            throw new InvalidArgumentException('Rebalancing plan cannot be empty');
        }

        try {
            // Validate plan structure
            $this->validateRebalancingPlan($plan);

            $aggregate = PortfolioAggregate::retrieve($portfolioId);

            // Check if already rebalancing
            if ($aggregate->isRebalancing()) {
                throw new InvalidArgumentException('Portfolio is already being rebalanced');
            }

            $rebalanceId = Str::uuid()->toString();

            // Create target allocations from plan
            $targetAllocations = [];
            foreach ($plan['actions'] as $action) {
                $targetAllocations[] = [
                    'assetClass'   => $action['asset_class'],
                    'targetWeight' => $action['target_weight'],
                    'amount'       => $action['target_value'],
                ];
            }

            // Execute rebalancing through aggregate
            $aggregate->rebalancePortfolio(
                $rebalanceId,
                $targetAllocations,
                'scheduled_rebalancing',
                'system'
            );

            $aggregate->persist();

            // Clear relevant caches
            Cache::forget("portfolio:{$portfolioId}");
            Cache::forget("rebalancing_needed:{$portfolioId}");
            Cache::forget("rebalancing_history:{$portfolioId}");
        } catch (Exception $e) {
            throw new RuntimeException("Failed to execute rebalancing: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @return Collection<int, array{event_id: int, event_type: string, portfolio_id: string|null, timestamp: string, data: array, reason: mixed, initiated_by: mixed}>
     */
    public function getRebalancingHistory(string $portfolioId): Collection
    {
        if (empty($portfolioId)) {
            throw new InvalidArgumentException('Portfolio ID cannot be empty');
        }

        $cacheKey = "rebalancing_history:{$portfolioId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($portfolioId) {
            try {
                // Query portfolio events for rebalancing history
                $events = PortfolioEvent::where('aggregate_uuid', $portfolioId)
                    ->whereIn('event_class', [
                        'App\Domain\Treasury\Events\Portfolio\PortfolioRebalanced',
                        'App\Domain\Treasury\Events\Portfolio\RebalancingTriggered',
                    ])
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();

                /** @var Collection<int, array{event_id: int, event_type: string, portfolio_id: string|null, timestamp: string, data: array, reason: mixed, initiated_by: mixed}> */
                $mappedEvents = $events->map(function ($event) {
                    $eventData = $event->event_properties;

                    return [
                        'event_id'     => $event->id,
                        'event_type'   => class_basename($event->event_class),
                        'portfolio_id' => $event->aggregate_uuid,
                        'timestamp'    => is_string($event->created_at) ? $event->created_at : $event->created_at->toISOString(),
                        'data'         => $eventData,
                        'reason'       => $eventData['reason'] ?? 'unknown',
                        'initiated_by' => $eventData['initiatedBy'] ?? 'system',
                    ];
                });

                /** @phpstan-ignore-next-line */
                return $mappedEvents;
            } catch (Exception $e) {
                throw new RuntimeException("Failed to get rebalancing history: {$e->getMessage()}", 0, $e);
            }
        });
    }

    /**
     * Get rebalancing metrics and statistics.
     */
    public function getRebalancingMetrics(string $portfolioId): array
    {
        $history = $this->getRebalancingHistory($portfolioId);

        if ($history->isEmpty()) {
            return [
                'total_rebalances'       => 0,
                'last_rebalance'         => null,
                'average_frequency_days' => null,
                'success_rate'           => 0,
            ];
        }

        $rebalanceEvents = $history->where('event_type', 'PortfolioRebalanced');
        $totalRebalances = $rebalanceEvents->count();

        $lastRebalance = $rebalanceEvents->first();
        $firstRebalance = $rebalanceEvents->last();

        $averageFrequency = null;
        if ($totalRebalances > 1 && $firstRebalance && $lastRebalance) {
            $daysDifference = now()->parse($lastRebalance['timestamp'])
                ->diffInDays(now()->parse($firstRebalance['timestamp']));
            $averageFrequency = $daysDifference / ($totalRebalances - 1);
        }

        return [
            'total_rebalances'       => $totalRebalances,
            'last_rebalance'         => $lastRebalance ? $lastRebalance['timestamp'] : null,
            'average_frequency_days' => $averageFrequency,
            'success_rate'           => 100, // Simplified - in reality would track failed rebalances
        ];
    }

    private function calculateRebalancingPriority(float $drift, float $threshold): float
    {
        // Higher drift gets higher priority
        return min($drift / $threshold, 10.0);
    }

    private function estimateCompletionTime(int $actionCount): string
    {
        // Estimate based on number of actions (simplified)
        $minutesPerAction = 5;
        $totalMinutes = $actionCount * $minutesPerAction;

        if ($totalMinutes < 60) {
            return "{$totalMinutes} minutes";
        }

        $hours = intval($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }

    private function calculateNetBenefit(array $actions, float $transactionCost): float
    {
        // Simplified calculation - in reality would factor in expected return improvement
        $totalRebalanceValue = array_sum(array_column($actions, 'amount'));
        $benefitRate = 0.02; // Assume 2% annual benefit from optimal allocation

        $annualBenefit = $totalRebalanceValue * $benefitRate;

        return $annualBenefit - $transactionCost;
    }

    private function assessRiskImpact(array $allocations, array $strategy): string
    {
        $avgDrift = array_sum(array_column($allocations, 'drift')) / count($allocations);
        $threshold = $strategy['rebalanceThreshold'] ?? 5.0;

        if ($avgDrift > $threshold * 2) {
            return 'high_risk_reduction';
        } elseif ($avgDrift > $threshold) {
            return 'moderate_risk_reduction';
        } else {
            return 'low_risk_reduction';
        }
    }

    private function shouldRecommendRebalancing(array $actions, float $transactionCost): bool
    {
        if (empty($actions)) {
            return false;
        }

        $netBenefit = $this->calculateNetBenefit($actions, $transactionCost);
        $highPriorityActions = array_filter($actions, fn ($action) => $action['priority'] > 2.0);

        return $netBenefit > 0 && count($highPriorityActions) > 0;
    }

    private function validateRebalancingPlan(array $plan): void
    {
        $requiredFields = ['portfolio_id', 'actions'];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $plan)) {
                throw new InvalidArgumentException("Missing required plan field: {$field}");
            }
        }

        foreach ($plan['actions'] as $action) {
            $actionFields = ['asset_class', 'target_weight', 'action_type', 'amount'];

            foreach ($actionFields as $field) {
                if (! array_key_exists($field, $action)) {
                    throw new InvalidArgumentException("Missing required action field: {$field}");
                }
            }

            if (! in_array($action['action_type'], ['buy', 'sell'])) {
                throw new InvalidArgumentException('Action type must be buy or sell');
            }

            if ($action['amount'] <= 0) {
                throw new InvalidArgumentException('Action amount must be positive');
            }
        }
    }
}
