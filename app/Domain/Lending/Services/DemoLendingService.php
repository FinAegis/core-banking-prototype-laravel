<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Services\TransactionService;
use App\Domain\Lending\Events\LoanApplicationApproved;
use App\Domain\Lending\Events\LoanApplicationRejected;
use App\Domain\Lending\Events\LoanApplicationSubmitted;
use App\Domain\Lending\Events\LoanDisbursed;
use App\Domain\Lending\Events\LoanPaymentReceived;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanApplication;
use App\Domain\Lending\Models\LoanPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoLendingService
{
    private TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Submit a loan application with auto-approval for demo.
     */
    public function applyForLoan(array $data): LoanApplication
    {
        $applicationId = 'demo_app_' . Str::random(16);

        return DB::transaction(function () use ($data, $applicationId) {
            // Create loan application
            $application = LoanApplication::create([
                'id'               => $applicationId,
                'borrower_id'      => $data['borrower_id'],
                'requested_amount' => $data['requested_amount'],
                'currency'         => $data['currency'] ?? 'USD',
                'term_months'      => $data['term_months'],
                'purpose'          => $data['purpose'],
                'status'           => 'pending',
                'metadata'         => array_merge($data['metadata'] ?? [], [
                    'demo_mode'     => true,
                    'borrower_info' => $data['borrower_info'] ?? [],
                ]),
            ]);

            event(new LoanApplicationSubmitted($application));

            // Auto-process application in demo mode
            if (config('demo.features.auto_approve_loans', true)) {
                $this->processApplication($application);
            }

            return $application;
        });
    }

    /**
     * Process loan application with demo logic.
     */
    public function processApplication(LoanApplication $application): void
    {
        // Simulate credit check
        $creditScore = $this->simulateCreditScore($application->borrower_id);
        $riskAssessment = $this->assessRisk($application, $creditScore);

        // Store assessment data
        $application->update([
            'credit_score' => $creditScore,
            'risk_rating'  => $riskAssessment['rating'],
            'risk_factors' => $riskAssessment['factors'],
        ]);

        // Auto-approval logic
        $autoApproveThreshold = config('demo.demo_data.lending.auto_approve_threshold', 10000);
        $approvalRate = config('demo.demo_data.lending.approval_rate', 80);

        $shouldApprove = $application->requested_amount <= $autoApproveThreshold
            && $creditScore >= 650
            && rand(1, 100) <= $approvalRate;

        if ($shouldApprove) {
            $this->approveLoan($application, $creditScore);
        } else {
            $this->rejectLoan($application, $this->getRejectReasons($creditScore, $riskAssessment));
        }
    }

    /**
     * Approve loan and create loan record.
     */
    private function approveLoan(LoanApplication $application, int $creditScore): void
    {
        DB::transaction(function () use ($application, $creditScore) {
            // Calculate loan terms
            $interestRate = $this->calculateInterestRate($creditScore, $application->term_months);
            $approvedAmount = min($application->requested_amount, $this->getMaxLoanAmount($creditScore));

            // Update application
            $application->update([
                'status'            => 'approved',
                'approved_amount'   => $approvedAmount,
                'interest_rate'     => $interestRate,
                'approved_at'       => now(),
                'approval_metadata' => [
                    'auto_approved' => true,
                    'credit_score'  => $creditScore,
                    'demo_mode'     => true,
                ],
            ]);

            // Create loan
            $loan = Loan::create([
                'id'                => 'demo_loan_' . Str::random(16),
                'application_id'    => $application->id,
                'borrower_id'       => $application->borrower_id,
                'principal_amount'  => $approvedAmount,
                'currency'          => $application->currency,
                'interest_rate'     => $interestRate,
                'term_months'       => $application->term_months,
                'status'            => 'active',
                'disbursed_at'      => now(),
                'next_payment_date' => Carbon::now()->addMonth(),
                'monthly_payment'   => $this->calculateMonthlyPayment($approvedAmount, $interestRate, $application->term_months),
                'remaining_balance' => $approvedAmount,
                'metadata'          => ['demo_mode' => true],
            ]);

            event(new LoanApplicationApproved($application));
            event(new LoanDisbursed($loan));

            // Simulate disbursement to borrower's account
            if (config('demo.features.instant_disbursement', true)) {
                $this->disburseLoan($loan);
            }
        });
    }

    /**
     * Reject loan application.
     */
    private function rejectLoan(LoanApplication $application, array $reasons): void
    {
        $application->update([
            'status'            => 'rejected',
            'rejection_reasons' => $reasons,
            'rejected_at'       => now(),
        ]);

        event(new LoanApplicationRejected($application));
    }

    /**
     * Make a loan payment.
     */
    public function makePayment(string $loanId, float $amount): LoanPayment
    {
        return DB::transaction(function () use ($loanId, $amount) {
            $loan = Loan::findOrFail($loanId);

            // Calculate payment allocation
            $interestPortion = round($loan->remaining_balance * ($loan->interest_rate / 100 / 12), 2);
            $principalPortion = round($amount - $interestPortion, 2);

            // Create payment record
            $payment = LoanPayment::create([
                'id'               => 'demo_pmt_' . Str::random(16),
                'loan_id'          => $loan->id,
                'amount'           => $amount,
                'principal_amount' => $principalPortion,
                'interest_amount'  => $interestPortion,
                'payment_date'     => now(),
                'status'           => 'completed',
                'metadata'         => ['demo_mode' => true, 'instant_processing' => true],
            ]);

            // Update loan balance
            $newBalance = max(0, $loan->remaining_balance - $principalPortion);
            $loan->update([
                'remaining_balance' => $newBalance,
                'last_payment_date' => now(),
                'next_payment_date' => $newBalance > 0 ? Carbon::now()->addMonth() : null,
                'status'            => $newBalance <= 0 ? 'paid_off' : 'active',
            ]);

            event(new LoanPaymentReceived($payment));

            return $payment;
        });
    }

    /**
     * Get loan details with payment schedule.
     */
    public function getLoanDetails(string $loanId): array
    {
        $loan = Loan::with(['application', 'payments'])->findOrFail($loanId);

        return [
            'loan'                => $loan,
            'payment_schedule'    => $this->generatePaymentSchedule($loan),
            'total_paid'          => $loan->payments->sum('amount'),
            'total_interest_paid' => $loan->payments->sum('interest_amount'),
            'remaining_payments'  => ceil($loan->remaining_balance / $loan->monthly_payment),
            'demo'                => true,
        ];
    }

    /**
     * Simulate credit score for demo.
     */
    private function simulateCreditScore(int $borrowerId): int
    {
        $baseScore = config('demo.demo_data.lending.default_credit_score', 750);
        $variation = rand(-100, 100);

        return max(300, min(850, $baseScore + $variation));
    }

    /**
     * Assess risk for loan application.
     */
    private function assessRisk(LoanApplication $application, int $creditScore): array
    {
        $riskFactors = [];
        $riskScore = 0;

        // Credit score risk
        if ($creditScore < 650) {
            $riskFactors[] = 'Low credit score';
            $riskScore += 30;
        } elseif ($creditScore < 700) {
            $riskFactors[] = 'Fair credit score';
            $riskScore += 15;
        }

        // Loan amount risk
        if ($application->requested_amount > 50000) {
            $riskFactors[] = 'High loan amount';
            $riskScore += 20;
        } elseif ($application->requested_amount > 25000) {
            $riskFactors[] = 'Moderate loan amount';
            $riskScore += 10;
        }

        // Term risk
        if ($application->term_months > 60) {
            $riskFactors[] = 'Long repayment term';
            $riskScore += 15;
        }

        // Determine rating
        $rating = match (true) {
            $riskScore >= 50 => 'high',
            $riskScore >= 25 => 'medium',
            default          => 'low',
        };

        return [
            'rating'              => $rating,
            'score'               => $riskScore,
            'factors'             => $riskFactors,
            'default_probability' => round($riskScore / 100, 2),
        ];
    }

    /**
     * Calculate interest rate based on credit score and term.
     */
    private function calculateInterestRate(int $creditScore, int $termMonths): float
    {
        $baseRate = config('demo.demo_data.lending.default_interest_rate', 5.5);

        // Credit score adjustment
        $creditAdjustment = match (true) {
            $creditScore >= 800 => -1.5,
            $creditScore >= 750 => -1.0,
            $creditScore >= 700 => -0.5,
            $creditScore >= 650 => 0,
            $creditScore >= 600 => 1.0,
            default             => 2.0,
        };

        // Term adjustment
        $termAdjustment = match (true) {
            $termMonths <= 12 => -0.5,
            $termMonths <= 36 => 0,
            $termMonths <= 60 => 0.5,
            default           => 1.0,
        };

        return max(2.0, min(18.0, $baseRate + $creditAdjustment + $termAdjustment));
    }

    /**
     * Get maximum loan amount based on credit score.
     */
    private function getMaxLoanAmount(int $creditScore): float
    {
        return match (true) {
            $creditScore >= 800 => 100000,
            $creditScore >= 750 => 75000,
            $creditScore >= 700 => 50000,
            $creditScore >= 650 => 25000,
            $creditScore >= 600 => 10000,
            default             => 5000,
        };
    }

    /**
     * Calculate monthly payment amount.
     */
    private function calculateMonthlyPayment(float $principal, float $annualRate, int $months): float
    {
        $monthlyRate = $annualRate / 100 / 12;

        if ($monthlyRate == 0) {
            return round($principal / $months, 2);
        }

        $payment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);

        return round($payment, 2);
    }

    /**
     * Generate payment schedule.
     */
    private function generatePaymentSchedule(Loan $loan): array
    {
        $schedule = [];
        $balance = $loan->principal_amount;
        $monthlyRate = $loan->interest_rate / 100 / 12;
        $paymentDate = Carbon::parse($loan->disbursed_at);

        for ($i = 1; $i <= $loan->term_months; $i++) {
            $paymentDate = $paymentDate->addMonth();
            $interestPayment = round($balance * $monthlyRate, 2);
            $principalPayment = round($loan->monthly_payment - $interestPayment, 2);
            $balance = max(0, $balance - $principalPayment);

            $schedule[] = [
                'payment_number' => $i,
                'payment_date'   => $paymentDate->format('Y-m-d'),
                'payment_amount' => $loan->monthly_payment,
                'principal'      => $principalPayment,
                'interest'       => $interestPayment,
                'balance'        => $balance,
                'status'         => $i <= $loan->payments()->count() ? 'paid' : 'pending',
            ];

            if ($balance <= 0) {
                break;
            }
        }

        return $schedule;
    }

    /**
     * Get rejection reasons based on assessment.
     */
    private function getRejectReasons(int $creditScore, array $riskAssessment): array
    {
        $reasons = [];

        if ($creditScore < 650) {
            $reasons[] = 'Credit score below minimum requirement';
        }

        if ($riskAssessment['rating'] === 'high') {
            $reasons[] = 'High risk profile';
            $reasons = array_merge($reasons, $riskAssessment['factors']);
        }

        if (empty($reasons)) {
            $reasons[] = 'Random selection for manual review (demo mode)';
        }

        return $reasons;
    }

    /**
     * Simulate loan disbursement.
     */
    private function disburseLoan(Loan $loan): void
    {
        // In demo mode, we just mark it as disbursed
        // In production, this would trigger actual fund transfer
        $loan->update([
            'disbursement_metadata' => [
                'demo_mode'            => true,
                'instant_disbursement' => true,
                'disbursed_at'         => now()->toIso8601String(),
            ],
        ]);
    }
}
