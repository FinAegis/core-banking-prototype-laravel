<?php

namespace App\Services\Lending;

use App\Domain\Lending\Aggregates\LoanApplication;
use App\Domain\Lending\Aggregates\Loan;
use App\Domain\Lending\Services\CreditScoringService;
use App\Domain\Lending\Services\RiskAssessmentService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanApplicationService
{
    public function __construct(
        private CreditScoringService $creditScoring,
        private RiskAssessmentService $riskAssessment
    ) {}
    
    public function processApplication(
        string $applicationId,
        string $borrowerId,
        string $requestedAmount,
        int $termMonths,
        string $purpose,
        array $borrowerInfo
    ): array {
        try {
            DB::beginTransaction();
            
            // Step 1: Create loan application
            $application = LoanApplication::submit(
                $applicationId,
                $borrowerId,
                $requestedAmount,
                $termMonths,
                $purpose,
                $borrowerInfo
            );
            $application->persist();
            
            // Step 2: Perform KYC check
            $user = User::find($borrowerId);
            $kycStatus = $user->kyc_status ?? 'pending';
            
            if ($kycStatus !== 'approved') {
                $application->reject(['KYC not verified'], 'system');
                $application->persist();
                
                DB::commit();
                
                return [
                    'status' => 'rejected',
                    'reason' => 'KYC check failed',
                    'applicationId' => $applicationId,
                ];
            }
            
            // Step 3: Run credit check
            $creditScore = $this->creditScoring->getScore($borrowerId);
            
            $application->completeCreditCheck(
                $creditScore['score'],
                $creditScore['bureau'],
                $creditScore['report'],
                'system'
            );
            $application->persist();
            
            // Step 4: Assess risk
            $riskAssessment = $this->riskAssessment->assessLoan(
                $application,
                $creditScore,
                [
                    'requestedAmount' => $requestedAmount,
                    'termMonths' => $termMonths,
                ]
            );
            
            $application->completeRiskAssessment(
                $riskAssessment['rating'],
                $riskAssessment['defaultProbability'],
                $riskAssessment['riskFactors'],
                'system'
            );
            $application->persist();
            
            // Step 5: Make decision
            $decision = $this->makeDecision(
                $requestedAmount,
                $termMonths,
                $creditScore,
                $riskAssessment
            );
            
            if ($decision['approved']) {
                // Approve application
                $application->approve(
                    $decision['approvedAmount'],
                    $decision['interestRate'],
                    $decision['terms'],
                    'system'
                );
                $application->persist();
                
                // Create loan
                $loanId = 'loan_' . uniqid();
                $loan = Loan::createFromApplication(
                    $loanId,
                    $applicationId,
                    $borrowerId,
                    $decision['approvedAmount'],
                    $decision['interestRate'],
                    $termMonths,
                    $decision['terms']
                );
                $loan->persist();
                
                DB::commit();
                
                // Notify borrower
                $this->notifyBorrower($borrowerId, 'approved', [
                    'applicationId' => $applicationId,
                    'loanId' => $loanId,
                    'approvedAmount' => $decision['approvedAmount'],
                    'interestRate' => $decision['interestRate'],
                    'termMonths' => $termMonths,
                ]);
                
                return [
                    'status' => 'approved',
                    'applicationId' => $applicationId,
                    'loanId' => $loanId,
                    'approvedAmount' => $decision['approvedAmount'],
                    'interestRate' => $decision['interestRate'],
                ];
            } else {
                // Reject application
                $application->reject($decision['rejectionReasons'], 'system');
                $application->persist();
                
                DB::commit();
                
                // Notify borrower
                $this->notifyBorrower($borrowerId, 'rejected', [
                    'applicationId' => $applicationId,
                    'reasons' => $decision['rejectionReasons'],
                ]);
                
                return [
                    'status' => 'rejected',
                    'applicationId' => $applicationId,
                    'reasons' => $decision['rejectionReasons'],
                ];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    private function makeDecision(
        string $requestedAmount,
        int $termMonths,
        array $creditScore,
        array $riskAssessment
    ): array {
        // Decision logic based on credit score and risk
        $approved = $creditScore['score'] >= 600 && in_array($riskAssessment['rating'], ['A', 'B', 'C', 'D']);
        
        if ($approved) {
            // Calculate interest rate
            $baseRate = 5.0; // Base rate
            $riskPremium = match($riskAssessment['rating']) {
                'A' => 0,
                'B' => 2,
                'C' => 4,
                'D' => 6,
                default => 10,
            };
            
            $interestRate = $baseRate + $riskPremium;
            
            // Determine approved amount (may be less than requested for higher risk)
            $approvalRatio = match($riskAssessment['rating']) {
                'A' => 1.0,
                'B' => 1.0,
                'C' => 0.9,
                'D' => 0.8,
                default => 0.7,
            };
            
            $approvedAmount = bcmul($requestedAmount, $approvalRatio, 2);
            
            return [
                'approved' => true,
                'approvedAmount' => $approvedAmount,
                'interestRate' => $interestRate,
                'terms' => [
                    'repaymentFrequency' => 'monthly',
                    'lateFeePercentage' => 5.0,
                    'gracePeriodDays' => 5,
                ],
            ];
        } else {
            $reasons = [];
            
            if ($creditScore['score'] < 600) {
                $reasons[] = 'Credit score below minimum threshold';
            }
            
            if (!in_array($riskAssessment['rating'], ['A', 'B', 'C', 'D'])) {
                $reasons[] = 'Risk rating too high';
            }
            
            return [
                'approved' => false,
                'rejectionReasons' => $reasons,
            ];
        }
    }
    
    private function notifyBorrower(string $borrowerId, string $status, array $details): void
    {
        // In production, this would send email/SMS/push notification
        Log::info("Loan application notification", [
            'borrowerId' => $borrowerId,
            'status' => $status,
            'details' => $details,
        ]);
    }
}