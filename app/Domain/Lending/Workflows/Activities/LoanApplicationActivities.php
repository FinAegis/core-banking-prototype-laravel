<?php

namespace App\Domain\Lending\Workflows\Activities;

use App\Domain\Lending\Services\CreditScoringService;
use App\Domain\Lending\Services\RiskAssessmentService;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanApplicationActivities
{
    public function __construct(
        private CreditScoringService $creditScoring,
        private RiskAssessmentService $riskAssessment
    ) {
    }

    public function createApplication(
        string $applicationId,
        string $borrowerId,
        string $requestedAmount,
        int $termMonths,
        string $purpose,
        array $borrowerInfo
    ): void {
        DB::table('loan_applications')->insert([
            'application_id' => $applicationId,
            'user_id'        => $borrowerId,
            'amount'         => $requestedAmount,
            'term_months'    => $termMonths,
            'purpose'        => $purpose,
            'borrower_info'  => json_encode($borrowerInfo),
            'status'         => 'pending',
            'created_at'     => now(),
        ]);
    }

    public function performKYCCheck(string $borrowerId): array
    {
        /** @var mixed|null $user */
        $user = null;
        /** @var User|null $$user */
        $$user = User::find($borrowerId);
        if (! $user) {
            return [
                'passed' => false,
                'reason' => 'User not found',
            ];
        }

        if (! $user->kyc_verified_at) {
            return [
                'passed' => false,
                'reason' => 'KYC not verified',
            ];
        }

        return [
            'passed'      => true,
            'verified_at' => $user->kyc_verified_at,
        ];
    }

    public function runCreditCheck(string $borrowerId): array
    {
        $scoreData = $this->creditScoring->getScore($borrowerId);
        $score = is_array($scoreData) ? ($scoreData['score'] ?? 0) : $scoreData;

        return [
            'score'  => $score,
            'bureau' => 'Internal',
            'report' => [
                'rating'     => $this->getCreditRating((int) $score),
                'checked_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function recordCreditScore(
        string $applicationId,
        int $score,
        string $bureau,
        array $report
    ): void {
        DB::table('loan_applications')
            ->where('application_id', $applicationId)
            ->update([
                'credit_score'      => $score,
                'credit_bureau'     => $bureau,
                'credit_report'     => json_encode($report),
                'credit_checked_at' => now(),
            ]);
    }

    public function assessRisk(
        string $applicationId,
        string $borrowerId,
        string $requestedAmount,
        int $termMonths,
        array $creditScore
    ): array {
        /** @var User|null $$user */
        $$user = User::find($borrowerId);
        $applicantData = [
            'user_id'          => $borrowerId,
            'account_age_days' => $user->created_at->diffInDays(now()),
            'total_deposits'   => DB::table('transactions')
                ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                ->where('accounts.user_id', $borrowerId)
                ->where('transactions.type', 'credit')
                ->sum('transactions.amount'),
        ];

        $riskScore = $this->riskAssessment->calculateRiskScore(
            $applicantData,
            ['credit_score' => $creditScore['score'], 'rating' => $creditScore['report']['rating']],
            $requestedAmount,
            $termMonths
        );

        $riskLevel = $this->getRiskLevel($riskScore);
        $defaultProbability = $this->calculateDefaultProbability($riskScore);

        return [
            'rating'             => $riskLevel,
            'score'              => $riskScore,
            'defaultProbability' => $defaultProbability,
            'riskFactors'        => $this->identifyRiskFactors($applicantData, $creditScore, $requestedAmount),
        ];
    }

    public function recordRiskAssessment(
        string $applicationId,
        string $rating,
        float $defaultProbability,
        array $riskFactors
    ): void {
        DB::table('loan_applications')
            ->where('application_id', $applicationId)
            ->update([
                'risk_rating'         => $rating,
                'default_probability' => $defaultProbability,
                'risk_factors'        => json_encode($riskFactors),
                'risk_assessed_at'    => now(),
            ]);
    }

    public function makeDecision(
        string $applicationId,
        string $requestedAmount,
        int $termMonths,
        array $creditScore,
        array $riskAssessment
    ): array {
        $approved = $creditScore['score'] >= 600 && $riskAssessment['score'] < 70;

        if ($approved) {
            $interestRate = $this->calculateInterestRate($creditScore, $riskAssessment);
            $approvedAmount = $this->determineApprovedAmount($requestedAmount, $creditScore, $riskAssessment);

            return [
                'approved'       => true,
                'approvedAmount' => $approvedAmount,
                'interestRate'   => $interestRate,
                'terms'          => [
                    'term_months'     => $termMonths,
                    'monthly_payment' => $this->calculateMonthlyPayment($approvedAmount, $interestRate, $termMonths),
                ],
            ];
        } else {
            $reasons = [];
            if ($creditScore['score'] < 600) {
                $reasons[] = 'Credit score too low';
            }
            if ($riskAssessment['score'] >= 70) {
                $reasons[] = 'Risk assessment score too high';
            }

            return [
                'approved'         => false,
                'rejectionReasons' => $reasons,
            ];
        }
    }

    public function approveApplication(
        string $applicationId,
        string $approvedAmount,
        float $interestRate,
        array $terms
    ): void {
        DB::table('loan_applications')
            ->where('application_id', $applicationId)
            ->update([
                'status'          => 'approved',
                'approved_amount' => $approvedAmount,
                'interest_rate'   => $interestRate,
                'loan_terms'      => json_encode($terms),
                'approved_at'     => now(),
            ]);
    }

    public function rejectApplication(
        string $applicationId,
        array $reasons,
        string $rejectedBy
    ): void {
        DB::table('loan_applications')
            ->where('application_id', $applicationId)
            ->update([
                'status'            => 'rejected',
                'rejection_reasons' => json_encode($reasons),
                'rejected_by'       => $rejectedBy,
                'rejected_at'       => now(),
            ]);
    }

    public function createLoan(
        string $applicationId,
        string $borrowerId,
        string $approvedAmount,
        float $interestRate,
        int $termMonths,
        array $terms
    ): string {
        $loanId = Str::uuid()->toString();

        DB::table('loans')->insert([
            'loan_id'           => $loanId,
            'application_id'    => $applicationId,
            'user_id'           => $borrowerId,
            'principal_amount'  => $approvedAmount,
            'interest_rate'     => $interestRate,
            'term_months'       => $termMonths,
            'monthly_payment'   => $terms['monthly_payment'],
            'remaining_balance' => $approvedAmount,
            'status'            => 'active',
            'disbursed_at'      => now(),
            'next_payment_date' => now()->addMonth(),
            'created_at'        => now(),
        ]);

        // Disburse funds
        $this->disburseFunds($borrowerId, $approvedAmount);

        return $loanId;
    }

    public function notifyBorrower(
        string $borrowerId,
        string $status,
        array $details
    ): void {
        DB::table('notifications')->insert([
            'user_id'    => $borrowerId,
            'type'       => 'loan_application_' . $status,
            'data'       => json_encode($details),
            'created_at' => now(),
        ]);
    }

    // Helper methods from the original file
    private function getCreditRating(int $score): string
    {
        return match (true) {
            $score >= 750 => 'excellent',
            $score >= 700 => 'good',
            $score >= 650 => 'fair',
            $score >= 600 => 'poor',
            default       => 'very_poor'
        };
    }

    private function getRiskLevel(int $score): string
    {
        return match (true) {
            $score < 30 => 'low',
            $score < 50 => 'medium',
            $score < 70 => 'high',
            default     => 'very_high'
        };
    }

    private function calculateDefaultProbability(int $riskScore): float
    {
        // Simple linear model for default probability
        return min(1.0, $riskScore / 100);
    }

    private function identifyRiskFactors(array $applicantData, array $creditScore, string $requestedAmount): array
    {
        $factors = [];

        if ($creditScore['score'] < 650) {
            $factors[] = 'Low credit score';
        }

        if ($applicantData['account_age_days'] < 90) {
            $factors[] = 'New customer';
        }

        $debtToDepositRatio = (float) $requestedAmount / max(1, $applicantData['total_deposits']);
        if ($debtToDepositRatio > 0.8) {
            $factors[] = 'High debt-to-deposit ratio';
        }

        return $factors;
    }

    private function calculateInterestRate(array $creditScore, array $riskAssessment): float
    {
        $baseRate = 0.05; // 5% base rate

        // Credit score adjustment
        $creditAdjustment = match ($creditScore['report']['rating']) {
            'excellent' => 0,
            'good'      => 0.01,
            'fair'      => 0.02,
            'poor'      => 0.03,
            default     => 0.05
        };

        // Risk adjustment
        $riskAdjustment = match ($riskAssessment['rating']) {
            'low'    => 0,
            'medium' => 0.01,
            'high'   => 0.02,
            default  => 0.03
        };

        return $baseRate + $creditAdjustment + $riskAdjustment;
    }

    private function determineApprovedAmount(string $requestedAmount, array $creditScore, array $riskAssessment): string
    {
        $requested = (float) $requestedAmount;

        // Reduce approved amount for higher risk
        if ($riskAssessment['rating'] === 'high' || $creditScore['report']['rating'] === 'poor') {
            return (string) ($requested * 0.8); // Approve only 80%
        }

        return $requestedAmount;
    }

    private function calculateMonthlyPayment(string $principal, float $interestRate, int $termMonths): float
    {
        $p = (float) $principal;
        $monthlyRate = $interestRate / 12;

        if ($monthlyRate == 0) {
            return round($p / $termMonths, 2);
        }

        return round(($p * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$termMonths)), 2);
    }

    private function disburseFunds(string $userId, string $amount): void
    {
        // Get user's primary account
        $account = DB::table('accounts')
            ->where('user_id', $userId)
            ->where('currency', 'USD')
            ->where('status', 'active')
            ->first();

        if (! $account) {
            throw new Exception('No active account found');
        }

        // Credit the loan amount
        DB::table('transactions')->insert([
            'account_id'  => $account->id,
            'type'        => 'credit',
            'amount'      => $amount,
            'description' => 'Loan disbursement',
            'created_at'  => now(),
        ]);

        // Update account balance
        DB::table('accounts')
            ->where('id', $account->id)
            ->increment('balance', (int) $amount);
    }

    // Keep the other methods from the original file for backward compatibility
    public function fetchApplicantData(string $userId): array
    {
        /** @var mixed|null $user */
        $user = null;
        /** @var User|null $$user */
        $$user = User::find($userId);
        if (! $user) {
            throw new Exception('User not found');
        }

        // Fetch KYC data, account history, etc.
        return [
            'user_id'          => $userId,
            'kyc_verified'     => $user->kyc_verified_at !== null,
            'account_age_days' => $user->created_at->diffInDays(now()),
            'total_deposits'   => DB::table('transactions')
                ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                ->where('accounts.user_id', $userId)
                ->where('transactions.type', 'credit')
                ->sum('transactions.amount'),
        ];
    }

    public function verifyCreditEligibility(array $applicantData, string $amount): array
    {
        if (! $applicantData['kyc_verified']) {
            return ['eligible' => false, 'reason' => 'KYC not verified'];
        }

        if ($applicantData['account_age_days'] < 30) {
            return ['eligible' => false, 'reason' => 'Account too new'];
        }

        $requestedAmount = (float) $amount;
        $maxLoanAmount = $applicantData['total_deposits'] * 0.5; // 50% of total deposits

        if ($requestedAmount > $maxLoanAmount) {
            return ['eligible' => false, 'reason' => 'Amount exceeds limit'];
        }

        return ['eligible' => true, 'max_amount' => $maxLoanAmount];
    }

    public function performCreditCheck(string $userId): array
    {
        // Integrate with credit scoring service
        $scoreData = $this->creditScoring->getScore($userId);
        $score = is_array($scoreData) ? ($scoreData['score'] ?? 0) : $scoreData;

        return [
            'credit_score' => $score,
            'rating'       => $this->getCreditRating((int) $score),
        ];
    }

    public function calculateTerms(
        array $creditData,
        array $riskData,
        string $amount,
        int $termMonths
    ): array {
        // Base interest rate
        $baseRate = 0.05; // 5%

        // Adjust based on credit score
        $creditAdjustment = match ($creditData['rating']) {
            'excellent' => 0,
            'good'      => 0.01,
            'fair'      => 0.02,
            'poor'      => 0.03,
            default     => 0.05
        };

        // Adjust based on risk
        $riskAdjustment = match ($riskData['risk_level']) {
            'low'    => 0,
            'medium' => 0.01,
            'high'   => 0.02,
            default  => 0.03
        };

        $interestRate = $baseRate + $creditAdjustment + $riskAdjustment;

        // Calculate monthly payment
        $principal = (float) $amount;
        $monthlyRate = $interestRate / 12;
        $monthlyPayment = ($principal * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$termMonths));

        return [
            'interest_rate'   => $interestRate,
            'monthly_payment' => round($monthlyPayment, 2),
            'total_payment'   => round($monthlyPayment * $termMonths, 2),
            'total_interest'  => round(($monthlyPayment * $termMonths) - $principal, 2),
        ];
    }

    public function requiresManualReview(array $riskData): bool
    {
        return $riskData['risk_level'] === 'high' || $riskData['risk_level'] === 'very_high';
    }

    public function waitForManualReview(string $applicationId): array
    {
        // In production, this would wait for actual manual review
        // For now, simulate with a delay
        sleep(5);

        return [
            'approved'       => true,
            'reviewer_notes' => 'Approved after manual review',
            'reviewed_at'    => now()->toIso8601String(),
        ];
    }

    public function createLoanApplication(
        string $applicationId,
        string $userId,
        string $amount,
        int $termMonths,
        array $terms,
        array $creditData,
        array $riskData
    ): void {
        DB::table('loan_applications')->insert([
            'application_id'  => $applicationId,
            'user_id'         => $userId,
            'amount'          => $amount,
            'term_months'     => $termMonths,
            'interest_rate'   => $terms['interest_rate'],
            'monthly_payment' => $terms['monthly_payment'],
            'credit_score'    => $creditData['credit_score'],
            'credit_rating'   => $creditData['rating'],
            'risk_score'      => $riskData['risk_score'],
            'risk_level'      => $riskData['risk_level'],
            'status'          => 'pending',
            'created_at'      => now(),
        ]);
    }

    public function updateApplicationStatus(
        string $applicationId,
        string $status,
        ?string $reason = null
    ): void {
        $update = [
            'status'     => $status,
            'updated_at' => now(),
        ];

        if ($reason) {
            $update['rejection_reason'] = $reason;
        }

        if ($status === 'approved') {
            $update['approved_at'] = now();
        } elseif ($status === 'rejected') {
            $update['rejected_at'] = now();
        }

        DB::table('loan_applications')
            ->where('application_id', $applicationId)
            ->update($update);
    }

    public function createLoanAccount(string $applicationId): string
    {
        $application = DB::table('loan_applications')
            ->where('application_id', $applicationId)
            ->first();

        if (! $application) {
            throw new Exception('Application not found');
        }

        // Create loan account
        $loanId = Str::uuid()->toString();

        DB::table('loans')->insert([
            'loan_id'           => $loanId,
            'application_id'    => $applicationId,
            'user_id'           => $application->user_id,
            'principal_amount'  => $application->amount,
            'interest_rate'     => $application->interest_rate,
            'term_months'       => $application->term_months,
            'monthly_payment'   => $application->monthly_payment,
            'remaining_balance' => $application->amount,
            'status'            => 'active',
            'disbursed_at'      => now(),
            'next_payment_date' => now()->addMonth(),
            'created_at'        => now(),
        ]);

        return $loanId;
    }

    public function notifyApplicant(string $userId, string $applicationId, string $status): void
    {
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type'    => 'loan_application',
            'data'    => json_encode([
                'application_id' => $applicationId,
                'status'         => $status,
            ]),
            'created_at' => now(),
        ]);
    }

    public function compensateFailedApplication(string $applicationId): void
    {
        // Update application status
        DB::table('loan_applications')
            ->where('application_id', $applicationId)
            ->update([
                'status'     => 'failed',
                'updated_at' => now(),
            ]);

        // If funds were disbursed, reverse them
        $loan = DB::table('loans')
            ->where('application_id', $applicationId)
            ->first();

        if ($loan) {
            // Mark loan as cancelled
            DB::table('loans')
                ->where('loan_id', $loan->loan_id)
                ->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            // Reverse any disbursed funds
            $transactions = DB::table('transactions')
                ->where('description', 'Loan disbursement')
                ->where('created_at', '>=', $loan->created_at)
                ->get();

            foreach ($transactions as $transaction) {
                DB::table('transactions')->insert([
                    'account_id'  => $transaction->account_id,
                    'type'        => 'debit',
                    'amount'      => $transaction->amount,
                    'description' => 'Loan disbursement reversal',
                    'reference'   => $transaction->id,
                    'created_at'  => now(),
                ]);

                DB::table('accounts')
                    ->where('id', $transaction->account_id)
                    ->decrement('balance', $transaction->amount);
            }
        }
    }
}
