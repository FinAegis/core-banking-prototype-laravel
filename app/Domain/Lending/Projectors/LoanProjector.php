<?php

namespace App\Domain\Lending\Projectors;

use App\Domain\Lending\Events\LoanCreated;
use App\Domain\Lending\Events\LoanFunded;
use App\Domain\Lending\Events\LoanDisbursed;
use App\Domain\Lending\Events\LoanRepaymentMade;
use App\Domain\Lending\Events\LoanPaymentMissed;
use App\Domain\Lending\Events\LoanDefaulted;
use App\Domain\Lending\Events\LoanCompleted;
use App\Domain\Lending\Events\LoanSettledEarly;
use App\Models\Loan;
use App\Models\LoanRepayment;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class LoanProjector extends Projector
{
    public function onLoanCreated(LoanCreated $event): void
    {
        Loan::create([
            'id' => $event->loanId,
            'application_id' => $event->applicationId,
            'borrower_id' => $event->borrowerId,
            'principal' => $event->principal,
            'interest_rate' => $event->interestRate,
            'term_months' => $event->termMonths,
            'repayment_schedule' => $event->repaymentSchedule->toArray(),
            'terms' => $event->terms,
            'status' => 'created',
            'created_at' => $event->createdAt,
        ]);
    }
    
    public function onLoanFunded(LoanFunded $event): void
    {
        Loan::where('id', $event->loanId)->update([
            'investor_ids' => $event->investorIds,
            'funded_amount' => $event->fundedAmount,
            'funded_at' => $event->fundedAt,
            'status' => 'funded',
        ]);
    }
    
    public function onLoanDisbursed(LoanDisbursed $event): void
    {
        Loan::where('id', $event->loanId)->update([
            'disbursed_amount' => $event->amount,
            'disbursed_at' => $event->disbursedAt,
            'status' => 'active',
        ]);
    }
    
    public function onLoanRepaymentMade(LoanRepaymentMade $event): void
    {
        // Update loan
        $loan = Loan::find($event->loanId);
        $loan->increment('total_principal_paid', $event->principalAmount);
        $loan->increment('total_interest_paid', $event->interestAmount);
        $loan->last_payment_date = $event->paidAt;
        $loan->save();
        
        // Create repayment record
        LoanRepayment::create([
            'loan_id' => $event->loanId,
            'payment_number' => $event->paymentNumber,
            'amount' => $event->amount,
            'principal_amount' => $event->principalAmount,
            'interest_amount' => $event->interestAmount,
            'remaining_balance' => $event->remainingBalance,
            'paid_at' => $event->paidAt,
        ]);
    }
    
    public function onLoanPaymentMissed(LoanPaymentMissed $event): void
    {
        Loan::where('id', $event->loanId)->update([
            'missed_payments' => \DB::raw('missed_payments + 1'),
            'status' => 'delinquent',
        ]);
    }
    
    public function onLoanDefaulted(LoanDefaulted $event): void
    {
        Loan::where('id', $event->loanId)->update([
            'defaulted_at' => $event->defaultedAt,
            'status' => 'defaulted',
        ]);
    }
    
    public function onLoanCompleted(LoanCompleted $event): void
    {
        Loan::where('id', $event->loanId)->update([
            'completed_at' => $event->completedAt,
            'status' => 'completed',
        ]);
    }
    
    public function onLoanSettledEarly(LoanSettledEarly $event): void
    {
        Loan::where('id', $event->loanId)->update([
            'settlement_amount' => $event->settlementAmount,
            'settled_at' => $event->settledAt,
            'settled_by' => $event->settledBy,
            'status' => 'settled',
        ]);
    }
}