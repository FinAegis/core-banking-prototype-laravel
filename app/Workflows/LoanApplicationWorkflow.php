<?php

namespace App\Workflows;

use App\Domain\Lending\Aggregates\LoanApplication;
use App\Domain\Lending\Services\CreditScoringService;
use App\Domain\Lending\Services\RiskAssessmentService;
use App\Domain\Account\Aggregates\Account;
use App\Models\User;
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class LoanApplicationWorkflow extends Workflow
{
    private ActivityStub $activities;
    
    public function __construct()
    {
        $this->activities = WorkflowStub::newActivityStub(
            LoanApplicationActivities::class,
            [
                'startToCloseTimeout' => 300, // 5 minutes
                'retryAttempts' => 3,
            ]
        );
    }
    
    public function execute(
        string $applicationId,
        string $borrowerId,
        string $requestedAmount,
        int $termMonths,
        string $purpose,
        array $borrowerInfo
    ) {
        // Step 1: Create loan application
        yield $this->activities->createApplication(
            $applicationId,
            $borrowerId,
            $requestedAmount,
            $termMonths,
            $purpose,
            $borrowerInfo
        );
        
        // Step 2: Perform KYC check
        $kycResult = yield $this->activities->performKYCCheck($borrowerId);
        
        if (!$kycResult['passed']) {
            yield $this->activities->rejectApplication(
                $applicationId,
                ['KYC check failed: ' . $kycResult['reason']],
                'system'
            );
            return [
                'status' => 'rejected',
                'reason' => 'KYC check failed',
                'applicationId' => $applicationId,
            ];
        }
        
        // Step 3: Run credit check
        $creditScore = yield $this->activities->runCreditCheck($borrowerId);
        
        yield $this->activities->recordCreditScore(
            $applicationId,
            $creditScore['score'],
            $creditScore['bureau'],
            $creditScore['report']
        );
        
        // Step 4: Assess risk
        $riskAssessment = yield $this->activities->assessRisk(
            $applicationId,
            $borrowerId,
            $requestedAmount,
            $termMonths,
            $creditScore
        );
        
        yield $this->activities->recordRiskAssessment(
            $applicationId,
            $riskAssessment['rating'],
            $riskAssessment['defaultProbability'],
            $riskAssessment['riskFactors']
        );
        
        // Step 5: Determine interest rate and approval
        $decision = yield $this->activities->makeDecision(
            $applicationId,
            $requestedAmount,
            $termMonths,
            $creditScore,
            $riskAssessment
        );
        
        if ($decision['approved']) {
            // Step 6: Approve application
            yield $this->activities->approveApplication(
                $applicationId,
                $decision['approvedAmount'],
                $decision['interestRate'],
                $decision['terms']
            );
            
            // Step 7: Create loan
            $loanId = yield $this->activities->createLoan(
                $applicationId,
                $borrowerId,
                $decision['approvedAmount'],
                $decision['interestRate'],
                $termMonths,
                $decision['terms']
            );
            
            // Step 8: Notify borrower
            yield $this->activities->notifyBorrower(
                $borrowerId,
                'approved',
                [
                    'applicationId' => $applicationId,
                    'loanId' => $loanId,
                    'approvedAmount' => $decision['approvedAmount'],
                    'interestRate' => $decision['interestRate'],
                    'termMonths' => $termMonths,
                ]
            );
            
            return [
                'status' => 'approved',
                'applicationId' => $applicationId,
                'loanId' => $loanId,
                'approvedAmount' => $decision['approvedAmount'],
                'interestRate' => $decision['interestRate'],
            ];
        } else {
            // Reject application
            yield $this->activities->rejectApplication(
                $applicationId,
                $decision['rejectionReasons'],
                'system'
            );
            
            yield $this->activities->notifyBorrower(
                $borrowerId,
                'rejected',
                [
                    'applicationId' => $applicationId,
                    'reasons' => $decision['rejectionReasons'],
                ]
            );
            
            return [
                'status' => 'rejected',
                'applicationId' => $applicationId,
                'reasons' => $decision['rejectionReasons'],
            ];
        }
    }
}

class LoanApplicationActivities
{
    public function __construct(
        private CreditScoringService $creditScoring,
        private RiskAssessmentService $riskAssessment
    ) {}
    
    public function createApplication(
        string $applicationId,
        string $borrowerId,
        string $requestedAmount,
        int $termMonths,
        string $purpose,
        array $borrowerInfo
    ): void {
        LoanApplication::submit(
            $applicationId,
            $borrowerId,
            $requestedAmount,
            $termMonths,
            $purpose,
            $borrowerInfo
        )->persist();
    }
    
    public function performKYCCheck(string $borrowerId): array
    {
        $user = User::find($borrowerId);
        
        // Check if user has completed KYC
        $kycStatus = $user->kyc_status ?? 'pending';
        
        if ($kycStatus !== 'verified') {
            return [
                'passed' => false,
                'reason' => 'KYC not verified',
            ];
        }
        
        return [
            'passed' => true,
            'verifiedAt' => $user->kyc_verified_at,
        ];
    }
    
    public function runCreditCheck(string $borrowerId): array
    {
        return $this->creditScoring->getScore($borrowerId);
    }
    
    public function recordCreditScore(
        string $applicationId,
        int $score,
        string $bureau,
        array $report
    ): void {
        $application = LoanApplication::retrieve($applicationId);
        $application->completeCreditCheck(
            $score,
            $bureau,
            $report,
            'system'
        );
        $application->persist();
    }
    
    public function assessRisk(
        string $applicationId,
        string $borrowerId,
        string $requestedAmount,
        int $termMonths,
        array $creditScore
    ): array {
        $application = LoanApplication::retrieve($applicationId);
        
        return $this->riskAssessment->assessLoan(
            $application,
            $creditScore,
            [
                'requestedAmount' => $requestedAmount,
                'termMonths' => $termMonths,
            ]
        );
    }
    
    public function recordRiskAssessment(
        string $applicationId,
        string $rating,
        float $defaultProbability,
        array $riskFactors
    ): void {
        $application = LoanApplication::retrieve($applicationId);
        $application->completeRiskAssessment(
            $rating,
            $defaultProbability,
            $riskFactors,
            'system'
        );
        $application->persist();
    }
    
    public function makeDecision(
        string $applicationId,
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
    
    public function approveApplication(
        string $applicationId,
        string $approvedAmount,
        float $interestRate,
        array $terms
    ): void {
        $application = LoanApplication::retrieve($applicationId);
        $application->approve(
            $approvedAmount,
            $interestRate,
            $terms,
            'system'
        );
        $application->persist();
    }
    
    public function rejectApplication(
        string $applicationId,
        array $reasons,
        string $rejectedBy
    ): void {
        $application = LoanApplication::retrieve($applicationId);
        $application->reject($reasons, $rejectedBy);
        $application->persist();
    }
    
    public function createLoan(
        string $applicationId,
        string $borrowerId,
        string $approvedAmount,
        float $interestRate,
        int $termMonths,
        array $terms
    ): string {
        $loanId = 'loan_' . uniqid();
        
        $loan = \App\Domain\Lending\Aggregates\Loan::createFromApplication(
            $loanId,
            $applicationId,
            $borrowerId,
            $approvedAmount,
            $interestRate,
            $termMonths,
            $terms
        );
        $loan->persist();
        
        return $loanId;
    }
    
    public function notifyBorrower(string $borrowerId, string $status, array $details): void
    {
        // In production, this would send email/SMS/push notification
        // For now, just log
        \Log::info("Loan application notification", [
            'borrowerId' => $borrowerId,
            'status' => $status,
            'details' => $details,
        ]);
    }
}