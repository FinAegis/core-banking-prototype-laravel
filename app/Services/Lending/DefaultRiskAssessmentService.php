<?php

namespace App\Services\Lending;

use App\Domain\Lending\Services\RiskAssessmentService;
use App\Domain\Lending\Aggregates\LoanApplication;
use App\Models\User;
use Brick\Math\BigDecimal;

class DefaultRiskAssessmentService implements RiskAssessmentService
{
    public function assessLoan(
        LoanApplication $application, 
        array $creditScore,
        array $additionalFactors = []
    ): array {
        $riskFactors = [];
        $riskScore = 0;
        
        // Credit score factor (40% weight)
        $creditScoreValue = $creditScore['score'];
        if ($creditScoreValue >= 750) {
            $riskScore += 40;
        } elseif ($creditScoreValue >= 700) {
            $riskScore += 35;
        } elseif ($creditScoreValue >= 650) {
            $riskScore += 25;
        } elseif ($creditScoreValue >= 600) {
            $riskScore += 15;
        } else {
            $riskScore += 5;
            $riskFactors[] = 'Low credit score';
        }
        
        // Debt-to-income ratio (30% weight) - mocked for now
        $monthlyIncome = $this->estimateMonthlyIncome($application->getBorrowerId());
        $requestedAmount = BigDecimal::of($additionalFactors['requestedAmount'] ?? '0');
        $termMonths = $additionalFactors['termMonths'] ?? 12;
        
        $estimatedMonthlyPayment = $requestedAmount->dividedBy($termMonths, 2, RoundingMode::UP);
        $dti = $monthlyIncome->isZero() ? 1.0 : $estimatedMonthlyPayment->dividedBy($monthlyIncome, 4)->toFloat();
        
        if ($dti <= 0.20) {
            $riskScore += 30;
        } elseif ($dti <= 0.35) {
            $riskScore += 20;
        } elseif ($dti <= 0.45) {
            $riskScore += 10;
        } else {
            $riskScore += 0;
            $riskFactors[] = 'High debt-to-income ratio';
        }
        
        // Payment history (20% weight)
        $paymentHistory = $creditScore['report']['paymentHistory'] ?? [];
        $latePayments = count(array_filter($paymentHistory, fn($p) => $p['status'] === 'late'));
        
        if ($latePayments == 0) {
            $riskScore += 20;
        } elseif ($latePayments <= 2) {
            $riskScore += 10;
        } else {
            $riskScore += 0;
            $riskFactors[] = 'Multiple late payments';
        }
        
        // Account history (10% weight)
        $user = User::find($application->getBorrowerId());
        $accountAgeMonths = $user->created_at->diffInMonths(now());
        
        if ($accountAgeMonths >= 24) {
            $riskScore += 10;
        } elseif ($accountAgeMonths >= 12) {
            $riskScore += 5;
        } else {
            $riskScore += 0;
            $riskFactors[] = 'New account';
        }
        
        // Determine rating based on risk score
        $rating = match(true) {
            $riskScore >= 80 => 'A',
            $riskScore >= 65 => 'B',
            $riskScore >= 50 => 'C',
            $riskScore >= 35 => 'D',
            $riskScore >= 20 => 'E',
            default => 'F',
        };
        
        // Calculate default probability
        $defaultProbability = match($rating) {
            'A' => 0.01,
            'B' => 0.03,
            'C' => 0.05,
            'D' => 0.10,
            'E' => 0.20,
            'F' => 0.35,
        };
        
        return [
            'rating' => $rating,
            'defaultProbability' => $defaultProbability,
            'riskFactors' => $riskFactors,
            'riskScore' => $riskScore,
            'details' => [
                'creditScore' => $creditScoreValue,
                'dti' => $dti,
                'latePayments' => $latePayments,
                'accountAgeMonths' => $accountAgeMonths,
            ],
        ];
    }
    
    public function calculateRiskAdjustedRate(string $riskRating, float $baseRate): float
    {
        $riskPremium = match($riskRating) {
            'A' => 0,
            'B' => 2,
            'C' => 4,
            'D' => 6,
            'E' => 8,
            'F' => 12,
            default => 15,
        };
        
        return $baseRate + $riskPremium;
    }
    
    public function getBorrowerRiskFactors(string $borrowerId): array
    {
        $user = User::find($borrowerId);
        $factors = [];
        
        // Account age
        $accountAgeMonths = $user->created_at->diffInMonths(now());
        if ($accountAgeMonths < 6) {
            $factors[] = 'Very new account';
        } elseif ($accountAgeMonths < 12) {
            $factors[] = 'New account';
        }
        
        // KYC status
        if ($user->kyc_status !== 'approved') {
            $factors[] = 'KYC not fully approved';
        }
        
        // Mock additional factors
        // In production, these would come from actual data
        if (rand(0, 100) > 80) {
            $factors[] = 'High existing debt';
        }
        
        if (rand(0, 100) > 90) {
            $factors[] = 'Recent credit inquiries';
        }
        
        return $factors;
    }
    
    private function estimateMonthlyIncome(string $borrowerId): BigDecimal
    {
        // In production, this would use actual income verification
        // For now, mock based on some logic
        return BigDecimal::of(rand(3000, 15000));
    }
}