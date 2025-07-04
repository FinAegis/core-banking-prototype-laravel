<?php

namespace App\Domain\Lending\Services;

use App\Domain\Lending\ValueObjects\CreditScore;

interface CreditScoringService
{
    /**
     * Get credit score for a borrower
     * 
     * @param string $borrowerId
     * @return array{score: int, bureau: string, report: array}
     */
    public function getScore(string $borrowerId): array;
    
    /**
     * Check if borrower meets minimum credit requirements
     * 
     * @param string $borrowerId
     * @param int $minimumScore
     * @return bool
     */
    public function meetsMinimumRequirements(string $borrowerId, int $minimumScore = 600): bool;
    
    /**
     * Get credit history
     * 
     * @param string $borrowerId
     * @return array
     */
    public function getCreditHistory(string $borrowerId): array;
}