<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Lending\Services;

use App\Domain\Lending\Events\LoanApplicationApproved;
use App\Domain\Lending\Events\LoanApplicationRejected;
use App\Domain\Lending\Events\LoanApplicationSubmitted;
use App\Domain\Lending\Events\LoanDisbursed;
use App\Domain\Lending\Events\RepaymentReceived;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanApplication;
use App\Domain\Lending\Services\DemoLendingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DemoLendingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemoLendingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('demo.mode', true);
        Config::set('demo.features.auto_approve', true);
        Config::set('demo.features.instant_deposits', true);
        Config::set('demo.domains.lending.auto_approve_threshold', 10000);
        Config::set('demo.domains.lending.approval_rate', 80);
        Config::set('demo.domains.lending.default_credit_score', 750);
        Config::set('demo.domains.lending.default_interest_rate', 5.5);

        $this->service = new DemoLendingService();
    }

    /** @test */
    public function it_can_submit_loan_application_with_auto_approval()
    {
        Event::fake();

        $applicationData = [
            'borrower_id'      => 1,
            'requested_amount' => 5000,
            'currency'         => 'USD',
            'term_months'      => 12,
            'purpose'          => 'Business expansion',
            'borrower_info'    => [
                'name'  => 'John Doe',
                'email' => 'john@example.com',
            ],
        ];

        $application = $this->service->applyForLoan($applicationData);

        $this->assertInstanceOf(LoanApplication::class, $application);
        $this->assertStringStartsWith('demo_app_', $application->id);
        $this->assertEquals(5000, $application->requested_amount);
        $this->assertEquals('USD', $application->borrower_info['currency'] ?? null);
        $this->assertEquals(12, $application->term_months);
        $this->assertTrue($application->borrower_info['demo_mode'] ?? false);

        Event::assertDispatched(LoanApplicationSubmitted::class);
    }

    /** @test */
    public function it_auto_approves_loan_within_threshold_with_good_credit()
    {
        Event::fake();
        srand(1); // Seed random for consistent test results

        // Mock a good credit score
        Config::set('demo.domains.lending.default_credit_score', 750);
        Config::set('demo.domains.lending.approval_rate', 100); // Ensure approval

        $applicationData = [
            'borrower_id'      => 1,
            'requested_amount' => 8000, // Below 10000 threshold
            'currency'         => 'USD',
            'term_months'      => 24,
            'purpose'          => 'Debt consolidation',
        ];

        $application = $this->service->applyForLoan($applicationData);
        $application->refresh();

        $this->assertEquals('approved', $application->status);
        $this->assertNotNull($application->approved_at);
        $this->assertNotNull($application->approved_amount);
        $this->assertNotNull($application->interest_rate);
        $this->assertNotNull($application->approval_metadata);
        $this->assertTrue($application->approval_metadata['auto_approved'] ?? false);

        Event::assertDispatched(LoanApplicationApproved::class);
        Event::assertDispatched(LoanDisbursed::class);

        // Check that a loan was created
        $loan = Loan::where('application_id', $application->id)->first();
        $this->assertNotNull($loan);
        $this->assertStringStartsWith('demo_loan_', $loan->id);
        $this->assertEquals('active', $loan->status);
        $this->assertEquals($application->approved_amount, $loan->principal);
    }

    /** @test */
    public function it_rejects_loan_with_poor_credit_score()
    {
        Event::fake();

        // Create application with poor credit simulation
        $application = LoanApplication::create([
            'id'               => 'demo_app_test123',
            'borrower_id'      => 1,
            'requested_amount' => 5000,
            'term_months'      => 12,
            'purpose'          => 'Emergency',
            'status'           => 'pending',
            'borrower_info'    => ['currency' => 'USD'],
            'submitted_at'     => now(),
        ]);

        // Mock poor credit score by setting config temporarily
        Config::set('demo.domains.lending.default_credit_score', 550);

        $this->service->processApplication($application);
        $application->refresh();

        $this->assertEquals('rejected', $application->status);
        $this->assertNotNull($application->rejected_at);
        $this->assertNotEmpty($application->rejection_reasons);
        $this->assertContains('Credit score below minimum requirement', $application->rejection_reasons);

        Event::assertDispatched(LoanApplicationRejected::class);
        Event::assertNotDispatched(LoanApplicationApproved::class);
    }

    /** @test */
    public function it_rejects_loan_exceeding_threshold_amount()
    {
        Event::fake();
        Config::set('demo.domains.lending.approval_rate', 0); // Force rejection

        $applicationData = [
            'borrower_id'      => 1,
            'requested_amount' => 15000, // Above 10000 threshold
            'currency'         => 'USD',
            'term_months'      => 36,
            'purpose'          => 'Large purchase',
        ];

        $application = $this->service->applyForLoan($applicationData);
        $application->refresh();

        $this->assertEquals('rejected', $application->status);
        $this->assertNotNull($application->rejection_reasons);

        Event::assertDispatched(LoanApplicationRejected::class);
    }

    /** @test */
    public function it_can_make_loan_payment_with_correct_allocation()
    {
        Event::fake();

        // Create a loan first
        $loan = Loan::create([
            'id'                 => 'demo_loan_test123',
            'application_id'     => 'demo_app_test123',
            'borrower_id'        => 1,
            'principal'          => 10000,
            'interest_rate'      => 6.0,
            'term_months'        => 12,
            'repayment_schedule' => json_encode([
                ['payment_number' => 1, 'amount' => 860.66, 'due_date' => Carbon::now()->addMonth()->toDateString()],
                ['payment_number' => 2, 'amount' => 860.66, 'due_date' => Carbon::now()->addMonths(2)->toDateString()],
            ]),
            'terms'            => json_encode(['interest_rate' => 6.0, 'term_months' => 12]),
            'status'           => 'active',
            'disbursed_at'     => now(),
            'disbursed_amount' => 10000,
        ]);

        $payment = $this->service->makePayment($loan->id, 860.66);

        // Payment is already known to be an array
        $this->assertStringStartsWith('demo_pmt_', $payment['id']);
        $this->assertEquals(860.66, $payment['amount']);
        $this->assertEquals('completed', $payment['status']);
        $this->assertTrue($payment['metadata']['demo_mode']);

        // Check interest calculation (6% annual = 0.5% monthly on 10000 = 50)
        $expectedInterest = round(10000 * (6.0 / 100 / 12), 2);
        $this->assertEquals($expectedInterest, $payment['interest_amount']);
        $this->assertEquals(860.66 - $expectedInterest, $payment['principal_amount']);

        // Check loan balance update
        $loan->refresh();
        $this->assertEquals($payment['principal_amount'], $loan->total_principal_paid);
        $this->assertNotNull($loan->last_payment_date);

        Event::assertDispatched(RepaymentReceived::class);
    }

    /** @test */
    public function it_marks_loan_as_paid_off_when_fully_paid()
    {
        Event::fake();

        $loan = Loan::create([
            'id'                 => 'demo_loan_test456',
            'application_id'     => 'demo_app_test456',
            'borrower_id'        => 1,
            'principal'          => 1000,
            'interest_rate'      => 5.0,
            'term_months'        => 12,
            'repayment_schedule' => json_encode([
                ['payment_number' => 1, 'amount' => 85.61, 'due_date' => Carbon::now()->addMonth()->toDateString()],
                ['payment_number' => 2, 'amount' => 85.61, 'due_date' => Carbon::now()->addMonths(2)->toDateString()],
            ]),
            'terms'                => json_encode(['interest_rate' => 5.0, 'term_months' => 12]),
            'status'               => 'active',
            'disbursed_at'         => now(),
            'disbursed_amount'     => 1000,
            'total_principal_paid' => 915, // Almost paid off
        ]);

        $payment = $this->service->makePayment($loan->id, 85.61);

        $loan->refresh();
        $this->assertEquals(0, $loan->remaining_balance);
        $this->assertEquals('completed', $loan->status);
        $this->assertNull($loan->next_payment_date);
    }

    /** @test */
    public function it_generates_correct_loan_details_with_payment_schedule()
    {
        $loan = Loan::create([
            'id'                 => 'demo_loan_test789',
            'application_id'     => 'demo_app_test789',
            'borrower_id'        => 1,
            'principal'          => 5000,
            'interest_rate'      => 7.5,
            'term_months'        => 6,
            'repayment_schedule' => json_encode([
                ['payment_number' => 1, 'amount' => 847.89, 'due_date' => now()->toDateString()],
                ['payment_number' => 2, 'amount' => 847.89, 'due_date' => now()->addMonth()->toDateString()],
                ['payment_number' => 3, 'amount' => 847.89, 'due_date' => now()->addMonths(2)->toDateString()],
                ['payment_number' => 4, 'amount' => 847.89, 'due_date' => now()->addMonths(3)->toDateString()],
                ['payment_number' => 5, 'amount' => 847.89, 'due_date' => now()->addMonths(4)->toDateString()],
                ['payment_number' => 6, 'amount' => 847.89, 'due_date' => now()->addMonths(5)->toDateString()],
            ]),
            'terms'            => json_encode(['interest_rate' => 7.5, 'term_months' => 6]),
            'status'           => 'active',
            'disbursed_at'     => now()->subMonth(),
            'disbursed_amount' => 5000,
        ]);

        // Create application for relationship
        LoanApplication::create([
            'id'               => 'demo_app_test789',
            'borrower_id'      => 1,
            'requested_amount' => 5000,
            'term_months'      => 6,
            'purpose'          => 'Test',
            'status'           => 'approved',
            'borrower_info'    => ['currency' => 'USD'],
            'submitted_at'     => now(),
        ]);

        $details = $this->service->getLoanDetails($loan->id);

        $this->assertArrayHasKey('loan', $details);
        $this->assertArrayHasKey('payment_schedule', $details);
        $this->assertArrayHasKey('total_paid', $details);
        $this->assertArrayHasKey('total_interest_paid', $details);
        $this->assertArrayHasKey('remaining_payments', $details);
        $this->assertTrue($details['demo']);

        $schedule = $details['payment_schedule'];
        $this->assertCount(6, $schedule); // 6 month term
        // Check payment amount is within reasonable range (floating point precision)
        $this->assertEqualsWithDelta(851.66, $schedule[0]['payment_amount'], 0.01);
        $this->assertEquals('pending', $schedule[0]['status']);
    }

    /** @test */
    public function it_calculates_interest_rate_based_on_credit_score()
    {
        Event::fake();
        srand(1); // Seed random for consistent test results

        // Test with excellent credit score
        Config::set('demo.domains.lending.default_credit_score', 820);
        Config::set('demo.domains.lending.approval_rate', 100);

        $applicationData = [
            'borrower_id'      => 1,
            'requested_amount' => 5000,
            'currency'         => 'USD',
            'term_months'      => 12,
            'purpose'          => 'Test',
        ];

        $application = $this->service->applyForLoan($applicationData);
        $application->refresh();

        // With 820 credit score and 12 month term, should get base rate - 1.5 - 0.5 = 3.5%
        $expectedRate = 5.5 - 1.5 - 0.5; // base - credit adjustment - term adjustment
        $this->assertEquals($expectedRate, $application->interest_rate);
    }

    /** @test */
    public function it_respects_auto_approve_configuration()
    {
        Config::set('demo.features.auto_approve', false);
        Event::fake();

        $applicationData = [
            'borrower_id'      => 1,
            'requested_amount' => 1000,
            'currency'         => 'USD',
            'term_months'      => 12,
            'purpose'          => 'Test',
        ];

        $application = $this->service->applyForLoan($applicationData);

        $this->assertEquals('pending', $application->status);
        $this->assertNull($application->approved_at);
        $this->assertNull($application->rejected_at);

        Event::assertDispatched(LoanApplicationSubmitted::class);
        Event::assertNotDispatched(LoanApplicationApproved::class);
        Event::assertNotDispatched(LoanApplicationRejected::class);
    }

    /** @test */
    public function it_assesses_risk_factors_correctly()
    {
        $application = LoanApplication::create([
            'id'               => 'demo_app_risk_test',
            'borrower_id'      => 1,
            'requested_amount' => 60000, // High amount
            'term_months'      => 72, // Long term
            'purpose'          => 'Test',
            'status'           => 'pending',
            'borrower_info'    => ['demo' => true, 'currency' => 'USD'],
            'submitted_at'     => now(),
        ]);

        Config::set('demo.domains.lending.default_credit_score', 600); // Fair credit

        $this->service->processApplication($application);
        $application->refresh();

        $this->assertNotNull($application->risk_rating);
        $this->assertNotNull($application->risk_factors);
        $this->assertContains('High loan amount', $application->risk_factors);
        $this->assertContains('Long repayment term', $application->risk_factors);
        $this->assertEquals('high', $application->risk_rating);
    }

    /** @test */
    public function it_limits_loan_amount_based_on_credit_score()
    {
        Event::fake();
        srand(1); // Seed random for consistent test results
        Config::set('demo.domains.lending.default_credit_score', 650);
        Config::set('demo.domains.lending.approval_rate', 100);

        $applicationData = [
            'borrower_id'      => 1,
            'requested_amount' => 30000, // More than 25000 limit for 650 credit
            'currency'         => 'USD',
            'term_months'      => 24,
            'purpose'          => 'Test',
        ];

        $application = $this->service->applyForLoan($applicationData);
        $application->refresh();

        // Should be approved but amount limited to 25000
        $this->assertEquals('approved', $application->status);
        $this->assertEquals(25000, $application->approved_amount);
        $this->assertLessThan($application->requested_amount, $application->approved_amount);
    }
}
