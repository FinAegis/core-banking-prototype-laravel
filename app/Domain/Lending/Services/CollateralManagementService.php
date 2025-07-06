<?php

namespace App\Domain\Lending\Services;

use App\Domain\Lending\DataObjects\Collateral;
use App\Domain\Lending\Enums\CollateralStatus;
use App\Domain\Lending\Enums\CollateralType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

interface CollateralManagementService
{
    /**
     * Register collateral for a loan
     */
    public function registerCollateral(array $collateralData): Collateral;

    /**
     * Verify collateral documentation and value
     */
    public function verifyCollateral(string $collateralId, string $verifiedBy): bool;

    /**
     * Update collateral valuation
     */
    public function updateValuation(string $collateralId, string $newValue): Collateral;

    /**
     * Release collateral after loan completion
     */
    public function releaseCollateral(string $collateralId): bool;

    /**
     * Initiate collateral liquidation for defaulted loan
     */
    public function liquidateCollateral(string $collateralId): array;

    /**
     * Get collateral details
     */
    public function getCollateral(string $collateralId): ?Collateral;

    /**
     * Get all collateral for a loan
     */
    public function getLoanCollateral(string $loanId): Collection;

    /**
     * Calculate total collateral value for a loan
     */
    public function calculateTotalValue(string $loanId): string;

    /**
     * Check if collateral needs revaluation
     */
    public function needsRevaluation(string $collateralId): bool;
}