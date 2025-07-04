<?php

namespace App\Domain\Lending\Services;

use App\Domain\Lending\Aggregates\LoanApplication;
use App\Domain\Lending\ValueObjects\RiskRating;

interface RiskAssessmentService
{
    /**
     * Assess loan risk
     * 
     * @param LoanApplication $application
     * @param array $creditScore
     * @param array $additionalFactors
     * @return array{rating: string, defaultProbability: float, riskFactors: array}
     */
    public function assessLoan(
        LoanApplication $application, 
        array $creditScore,
        array $additionalFactors = []
    ): array;
    
    /**
     * Calculate risk-adjusted interest rate
     * 
     * @param string $riskRating
     * @param float $baseRate
     * @return float
     */
    public function calculateRiskAdjustedRate(string $riskRating, float $baseRate): float;
    
    /**
     * Get risk factors for a borrower
     * 
     * @param string $borrowerId
     * @return array
     */
    public function getBorrowerRiskFactors(string $borrowerId): array;
}