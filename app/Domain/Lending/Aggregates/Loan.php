<?php

namespace App\Domain\Lending\Aggregates;

use App\Domain\Lending\Events\LoanCreated;
use App\Domain\Lending\Events\LoanFunded;
use App\Domain\Lending\Events\LoanDisbursed;
use App\Domain\Lending\Events\LoanRepaymentMade;
use App\Domain\Lending\Events\LoanPaymentMissed;
use App\Domain\Lending\Events\LoanDefaulted;
use App\Domain\Lending\Events\LoanSettledEarly;
use App\Domain\Lending\Events\LoanCompleted;
use App\Domain\Lending\Exceptions\LoanException;
use App\Domain\Lending\Repositories\LendingEventRepository;
use App\Domain\Lending\ValueObjects\RepaymentSchedule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class Loan extends AggregateRoot
{
    private string $loanId;
    private string $applicationId;
    private string $borrowerId;
    private string $principal;
    private float $interestRate;
    private int $termMonths;
    private string $status = 'created';
    private array $investorIds = [];
    private ?RepaymentSchedule $schedule = null;
    private string $outstandingBalance;
    private string $totalPrincipalPaid = '0';
    private string $totalInterestPaid = '0';
    private int $paymentsReceived = 0;
    private int $missedPayments = 0;
    private ?\DateTimeImmutable $fundedAt = null;
    private ?\DateTimeImmutable $disbursedAt = null;
    private ?\DateTimeImmutable $defaultedAt = null;
    private ?\DateTimeImmutable $completedAt = null;
    
    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(LendingEventRepository::class);
    }
    
    public static function createFromApplication(
        string $loanId,
        string $applicationId,
        string $borrowerId,
        string $principal,
        float $interestRate,
        int $termMonths,
        array $terms
    ): self {
        if (BigDecimal::of($principal)->isLessThanOrEqualTo(0)) {
            throw new LoanException('Principal amount must be greater than zero');
        }
        
        if ($interestRate < 0 || $interestRate > 100) {
            throw new LoanException('Interest rate must be between 0 and 100');
        }
        
        if ($termMonths < 1 || $termMonths > 360) {
            throw new LoanException('Term must be between 1 and 360 months');
        }
        
        $loan = static::retrieve($loanId);
        
        // Generate repayment schedule
        $schedule = $loan->generateRepaymentScheduleForNewLoan($principal, $interestRate, $termMonths);
        
        $loan->recordThat(new LoanCreated(
            loanId: $loanId,
            applicationId: $applicationId,
            borrowerId: $borrowerId,
            principal: $principal,
            interestRate: $interestRate,
            termMonths: $termMonths,
            repaymentSchedule: $schedule,
            terms: $terms,
            createdAt: new \DateTimeImmutable()
        ));
        
        return $loan;
    }
    
    public function fund(array $investorIds, string $fundedAmount): self
    {
        if ($this->status !== 'created') {
            throw new LoanException('Can only fund loans in created status');
        }
        
        if (!BigDecimal::of($fundedAmount)->isEqualTo($this->principal)) {
            throw new LoanException('Funded amount must equal principal amount');
        }
        
        $this->recordThat(new LoanFunded(
            loanId: $this->loanId,
            investorIds: $investorIds,
            fundedAmount: $fundedAmount,
            fundedAt: new \DateTimeImmutable()
        ));
        
        return $this;
    }
    
    public function disburse(string $amount): self
    {
        if ($this->status !== 'funded') {
            throw new LoanException('Can only disburse funded loans');
        }
        
        $this->recordThat(new LoanDisbursed(
            loanId: $this->loanId,
            amount: $amount,
            disbursedAt: new \DateTimeImmutable()
        ));
        
        return $this;
    }
    
    public function recordRepayment(
        int $paymentNumber,
        string $amount,
        string $principalAmount,
        string $interestAmount,
        string $paidBy
    ): self {
        if ($this->status !== 'active') {
            throw new LoanException('Can only record repayments for active loans');
        }
        
        if (BigDecimal::of($amount)->isLessThanOrEqualTo(0)) {
            throw new LoanException('Payment amount must be greater than zero');
        }
        
        $remainingBalance = BigDecimal::of($this->outstandingBalance)
            ->minus($principalAmount)
            ->toScale(2, RoundingMode::DOWN)
            ->__toString();
        
        $this->recordThat(new LoanRepaymentMade(
            loanId: $this->loanId,
            paymentNumber: $paymentNumber,
            amount: $amount,
            principalAmount: $principalAmount,
            interestAmount: $interestAmount,
            remainingBalance: $remainingBalance,
            paidAt: new \DateTimeImmutable()
        ));
        
        // Check if loan is fully paid
        if (BigDecimal::of($remainingBalance)->isEqualTo(0)) {
            $this->recordThat(new LoanCompleted(
                loanId: $this->loanId,
                totalPrincipalPaid: $this->principal,
                totalInterestPaid: bcadd($this->totalInterestPaid, $interestAmount, 2),
                completedAt: new \DateTimeImmutable()
            ));
        }
        
        return $this;
    }
    
    public function missPayment(int $paymentNumber): self
    {
        if ($this->status !== 'active') {
            throw new LoanException('Can only mark payments as missed for active loans');
        }
        
        $this->recordThat(new LoanPaymentMissed(
            loanId: $this->loanId,
            paymentNumber: $paymentNumber,
            missedAt: new \DateTimeImmutable()
        ));
        
        return $this;
    }
    
    public function markAsDefaulted(string $reason): self
    {
        if (!in_array($this->status, ['active', 'delinquent'])) {
            throw new LoanException('Can only mark active or delinquent loans as defaulted');
        }
        
        $this->recordThat(new LoanDefaulted(
            loanId: $this->loanId,
            reason: $reason,
            outstandingBalance: $this->outstandingBalance,
            defaultedAt: new \DateTimeImmutable()
        ));
        
        return $this;
    }
    
    public function settleEarly(string $settlementAmount, string $settledBy): self
    {
        if (!in_array($this->status, ['active', 'delinquent'])) {
            throw new LoanException('Can only settle active or delinquent loans');
        }
        
        if (BigDecimal::of($settlementAmount)->isLessThan($this->outstandingBalance)) {
            throw new LoanException('Settlement amount must cover outstanding balance');
        }
        
        $this->recordThat(new LoanSettledEarly(
            loanId: $this->loanId,
            settlementAmount: $settlementAmount,
            outstandingBalance: $this->outstandingBalance,
            settledBy: $settledBy,
            settledAt: new \DateTimeImmutable()
        ));
        
        return $this;
    }
    
    // Event handlers
    protected function applyLoanCreated(LoanCreated $event): void
    {
        $this->loanId = $event->loanId;
        $this->applicationId = $event->applicationId;
        $this->borrowerId = $event->borrowerId;
        $this->principal = $event->principal;
        $this->interestRate = $event->interestRate;
        $this->termMonths = $event->termMonths;
        $this->schedule = $event->repaymentSchedule;
        $this->outstandingBalance = $event->principal;
        $this->status = 'created';
    }
    
    protected function applyLoanFunded(LoanFunded $event): void
    {
        $this->investorIds = $event->investorIds;
        $this->fundedAt = $event->fundedAt;
        $this->status = 'funded';
    }
    
    protected function applyLoanDisbursed(LoanDisbursed $event): void
    {
        $this->disbursedAt = $event->disbursedAt;
        $this->status = 'active';
    }
    
    protected function applyLoanRepaymentMade(LoanRepaymentMade $event): void
    {
        $this->paymentsReceived++;
        $this->totalPrincipalPaid = bcadd($this->totalPrincipalPaid, $event->principalAmount, 2);
        $this->totalInterestPaid = bcadd($this->totalInterestPaid, $event->interestAmount, 2);
        $this->outstandingBalance = $event->remainingBalance;
    }
    
    protected function applyLoanPaymentMissed(LoanPaymentMissed $event): void
    {
        $this->missedPayments++;
        if ($this->missedPayments >= 1) {
            $this->status = 'delinquent';
        }
    }
    
    protected function applyLoanDefaulted(LoanDefaulted $event): void
    {
        $this->defaultedAt = $event->defaultedAt;
        $this->status = 'defaulted';
    }
    
    protected function applyLoanCompleted(LoanCompleted $event): void
    {
        $this->completedAt = $event->completedAt;
        $this->status = 'completed';
    }
    
    protected function applyLoanSettledEarly(LoanSettledEarly $event): void
    {
        $this->status = 'settled';
        $this->outstandingBalance = '0';
    }
    
    // Helper methods
    private function generateRepaymentScheduleForNewLoan(string $principal, float $interestRate, int $termMonths): RepaymentSchedule
    {
        $monthlyRate = $interestRate / 100 / 12;
        $principalAmount = BigDecimal::of($principal);
        
        // Calculate monthly payment using amortization formula
        if ($monthlyRate > 0) {
            $monthlyPayment = $principalAmount->multipliedBy($monthlyRate)
                ->multipliedBy(pow(1 + $monthlyRate, $termMonths))
                ->dividedBy(pow(1 + $monthlyRate, $termMonths) - 1, 2, RoundingMode::UP);
        } else {
            // If no interest, just divide principal equally
            $monthlyPayment = $principalAmount->dividedBy($termMonths, 2, RoundingMode::UP);
        }
        
        $payments = [];
        $remainingBalance = $principalAmount;
        
        for ($i = 1; $i <= $termMonths; $i++) {
            $interestPayment = $remainingBalance->multipliedBy($monthlyRate)->toScale(2, RoundingMode::UP);
            $principalPayment = $monthlyPayment->minus($interestPayment)->toScale(2, RoundingMode::DOWN);
            
            // Adjust last payment
            if ($i === $termMonths) {
                $principalPayment = $remainingBalance;
                $monthlyPayment = $principalPayment->plus($interestPayment);
            }
            
            $remainingBalance = $remainingBalance->minus($principalPayment);
            
            $payments[] = [
                'payment_number' => $i,
                'due_date' => now()->addMonths($i)->format('Y-m-d'),
                'principal' => $principalPayment->__toString(),
                'interest' => $interestPayment->__toString(),
                'total' => $monthlyPayment->__toString(),
                'remaining_balance' => $remainingBalance->__toString(),
            ];
        }
        
        return new RepaymentSchedule($payments);
    }
    
    // Public getter methods
    public function getRepaymentSchedule(): ?RepaymentSchedule
    {
        return $this->schedule;
    }
    
    public function getLoanId(): string
    {
        return $this->loanId;
    }
}