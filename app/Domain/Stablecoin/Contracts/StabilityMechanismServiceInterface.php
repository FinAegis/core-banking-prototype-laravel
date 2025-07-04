<?php

namespace App\Domain\Stablecoin\Contracts;

use App\Models\Stablecoin;

interface StabilityMechanismServiceInterface
{
    /**
     * Execute all stability mechanisms
     *
     * @return array
     */
    public function executeStabilityMechanisms(): array;

    /**
     * Execute stability mechanism for specific stablecoin
     *
     * @param Stablecoin $stablecoin
     * @return array
     */
    public function executeStabilityMechanismForStablecoin(Stablecoin $stablecoin): array;

    /**
     * Check system health
     *
     * @return array
     */
    public function checkSystemHealth(): array;

    /**
     * Rebalance system parameters
     *
     * @param array $targetMetrics
     * @return array
     */
    public function rebalanceSystemParameters(array $targetMetrics = []): array;

    /**
     * Check peg deviation
     *
     * @param Stablecoin $stablecoin
     * @return array
     */
    public function checkPegDeviation(Stablecoin $stablecoin): array;

    /**
     * Apply stability mechanism
     *
     * @param Stablecoin $stablecoin
     * @param array $mechanism
     * @param bool $dryRun
     * @return array
     */
    public function applyStabilityMechanism(Stablecoin $stablecoin, array $mechanism, bool $dryRun = false): array;

    /**
     * Calculate fee adjustment
     *
     * @param float $deviation
     * @param array $currentFees
     * @return array
     */
    public function calculateFeeAdjustment(float $deviation, array $currentFees): array;

    /**
     * Calculate supply incentives
     *
     * @param float $deviation
     * @param float $currentSupply
     * @param float $targetSupply
     * @return array
     */
    public function calculateSupplyIncentives(float $deviation, float $currentSupply, float $targetSupply): array;

    /**
     * Monitor all pegs
     *
     * @return array
     */
    public function monitorAllPegs(): array;

    /**
     * Execute emergency actions
     *
     * @param string $action
     * @param array $params
     * @return array
     */
    public function executeEmergencyActions(string $action, array $params = []): array;
}