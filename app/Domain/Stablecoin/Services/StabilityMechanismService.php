<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Stablecoin\Contracts\StabilityMechanismServiceInterface;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StabilityMechanismService implements StabilityMechanismServiceInterface
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly CollateralService $collateralService,
        private readonly ?LiquidationService $liquidationService = null
    ) {}

    /**
     * Execute stability mechanisms for all active stablecoins.
     */
    public function executeStabilityMechanisms(): array
    {
        $results = [];
        $stablecoins = Stablecoin::active()->get();
        
        foreach ($stablecoins as $stablecoin) {
            try {
                $result = $this->executeStabilityMechanismForStablecoin($stablecoin);
                $results[$stablecoin->code] = $result;
            } catch (\Exception $e) {
                Log::error('Stability mechanism failed', [
                    'stablecoin_code' => $stablecoin->code,
                    'error' => $e->getMessage(),
                ]);
                
                $results[$stablecoin->code] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }

    /**
     * Execute stability mechanism for a specific stablecoin.
     */
    public function executeStabilityMechanismForStablecoin(Stablecoin $stablecoin): array
    {
        $mechanism = $stablecoin->stability_mechanism;
        
        return match ($mechanism) {
            'collateralized' => $this->executeCollateralizedMechanism($stablecoin),
            'algorithmic' => $this->executeAlgorithmicMechanism($stablecoin),
            'hybrid' => $this->executeHybridMechanism($stablecoin),
            default => throw new \InvalidArgumentException("Unknown stability mechanism: {$mechanism}")
        };
    }

    /**
     * Execute collateralized stability mechanism.
     */
    private function executeCollateralizedMechanism(Stablecoin $stablecoin): array
    {
        $actions = [];
        $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$stablecoin->code] ?? null;
        
        if (!$metrics) {
            return ['success' => false, 'error' => 'No metrics available'];
        }
        
        // 1. Check global collateralization ratio
        $globalRatio = $metrics['global_ratio'];
        $targetRatio = $stablecoin->collateral_ratio;
        $minRatio = $stablecoin->min_collateral_ratio;
        
        // 2. Handle undercollateralization
        if ($globalRatio < $minRatio) {
            if ($this->liquidationService) {
                $liquidationResult = $this->liquidationService->liquidateEligiblePositions();
                $actions[] = [
                    'type' => 'emergency_liquidation',
                    'reason' => 'Global collateralization below minimum',
                    'details' => $liquidationResult,
                ];
            } else {
                $actions[] = [
                    'type' => 'emergency_liquidation_needed',
                    'reason' => 'Global collateralization below minimum',
                    'details' => 'Liquidation service not available',
                ];
            }
        } elseif ($globalRatio < $targetRatio) {
            // Gradually increase collateral requirements or fees
            $actions[] = $this->adjustCollateralRequirements($stablecoin, 'increase');
        }
        
        // 3. Handle overcollateralization
        if ($globalRatio > $targetRatio * 1.5) {
            // Reduce collateral requirements to encourage more minting
            $actions[] = $this->adjustCollateralRequirements($stablecoin, 'decrease');
        }
        
        // 4. Monitor and liquidate risky positions
        $atRiskPositions = $this->collateralService->getPositionsAtRisk();
        if ($atRiskPositions->isNotEmpty()) {
            $actions[] = [
                'type' => 'risk_monitoring',
                'positions_at_risk' => $atRiskPositions->count(),
                'recommendations' => $this->generateRiskRecommendations($atRiskPositions),
            ];
        }
        
        return [
            'success' => true,
            'mechanism' => 'collateralized',
            'global_ratio' => $globalRatio,
            'target_ratio' => $targetRatio,
            'actions_taken' => $actions,
        ];
    }

    /**
     * Execute algorithmic stability mechanism.
     */
    private function executeAlgorithmicMechanism(Stablecoin $stablecoin): array
    {
        $actions = [];
        $currentPrice = $this->getCurrentPrice($stablecoin);
        $targetPrice = $stablecoin->target_price;
        $priceDeviation = abs($currentPrice - $targetPrice) / $targetPrice;
        
        // Price is too high - encourage burning by reducing fees
        if ($currentPrice > $targetPrice * 1.02) { // 2% threshold
            $actions[] = $this->adjustFees($stablecoin, 'reduce_burn_fee');
            $actions[] = $this->adjustFees($stablecoin, 'increase_mint_fee');
        }
        
        // Price is too low - encourage minting by reducing fees
        if ($currentPrice < $targetPrice * 0.98) { // 2% threshold
            $actions[] = $this->adjustFees($stablecoin, 'reduce_mint_fee');
            $actions[] = $this->adjustFees($stablecoin, 'increase_burn_fee');
        }
        
        // Large deviation - emergency measures
        if ($priceDeviation > 0.05) { // 5% threshold
            $actions[] = [
                'type' => 'emergency_intervention',
                'current_price' => $currentPrice,
                'target_price' => $targetPrice,
                'deviation' => $priceDeviation * 100,
                'action' => 'halt_operations',
            ];
            
            // Temporarily halt minting/burning
            $stablecoin->update([
                'minting_enabled' => false,
                'burning_enabled' => false,
            ]);
        }
        
        return [
            'success' => true,
            'mechanism' => 'algorithmic',
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'price_deviation' => $priceDeviation * 100,
            'actions_taken' => $actions,
        ];
    }

    /**
     * Execute hybrid stability mechanism.
     */
    private function executeHybridMechanism(Stablecoin $stablecoin): array
    {
        // Combine both collateralized and algorithmic mechanisms
        $collateralizedResult = $this->executeCollateralizedMechanism($stablecoin);
        $algorithmicResult = $this->executeAlgorithmicMechanism($stablecoin);
        
        return [
            'success' => $collateralizedResult['success'] && $algorithmicResult['success'],
            'mechanism' => 'hybrid',
            'collateralized_actions' => $collateralizedResult['actions_taken'] ?? [],
            'algorithmic_actions' => $algorithmicResult['actions_taken'] ?? [],
            'global_ratio' => $collateralizedResult['global_ratio'] ?? 0,
            'price_deviation' => $algorithmicResult['price_deviation'] ?? 0,
        ];
    }

    /**
     * Adjust collateral requirements.
     */
    private function adjustCollateralRequirements(Stablecoin $stablecoin, string $direction): array
    {
        $currentRatio = $stablecoin->collateral_ratio;
        $adjustment = 0.05; // 5% adjustment
        
        $newRatio = $direction === 'increase' 
            ? $currentRatio + $adjustment 
            : max($stablecoin->min_collateral_ratio, $currentRatio - $adjustment);
        
        // Don't adjust too frequently
        $cacheKey = "collateral_adjustment:{$stablecoin->code}";
        if (Cache::has($cacheKey)) {
            return [
                'type' => 'collateral_adjustment_skipped',
                'reason' => 'Recent adjustment already made',
            ];
        }
        
        $stablecoin->update(['collateral_ratio' => $newRatio]);
        Cache::put($cacheKey, true, 3600); // 1 hour cooldown
        
        Log::info('Collateral ratio adjusted', [
            'stablecoin_code' => $stablecoin->code,
            'old_ratio' => $currentRatio,
            'new_ratio' => $newRatio,
            'direction' => $direction,
        ]);
        
        return [
            'type' => 'collateral_adjustment',
            'direction' => $direction,
            'old_ratio' => $currentRatio,
            'new_ratio' => $newRatio,
        ];
    }

    /**
     * Adjust minting/burning fees.
     */
    private function adjustFees(Stablecoin $stablecoin, string $feeType): array
    {
        $adjustment = 0.001; // 0.1% adjustment
        $maxFee = 0.01; // 1% maximum fee
        $minFee = 0; // 0% minimum fee
        
        $currentMintFee = $stablecoin->mint_fee;
        $currentBurnFee = $stablecoin->burn_fee;
        
        $updates = [];
        
        switch ($feeType) {
            case 'reduce_mint_fee':
                $newMintFee = max($minFee, $currentMintFee - $adjustment);
                $updates['mint_fee'] = $newMintFee;
                break;
                
            case 'increase_mint_fee':
                $newMintFee = min($maxFee, $currentMintFee + $adjustment);
                $updates['mint_fee'] = $newMintFee;
                break;
                
            case 'reduce_burn_fee':
                $newBurnFee = max($minFee, $currentBurnFee - $adjustment);
                $updates['burn_fee'] = $newBurnFee;
                break;
                
            case 'increase_burn_fee':
                $newBurnFee = min($maxFee, $currentBurnFee + $adjustment);
                $updates['burn_fee'] = $newBurnFee;
                break;
        }
        
        // Don't adjust fees too frequently
        $cacheKey = "fee_adjustment:{$stablecoin->code}:{$feeType}";
        if (Cache::has($cacheKey)) {
            return [
                'type' => 'fee_adjustment_skipped',
                'fee_type' => $feeType,
                'reason' => 'Recent adjustment already made',
            ];
        }
        
        $stablecoin->update($updates);
        Cache::put($cacheKey, true, 1800); // 30 minute cooldown
        
        Log::info('Fee adjusted', [
            'stablecoin_code' => $stablecoin->code,
            'fee_type' => $feeType,
            'old_mint_fee' => $currentMintFee,
            'old_burn_fee' => $currentBurnFee,
            'updates' => $updates,
        ]);
        
        return [
            'type' => 'fee_adjustment',
            'fee_type' => $feeType,
            'updates' => $updates,
        ];
    }

    /**
     * Get current market price for stablecoin.
     */
    private function getCurrentPrice(Stablecoin $stablecoin): float
    {
        // In a real implementation, this would fetch from price oracles
        // For now, simulate price with small random variations around target
        $targetPrice = $stablecoin->target_price;
        $variance = 0.02; // 2% variance
        $randomFactor = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variance;
        
        return $targetPrice * (1 + $randomFactor);
    }

    /**
     * Generate recommendations for at-risk positions.
     */
    private function generateRiskRecommendations(Collection $atRiskPositions): array
    {
        $recommendations = [];
        
        foreach ($atRiskPositions as $position) {
            $positionRecommendations = $this->collateralService->getPositionRecommendations($position);
            
            if (!empty($positionRecommendations)) {
                $recommendations[] = [
                    'position_uuid' => $position->uuid,
                    'account_uuid' => $position->account_uuid,
                    'current_ratio' => $position->collateral_ratio,
                    'recommendations' => $positionRecommendations,
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Check system health and trigger emergency measures if needed.
     */
    public function checkSystemHealth(): array
    {
        $systemHealth = [
            'overall_status' => 'healthy',
            'stablecoin_status' => [],
            'emergency_actions' => [],
        ];
        
        $stablecoins = Stablecoin::active()->get();
        $unhealthyStablecoins = 0;
        
        foreach ($stablecoins as $stablecoin) {
            $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$stablecoin->code] ?? null;
            
            if (!$metrics) {
                continue;
            }
            
            $isHealthy = $metrics['is_healthy'];
            $globalRatio = $metrics['global_ratio'];
            $atRiskCount = $metrics['at_risk_positions'];
            
            $stablecoinStatus = [
                'code' => $stablecoin->code,
                'is_healthy' => $isHealthy,
                'global_ratio' => $globalRatio,
                'at_risk_positions' => $atRiskCount,
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
            ];
            
            // Determine if emergency action is needed
            if (!$isHealthy || $globalRatio < $stablecoin->min_collateral_ratio * 0.9) {
                $stablecoinStatus['status'] = 'critical';
                $unhealthyStablecoins++;
                
                // Trigger emergency liquidation
                if ($this->liquidationService) {
                    $emergencyResult = $this->liquidationService->emergencyLiquidation($stablecoin->code);
                    $systemHealth['emergency_actions'][] = [
                        'stablecoin_code' => $stablecoin->code,
                        'action' => 'emergency_liquidation',
                        'result' => $emergencyResult,
                    ];
                } else {
                    $systemHealth['emergency_actions'][] = [
                        'stablecoin_code' => $stablecoin->code,
                        'action' => 'emergency_liquidation_needed',
                        'result' => 'Liquidation service not available',
                    ];
                }
            }
            
            $systemHealth['stablecoin_status'][] = $stablecoinStatus;
        }
        
        // Overall system status
        if ($unhealthyStablecoins > 0) {
            // If any stablecoin is unhealthy, it's critical
            $systemHealth['overall_status'] = 'critical';
        }
        
        return $systemHealth;
    }

    /**
     * Rebalance system parameters based on market conditions.
     */
    public function rebalanceSystemParameters(array $targetMetrics = []): array
    {
        $rebalanceActions = [];
        $stablecoins = Stablecoin::active()->get();
        
        foreach ($stablecoins as $stablecoin) {
            $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$stablecoin->code] ?? null;
            
            if (!$metrics) {
                continue;
            }
            
            // Analyze collateral distribution
            $distribution = $metrics['collateral_distribution'];
            $diversificationScore = $this->calculateDiversificationScore($distribution);
            
            // If too concentrated in one asset, adjust requirements
            if ($diversificationScore < 0.5) {
                $rebalanceActions[] = [
                    'stablecoin_code' => $stablecoin->code,
                    'action' => 'improve_diversification',
                    'current_score' => $diversificationScore,
                    'recommendation' => 'Encourage collateral diversification through incentives',
                ];
            }
            
            // Adjust parameters based on utilization
            $utilizationRate = $stablecoin->total_supply / ($stablecoin->max_supply ?: PHP_INT_MAX);
            
            if ($utilizationRate > 0.8) {
                $rebalanceActions[] = [
                    'stablecoin_code' => $stablecoin->code,
                    'action' => 'increase_supply_capacity',
                    'current_utilization' => $utilizationRate,
                    'recommendation' => 'Consider increasing max supply or reducing mint requirements',
                ];
            }
        }
        
        return [
            'rebalance_timestamp' => now(),
            'actions_taken' => $rebalanceActions,
        ];
    }

    /**
     * Calculate collateral diversification score (0-1, higher is better).
     */
    private function calculateDiversificationScore(array $distribution): float
    {
        if (empty($distribution)) {
            return 0;
        }
        
        $percentages = collect($distribution)->pluck('percentage');
        $maxPercentage = $percentages->max();
        
        // Simple diversification score: 1 - max_concentration
        return 1 - ($maxPercentage / 100);
    }
    
    /**
     * Check the peg deviation for a stablecoin.
     */
    public function checkPegDeviation(Stablecoin $stablecoin): array
    {
        $rateObject = $this->exchangeRateService->getRate($stablecoin->code, $stablecoin->peg_asset_code);
        
        if (!$rateObject) {
            throw new \RuntimeException("Exchange rate not found for {$stablecoin->code} to {$stablecoin->peg_asset_code}");
        }
        
        $currentPrice = $rateObject->rate;
        $targetPrice = $stablecoin->target_price;
        
        $deviation = $currentPrice - $targetPrice;
        $percentage = ($deviation / $targetPrice) * 100;
        
        return [
            'deviation' => $deviation,
            'percentage' => $percentage,
            'direction' => $deviation > 0 ? 'above' : ($deviation < 0 ? 'below' : 'at'),
            'within_threshold' => abs($percentage) <= 1.0, // 1% threshold
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
        ];
    }
    
    /**
     * Apply stability mechanism based on the stablecoin type.
     */
    public function applyStabilityMechanism(Stablecoin $stablecoin, array $mechanism, bool $dryRun = false): array
    {
        $deviation = $this->checkPegDeviation($stablecoin);
        $actions = [];
        
        if (abs($deviation['percentage']) <= 1.0) {
            return []; // Within acceptable range
        }
        
        switch ($stablecoin->stability_mechanism) {
            case 'collateralized':
                $actions = $this->applyCollateralizedMechanism($stablecoin, $deviation);
                break;
            case 'algorithmic':
                $actions = $this->applyAlgorithmicMechanism($stablecoin, $deviation);
                break;
            case 'hybrid':
                $actions = array_merge(
                    $this->applyCollateralizedMechanism($stablecoin, $deviation),
                    $this->applyAlgorithmicMechanism($stablecoin, $deviation)
                );
                break;
        }
        
        // Fire event if not dry run
        if (!$dryRun) {
            event('stability.mechanism.applied', [
                'stablecoin_code' => $stablecoin->code,
                'deviation' => $deviation,
                'actions' => $actions,
            ]);
        }
        
        return $actions;
    }
    
    /**
     * Apply collateralized stability mechanism.
     */
    private function applyCollateralizedMechanism(Stablecoin $stablecoin, array $deviation): array
    {
        $actions = [];
        
        // Adjust fees based on deviation
        $feeAdjustment = $this->calculateFeeAdjustment($stablecoin->code);
        
        if ($feeAdjustment['new_mint_fee'] !== $stablecoin->mint_fee || 
            $feeAdjustment['new_burn_fee'] !== $stablecoin->burn_fee) {
            
            $stablecoin->mint_fee = $feeAdjustment['new_mint_fee'];
            $stablecoin->burn_fee = $feeAdjustment['new_burn_fee'];
            $stablecoin->save();
            
            $actions[] = [
                'action' => 'adjust_fees',
                'timestamp' => now(),
                'reason' => "Price deviation of {$deviation['percentage']}%",
                'new_mint_fee' => $feeAdjustment['new_mint_fee'],
                'new_burn_fee' => $feeAdjustment['new_burn_fee'],
            ];
        }
        
        return $actions;
    }
    
    /**
     * Apply algorithmic stability mechanism.
     */
    private function applyAlgorithmicMechanism(Stablecoin $stablecoin, array $deviation): array
    {
        $actions = [];
        $incentives = $this->calculateSupplyIncentives($stablecoin->code);
        
        // Update algorithmic rewards/penalties
        if ($incentives['recommended_action'] === 'burn') {
            $stablecoin->algo_burn_penalty = $incentives['burn_reward'];
            $stablecoin->algo_mint_reward = 0;
        } else {
            $stablecoin->algo_mint_reward = $incentives['mint_reward'];
            $stablecoin->algo_burn_penalty = 0;
        }
        
        $stablecoin->save();
        
        $actions[] = [
            'action' => 'adjust_supply',
            'timestamp' => now(),
            'direction' => $deviation['direction'] === 'above' ? 'expand' : 'contract',
            'burn_incentive' => $incentives['burn_reward'],
            'mint_incentive' => $incentives['mint_reward'],
        ];
        
        // Also adjust algorithmic incentives
        $actions[] = [
            'action' => 'adjust_incentives',
            'timestamp' => now(),
            'reason' => "Algorithmic adjustment for {$deviation['percentage']}% deviation",
        ];
        
        return $actions;
    }
    
    /**
     * Calculate fee adjustments based on price deviation.
     */
    public function calculateFeeAdjustment(float $deviation, array $currentFees): array
    {
        $baseMintFee = $currentFees['mint_fee'] ?? 0.01;
        $baseBurnFee = $currentFees['burn_fee'] ?? 0.01;
        
        // If price is above peg, increase mint fees and decrease burn fees
        // If price is below peg, decrease mint fees and increase burn fees
        $adjustmentFactor = min(abs($deviation) / 10, 1.0); // Cap at 100% adjustment
        
        if ($deviation > 0) {
            $newMintFee = min(0.1, $baseMintFee * (1 + $adjustmentFactor));
            $newBurnFee = max(0, $baseBurnFee * (1 - $adjustmentFactor));
        } elseif ($deviation < 0) {
            $newMintFee = max(0, $baseMintFee * (1 - $adjustmentFactor));
            $newBurnFee = min(0.1, $baseBurnFee * (1 + $adjustmentFactor));
        } else {
            $newMintFee = $baseMintFee;
            $newBurnFee = $baseBurnFee;
        }
        
        return [
            'new_mint_fee' => round($newMintFee, 6),
            'new_burn_fee' => round($newBurnFee, 6),
            'adjustment_reason' => sprintf("Price %s peg by %.2f%%", $deviation > 0 ? 'above' : 'below', abs($deviation)),
        ];
    }
    
    /**
     * Calculate supply incentives for algorithmic stablecoins.
     */
    public function calculateSupplyIncentives(float $deviation, float $currentSupply, float $targetSupply): array
    {
        if ($deviation < 0) {
            // Need to reduce supply - incentivize burning
            return [
                'recommended_action' => 'burn',
                'burn_reward' => min(0.1, abs($deviation) * 0.01),
                'mint_penalty' => 0,
            ];
        } elseif ($deviation > 0) {
            // Need to increase supply - incentivize minting
            return [
                'recommended_action' => 'mint',
                'mint_reward' => min(0.1, abs($deviation) * 0.01),
                'burn_penalty' => 0,
            ];
        }
        
        return [
            'recommended_action' => 'none',
            'mint_reward' => 0,
            'burn_reward' => 0,
            'mint_penalty' => 0,
            'burn_penalty' => 0,
        ];
    }
    
    /**
     * Monitor all stablecoin pegs.
     */
    public function monitorAllPegs(): array
    {
        $monitoring = [];
        $stablecoins = Stablecoin::active()->get();
        
        foreach ($stablecoins as $stablecoin) {
            try {
                $deviation = $this->checkPegDeviation($stablecoin);
                
                $monitoring[] = [
                    'stablecoin_code' => $stablecoin->code,
                    'deviation' => $deviation,
                    'status' => abs($deviation['percentage']) <= 1.0 ? 'healthy' : 
                               (abs($deviation['percentage']) <= 5.0 ? 'warning' : 'critical'),
                    'last_checked' => now(),
                ];
            } catch (\Exception $e) {
                $monitoring[] = [
                    'stablecoin_code' => $stablecoin->code,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'last_checked' => now(),
                ];
            }
        }
        
        return $monitoring;
    }
    
    /**
     * Execute emergency actions for extreme deviations.
     */
    public function executeEmergencyActions(string $action, array $params = []): array
    {
        $stablecoinCode = $params['stablecoin_code'] ?? null;
        if (!$stablecoinCode) {
            throw new \InvalidArgumentException('stablecoin_code is required in params');
        }
        
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);
        $deviation = $this->checkPegDeviation($stablecoin);
        $actions = [];
        
        if (abs($deviation['percentage']) > 10.0) {
            // Pause minting if price is too high
            if ($deviation['direction'] === 'above' && $stablecoin->minting_enabled) {
                $stablecoin->minting_enabled = false;
                $stablecoin->save();
                
                $actions[] = [
                    'action' => 'pause_minting',
                    'timestamp' => now(),
                    'reason' => "Extreme price deviation: {$deviation['percentage']}% above peg",
                ];
            }
            
            // Max out fees
            $stablecoin->mint_fee = 0.1;
            $stablecoin->save();
            
            $actions[] = [
                'action' => 'max_fee_adjustment',
                'timestamp' => now(),
                'reason' => "Emergency fee adjustment due to {$deviation['percentage']}% deviation",
            ];
        }
        
        return $actions;
    }
    
    /**
     * Get stability recommendations for a stablecoin.
     */
    public function getStabilityRecommendations(string $stablecoinCode): array
    {
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);
        $recommendations = [];
        
        // Check collateralization
        if ($stablecoin->stability_mechanism === 'collateralized' || 
            $stablecoin->stability_mechanism === 'hybrid') {
            
            $globalRatio = $stablecoin->total_collateral_value / max(1, $stablecoin->total_supply);
            
            if ($globalRatio < $stablecoin->collateral_ratio) {
                $recommendations[] = [
                    'action' => 'increase_collateral_requirements',
                    'reason' => 'Global collateralization below target',
                    'current_ratio' => $globalRatio,
                    'target_ratio' => $stablecoin->collateral_ratio,
                ];
                
                $recommendations[] = [
                    'action' => 'incentivize_collateral_deposits',
                    'reason' => 'Encourage users to add more collateral',
                ];
            }
        }
        
        // Check supply utilization
        if ($stablecoin->max_supply > 0) {
            $utilization = $stablecoin->total_supply / $stablecoin->max_supply;
            
            if ($utilization > 0.8) {
                $recommendations[] = [
                    'action' => 'reduce_max_supply',
                    'reason' => 'High supply utilization may limit growth',
                    'current_utilization' => $utilization,
                ];
                
                if ($stablecoin->stability_mechanism === 'algorithmic' || 
                    $stablecoin->stability_mechanism === 'hybrid') {
                    $recommendations[] = [
                        'action' => 'increase_burn_incentives',
                        'reason' => 'Reduce supply through algorithmic incentives',
                    ];
                }
            }
        }
        
        return $recommendations;
    }
}