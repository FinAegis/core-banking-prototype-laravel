<?php

namespace Tests\Feature\Lending;

use App\Domain\Lending\Aggregates\Loan;
use App\Domain\Lending\Events\LoanCreated;
use App\Domain\Lending\Events\LoanFunded;
use App\Domain\Lending\Events\LoanDisbursed;
use App\Domain\Lending\Events\LoanRepaymentMade;
use App\Domain\Lending\Events\LoanCompleted;
use App\Domain\Lending\ValueObjects\RepaymentSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Tests\TestCase;

class LoanAggregateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_loan_creation_from_application()
    {
        $loanId = 'loan_' . uniqid();
        $applicationId = 'app_' . uniqid();
        $borrowerId = 'user_' . uniqid();
        $principal = '10000.00';
        $interestRate = 10.0;
        $termMonths = 12;
        $terms = [
            'repaymentFrequency' => 'monthly',
            'lateFeePercentage' => 5.0,
            'gracePeriodDays' => 5,
        ];
        
        $loan = Loan::createFromApplication(
            $loanId,
            $applicationId,
            $borrowerId,
            $principal,
            $interestRate,
            $termMonths,
            $terms
        );
        
        $events = $loan->getRecordedEvents();
        $loan->persist();
        
        $this->assertCount(1, $events);
        
        $event = $events[0];
        $this->assertInstanceOf(LoanCreated::class, $event);
        $this->assertEquals($loanId, $event->loanId);
        $this->assertEquals($applicationId, $event->applicationId);
        $this->assertEquals($borrowerId, $event->borrowerId);
        $this->assertEquals($principal, $event->principal);
        $this->assertEquals($interestRate, $event->interestRate);
        $this->assertEquals($termMonths, $event->termMonths);
        $this->assertInstanceOf(RepaymentSchedule::class, $event->repaymentSchedule);
        
        // Verify repayment schedule
        $schedule = $event->repaymentSchedule;
        $this->assertEquals($termMonths, $schedule->getTotalPayments());
        $this->assertEquals($principal, $schedule->getTotalPrincipal());
        $this->assertGreaterThan('0', $schedule->getTotalInterest());
    }

    public function test_loan_funding_and_disbursement()
    {
        $loanId = 'loan_' . uniqid();
        $investorIds = ['investor1', 'investor2', 'investor3'];
        $fundedAmount = '10000.00';
        
        $loan = Loan::createFromApplication(
            $loanId,
            'app_' . uniqid(),
            'borrower_' . uniqid(),
            $fundedAmount,
            10.0,
            12,
            []
        );
        
        $loan->fund($investorIds, $fundedAmount);
        $loan->disburse($fundedAmount);
        
        $events = $loan->getRecordedEvents();
        $this->assertCount(3, $events);
        
        // Check funding event
        $fundingEvent = $events[1];
        $this->assertInstanceOf(LoanFunded::class, $fundingEvent);
        $this->assertEquals($investorIds, $fundingEvent->investorIds);
        $this->assertEquals($fundedAmount, $fundingEvent->fundedAmount);
        
        // Check disbursement event
        $disbursementEvent = $events[2];
        $this->assertInstanceOf(LoanDisbursed::class, $disbursementEvent);
        $this->assertEquals($fundedAmount, $disbursementEvent->amount);
    }

    public function test_loan_repayment_recording()
    {
        $loanId = 'loan_' . uniqid();
        $principal = '1000.00';
        $interestRate = 12.0; // 12% APR = 1% monthly
        $termMonths = 3;
        
        $loan = Loan::createFromApplication(
            $loanId,
            'app_' . uniqid(),
            'borrower_' . uniqid(),
            $principal,
            $interestRate,
            $termMonths,
            []
        );
        
        $loan->fund(['investor1'], $principal);
        $loan->disburse($principal);
        
        // Get first payment from schedule
        $schedule = $loan->getRepaymentSchedule();
        $firstPayment = $schedule->getPayment(1);
        
        // Make first payment
        $loan->recordRepayment(
            1,
            $firstPayment['total'],
            $firstPayment['principal'],
            $firstPayment['interest'],
            'payer_' . uniqid()
        );
        
        $events = $loan->getRecordedEvents();
        $repaymentEvent = end($events);
        
        $this->assertInstanceOf(LoanRepaymentMade::class, $repaymentEvent);
        $this->assertEquals(1, $repaymentEvent->paymentNumber);
        $this->assertEquals($firstPayment['total'], $repaymentEvent->amount);
        $this->assertEquals($firstPayment['principal'], $repaymentEvent->principalAmount);
        $this->assertEquals($firstPayment['interest'], $repaymentEvent->interestAmount);
    }

    public function test_loan_completion_after_all_payments()
    {
        $loanId = 'loan_' . uniqid();
        $principal = '1000.00';
        $interestRate = 0.0; // 0% interest for simplicity
        $termMonths = 2;
        
        $loan = Loan::createFromApplication(
            $loanId,
            'app_' . uniqid(),
            'borrower_' . uniqid(),
            $principal,
            $interestRate,
            $termMonths,
            []
        );
        
        $loan->fund(['investor1'], $principal);
        $loan->disburse($principal);
        
        // Get repayment schedule
        $schedule = $loan->getRepaymentSchedule();
        
        // Make all payments
        for ($i = 1; $i <= $termMonths; $i++) {
            $payment = $schedule->getPayment($i);
            $loan->recordRepayment(
                $i,
                $payment['total'],
                $payment['principal'],
                $payment['interest'],
                'payer_' . uniqid()
            );
        }
        
        // Loan should be automatically completed
        $events = $loan->getRecordedEvents();
        $lastEvent = end($events);
        
        $this->assertInstanceOf(LoanCompleted::class, $lastEvent);
        $this->assertEquals($principal, $lastEvent->totalPrincipalPaid);
        $this->assertEquals('0.00', $lastEvent->totalInterestPaid); // 0% interest
    }

    public function test_loan_early_settlement()
    {
        $loanId = 'loan_' . uniqid();
        $principal = '10000.00';
        $interestRate = 12.0;
        $termMonths = 12;
        
        $loan = Loan::createFromApplication(
            $loanId,
            'app_' . uniqid(),
            'borrower_' . uniqid(),
            $principal,
            $interestRate,
            $termMonths,
            []
        );
        
        $loan->fund(['investor1'], $principal);
        $loan->disburse($principal);
        
        // Make 3 payments
        $schedule = $loan->getRepaymentSchedule();
        for ($i = 1; $i <= 3; $i++) {
            $payment = $schedule->getPayment($i);
            $loan->recordRepayment(
                $i,
                $payment['total'],
                $payment['principal'],
                $payment['interest'],
                'payer_' . uniqid()
            );
        }
        
        // Calculate remaining balance and settle early
        $totalPrincipalPaid = '0';
        for ($i = 1; $i <= 3; $i++) {
            $payment = $schedule->getPayment($i);
            $totalPrincipalPaid = bcadd($totalPrincipalPaid, $payment['principal'], 2);
        }
        $remainingBalance = bcsub($principal, $totalPrincipalPaid, 2);
        
        $loan->settleEarly($remainingBalance, 'borrower_' . uniqid());
        
        $events = $loan->getRecordedEvents();
        $settlementEvent = end($events);
        
        $this->assertInstanceOf(\App\Domain\Lending\Events\LoanSettledEarly::class, $settlementEvent);
        $this->assertEquals($remainingBalance, $settlementEvent->settlementAmount);
        $this->assertEquals($remainingBalance, $settlementEvent->outstandingBalance);
    }
}