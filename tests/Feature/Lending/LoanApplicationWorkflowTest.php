<?php

namespace Tests\Feature\Lending;

use App\Models\User;
use App\Models\LoanApplication;
use App\Models\Loan;
use App\Domain\Lending\Services\CreditScoringService;
use App\Domain\Lending\Services\RiskAssessmentService;
use App\Services\Lending\MockCreditScoringService;
use App\Services\Lending\DefaultRiskAssessmentService;
use App\Services\Lending\LoanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Bind services
        $this->app->bind(CreditScoringService::class, MockCreditScoringService::class);
        $this->app->bind(RiskAssessmentService::class, DefaultRiskAssessmentService::class);
    }

    public function test_successful_loan_application_workflow()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_verified_at' => now()->subDays(30),
        ]);
        
        $applicationId = 'app_' . uniqid();
        $borrowerInfo = [
            'employment_status' => 'employed',
            'monthly_income' => 5000,
            'monthly_expenses' => 2000,
        ];
        
        // Mock credit scoring to return good score
        $this->mock(CreditScoringService::class, function ($mock) {
            $mock->shouldReceive('getScore')
                ->andReturn([
                    'score' => 750,
                    'bureau' => 'MockBureau',
                    'report' => [
                        'inquiries' => 1,
                        'openAccounts' => 3,
                        'totalDebt' => 10000,
                        'paymentHistory' => array_fill(0, 12, ['month' => now()->format('Y-m'), 'status' => 'on_time']),
                        'creditUtilization' => 0.3,
                    ],
                ]);
        });
        
        $service = app(LoanApplicationService::class);
        $result = $service->processApplication(
            $applicationId,
            $user->id,
            '10000',
            12,
            'personal',
            $borrowerInfo
        );
        
        $this->assertEquals('approved', $result['status']);
        $this->assertArrayHasKey('loanId', $result);
        $this->assertArrayHasKey('approvedAmount', $result);
        $this->assertArrayHasKey('interestRate', $result);
        
        // Verify application was created and approved
        $application = LoanApplication::find($applicationId);
        $this->assertNotNull($application);
        $this->assertEquals('approved', $application->status);
        $this->assertEquals(750, $application->credit_score);
        $this->assertNotNull($application->risk_rating);
        
        // Verify loan was created
        $loan = Loan::find($result['loanId']);
        $this->assertNotNull($loan);
        $this->assertEquals($applicationId, $loan->application_id);
        $this->assertEquals('created', $loan->status);
        $this->assertNotNull($loan->repayment_schedule);
    }

    public function test_loan_application_rejected_for_low_credit_score()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_verified_at' => now()->subDays(30),
        ]);
        
        $applicationId = 'app_' . uniqid();
        $borrowerInfo = [
            'employment_status' => 'employed',
            'monthly_income' => 3000,
            'monthly_expenses' => 2500,
        ];
        
        // Mock credit scoring to return low score
        $this->mock(CreditScoringService::class, function ($mock) {
            $mock->shouldReceive('getScore')
                ->andReturn([
                    'score' => 450,
                    'bureau' => 'MockBureau',
                    'report' => [
                        'inquiries' => 10,
                        'openAccounts' => 5,
                        'totalDebt' => 50000,
                        'paymentHistory' => array_fill(0, 12, ['month' => now()->format('Y-m'), 'status' => 'late']),
                        'creditUtilization' => 0.95,
                    ],
                ]);
        });
        
        $service = app(LoanApplicationService::class);
        $result = $service->processApplication(
            $applicationId,
            $user->id,
            '10000',
            12,
            'personal',
            $borrowerInfo
        );
        
        $this->assertEquals('rejected', $result['status']);
        $this->assertArrayHasKey('reasons', $result);
        $this->assertContains('Credit score below minimum threshold', $result['reasons']);
        
        // Verify application was rejected
        $application = LoanApplication::find($applicationId);
        $this->assertNotNull($application);
        $this->assertEquals('rejected', $application->status);
        $this->assertNotNull($application->rejection_reasons);
    }

    public function test_loan_application_rejected_for_failed_kyc()
    {
        $user = User::factory()->create([
            'kyc_status' => 'pending',
        ]);
        
        $applicationId = 'app_' . uniqid();
        $borrowerInfo = [
            'employment_status' => 'employed',
            'monthly_income' => 5000,
            'monthly_expenses' => 2000,
        ];
        
        $service = app(LoanApplicationService::class);
        $result = $service->processApplication(
            $applicationId,
            $user->id,
            '10000',
            12,
            'personal',
            $borrowerInfo
        );
        
        $this->assertEquals('rejected', $result['status']);
        $this->assertEquals('KYC check failed', $result['reason']);
        
        // Verify application was rejected
        $application = LoanApplication::find($applicationId);
        $this->assertNotNull($application);
        $this->assertEquals('rejected', $application->status);
    }

    public function test_loan_application_with_reduced_amount_for_high_risk()
    {
        $user = User::factory()->create([
            'kyc_status' => 'approved',
            'kyc_verified_at' => now()->subDays(30),
            'created_at' => now()->subMonths(3), // New account
        ]);
        
        $applicationId = 'app_' . uniqid();
        $requestedAmount = '20000';
        $borrowerInfo = [
            'employment_status' => 'self_employed',
            'monthly_income' => 4000,
            'monthly_expenses' => 3000,
        ];
        
        // Mock credit scoring to return medium score
        $this->mock(CreditScoringService::class, function ($mock) {
            $mock->shouldReceive('getScore')
                ->andReturn([
                    'score' => 650,
                    'bureau' => 'MockBureau',
                    'report' => [
                        'inquiries' => 3,
                        'openAccounts' => 4,
                        'totalDebt' => 25000,
                        'paymentHistory' => array_merge(
                            array_fill(0, 10, ['month' => now()->format('Y-m'), 'status' => 'on_time']),
                            array_fill(0, 2, ['month' => now()->format('Y-m'), 'status' => 'late'])
                        ),
                        'creditUtilization' => 0.7,
                    ],
                ]);
        });
        
        $service = app(LoanApplicationService::class);
        $result = $service->processApplication(
            $applicationId,
            $user->id,
            $requestedAmount,
            24,
            'business',
            $borrowerInfo
        );
        
        $this->assertEquals('approved', $result['status']);
        
        // Check if amount was reduced for high risk
        $approvedAmount = $result['approvedAmount'];
        $this->assertLessThan($requestedAmount, $approvedAmount);
        
        // Verify higher interest rate for risk
        $this->assertGreaterThan(5.0, $result['interestRate']);
    }
}