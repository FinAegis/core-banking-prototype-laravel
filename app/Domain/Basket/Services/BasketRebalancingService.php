<?php

declare(strict_types=1);

namespace App\Domain\Basket\Services;

use App\Models\BasketAsset;
use App\Models\BasketValue;
use App\Domain\Basket\Events\BasketRebalanced;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\Facades\Projectionist;

class BasketRebalancingService
{
    public function __construct(
        private readonly BasketValueCalculationService $valueCalculationService
    ) {}

    /**
     * Check if a basket needs rebalancing.
     */
    public function needsRebalancing(BasketAsset $basket): bool
    {
        return $basket->needsRebalancing();
    }

    /**
     * Check if a basket needs rebalancing and perform it if necessary.
     */
    public function rebalanceIfNeeded(BasketAsset $basket): ?array
    {
        if (!$basket->needsRebalancing()) {
            return null;
        }

        return $this->rebalance($basket);
    }

    /**
     * Force rebalancing of a basket.
     */
    public function rebalance(BasketAsset $basket): array
    {
        if ($basket->type !== 'dynamic') {
            throw new \Exception('Only dynamic baskets can be rebalanced');
        }

        // Calculate current value to get component weights
        $currentValue = $this->valueCalculationService->calculateValue($basket);
        
        if ($currentValue->value <= 0) {
            throw new \Exception('Cannot rebalance basket with zero or negative value');
        }

        $adjustments = $this->calculateAdjustments($basket, $currentValue);

        // Check if weights need normalization even if no individual adjustments needed
        $totalWeight = $basket->activeComponents->sum('weight');
        $needsNormalization = abs($totalWeight - 100) > 0.01;

        if (empty($adjustments) && !$needsNormalization) {
            Log::info("Basket {$basket->code} does not need rebalancing");
            return [
                'status' => 'completed',
                'basket' => $basket->code,
                'adjustments' => [],
                'adjustments_count' => 0,
                'checked_at' => now()->toISOString(),
            ];
        }

        // Perform rebalancing
        $result = $this->executeRebalancing($basket, $adjustments);

        return $result;
    }

    /**
     * Calculate required adjustments for rebalancing.
     */
    private function calculateAdjustments(BasketAsset $basket, BasketValue $currentValue): array
    {
        $adjustments = [];
        $components = $basket->activeComponents;

        foreach ($components as $component) {
            $currentComponentWeight = $component->weight;
            $needsAdjustment = false;
            $adjustmentTarget = $currentComponentWeight;

            // Check if component weight is outside allowed bounds
            if ($component->min_weight !== null && $currentComponentWeight < $component->min_weight) {
                $needsAdjustment = true;
                $adjustmentTarget = $component->min_weight;
            } elseif ($component->max_weight !== null && $currentComponentWeight > $component->max_weight) {
                $needsAdjustment = true;
                $adjustmentTarget = $component->max_weight;
            }

            if ($needsAdjustment) {
                $adjustments[] = [
                    'asset' => $component->asset_code,
                    'current_weight' => round($currentComponentWeight, 2),
                    'target_weight' => round($adjustmentTarget, 2),
                    'adjustment' => round($adjustmentTarget - $currentComponentWeight, 2),
                    'action' => $adjustmentTarget > $currentComponentWeight ? 'increase' : 'decrease',
                ];
            }
        }

        return $adjustments;
    }

    /**
     * Execute the rebalancing adjustments.
     */
    private function executeRebalancing(BasketAsset $basket, array $adjustments): array
    {
        return DB::transaction(function () use ($basket, $adjustments) {
            // Apply weight adjustments to components
            foreach ($adjustments as $adjustment) {
                $component = $basket->components()
                    ->where('asset_code', $adjustment['asset'])
                    ->first();
                    
                if ($component) {
                    $component->update(['weight' => $adjustment['target_weight']]);
                }
            }

            // Normalize weights to ensure they sum to 100%
            $this->normalizeWeights($basket);

            // Record the rebalancing event
            $rebalancedEvent = new BasketRebalanced(
                basketCode: $basket->code,
                adjustments: $adjustments,
                rebalancedAt: now()
            );
            event($rebalancedEvent);

            // Update the basket's last rebalanced timestamp
            $basket->update(['last_rebalanced_at' => now()]);

            // Invalidate cached values
            $this->valueCalculationService->invalidateCache($basket);

            // Log the rebalancing
            Log::info("Basket {$basket->code} rebalanced", [
                'adjustments' => $adjustments,
                'timestamp' => now()->toISOString(),
            ]);

            return [
                'status' => 'completed',
                'basket' => $basket->code,
                'adjustments' => $adjustments,
                'adjustments_count' => count($adjustments),
                'rebalanced_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Normalize component weights to sum to 100%.
     */
    private function normalizeWeights(BasketAsset $basket): void
    {
        $components = $basket->activeComponents()->get();
        $totalWeight = $components->sum('weight');
        
        if (abs($totalWeight - 100) < 0.01) {
            return; // Already normalized
        }
        
        if ($totalWeight <= 0) {
            return; // Cannot normalize zero or negative weights
        }
        
        // Calculate the difference that needs to be distributed
        $difference = 100 - $totalWeight;
        
        // Calculate available capacity for adjustment
        $availableCapacity = 0;
        $adjustableComponents = [];
        
        foreach ($components as $component) {
            $capacity = 0;
            
            if ($difference > 0) { // Need to increase weights
                $maxPossible = $component->max_weight ?? 100; // No limit = can go to 100%
                $capacity = max(0, $maxPossible - $component->weight);
            } else { // Need to decrease weights
                $minPossible = $component->min_weight ?? 0; // No limit = can go to 0%
                $capacity = max(0, $component->weight - $minPossible);
            }
            
            if ($capacity > 0.01) { // Small threshold to avoid floating point issues
                $adjustableComponents[] = [
                    'component' => $component,
                    'capacity' => $capacity,
                ];
                $availableCapacity += $capacity;
            }
        }
        
        if (empty($adjustableComponents) || $availableCapacity < abs($difference)) {
            // Cannot adjust without violating constraints or insufficient capacity
            // Use proportional scaling as fallback
            $scaleFactor = 100 / $totalWeight;
            foreach ($components as $component) {
                $newWeight = $component->weight * $scaleFactor;
                $component->update(['weight' => $newWeight]);
            }
            return;
        }
        
        // Distribute the difference proportionally based on available capacity
        foreach ($adjustableComponents as $adjustable) {
            $component = $adjustable['component'];
            $capacity = $adjustable['capacity'];
            
            // Calculate this component's share of the adjustment
            $proportionalAdjustment = ($capacity / $availableCapacity) * $difference;
            $newWeight = $component->weight + $proportionalAdjustment;
            
            // Clamp to bounds if they exist (should not be necessary but safety check)
            if ($component->min_weight !== null) {
                $newWeight = max($newWeight, $component->min_weight);
            }
            if ($component->max_weight !== null) {
                $newWeight = min($newWeight, $component->max_weight);
            }
            
            $component->update(['weight' => $newWeight]);
        }
    }

    /**
     * Rebalance all baskets that need it.
     */
    public function rebalanceAll(): array
    {
        $baskets = BasketAsset::query()->needsRebalancing()->get();
        $results = [
            'rebalanced' => [],
            'no_changes' => [],
            'failed' => [],
        ];

        foreach ($baskets as $basket) {
            try {
                $result = $this->rebalance($basket);
                
                if ($result['status'] === 'rebalanced') {
                    $results['rebalanced'][] = $result;
                } else {
                    $results['no_changes'][] = $result;
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'basket' => $basket->code,
                    'error' => $e->getMessage(),
                ];
                
                Log::error("Failed to rebalance basket {$basket->code}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Simulate rebalancing without executing it.
     */
    public function simulateRebalancing(BasketAsset $basket): array
    {
        if ($basket->type !== 'dynamic') {
            throw new \Exception('Only dynamic baskets can be rebalanced');
        }

        $currentValue = $this->valueCalculationService->calculateValue($basket);
        $adjustments = $this->calculateAdjustments($basket, $currentValue);

        return [
            'status' => 'simulated',
            'basket' => $basket->code,
            'current_value' => $currentValue->value,
            'adjustments' => $adjustments,
            'adjustments_count' => count($adjustments),
            'needs_rebalancing' => !empty($adjustments),
            'simulated' => true,
            'simulated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get rebalancing history for a basket.
     */
    public function getRebalancingHistory(BasketAsset $basket, int $limit = 10): array
    {
        // This would query the event store for BasketRebalanced events
        // For now, return a placeholder
        return [
            'basket' => $basket->code,
            'history' => [],
            'message' => 'Rebalancing history will be available after event store integration',
        ];
    }
}