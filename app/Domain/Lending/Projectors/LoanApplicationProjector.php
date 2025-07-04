<?php

namespace App\Domain\Lending\Projectors;

use App\Domain\Lending\Events\LoanApplicationSubmitted;
use App\Domain\Lending\Events\LoanApplicationCreditCheckCompleted;
use App\Domain\Lending\Events\LoanApplicationRiskAssessmentCompleted;
use App\Domain\Lending\Events\LoanApplicationApproved;
use App\Domain\Lending\Events\LoanApplicationRejected;
use App\Models\LoanApplication;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class LoanApplicationProjector extends Projector
{
    public function onLoanApplicationSubmitted(LoanApplicationSubmitted $event): void
    {
        LoanApplication::create([
            'id' => $event->applicationId,
            'borrower_id' => $event->borrowerId,
            'requested_amount' => $event->requestedAmount,
            'term_months' => $event->termMonths,
            'purpose' => $event->purpose,
            'status' => 'submitted',
            'borrower_info' => $event->borrowerInfo,
            'submitted_at' => $event->submittedAt,
        ]);
    }
    
    public function onLoanApplicationCreditCheckCompleted(LoanApplicationCreditCheckCompleted $event): void
    {
        LoanApplication::where('id', $event->applicationId)->update([
            'credit_score' => $event->score,
            'credit_bureau' => $event->bureau,
            'credit_report' => $event->report,
            'credit_checked_at' => $event->checkedAt,
            'status' => 'credit_checked',
        ]);
    }
    
    public function onLoanApplicationRiskAssessmentCompleted(LoanApplicationRiskAssessmentCompleted $event): void
    {
        LoanApplication::where('id', $event->applicationId)->update([
            'risk_rating' => $event->rating,
            'default_probability' => $event->defaultProbability,
            'risk_factors' => $event->riskFactors,
            'risk_assessed_at' => $event->assessedAt,
            'status' => 'risk_assessed',
        ]);
    }
    
    public function onLoanApplicationApproved(LoanApplicationApproved $event): void
    {
        LoanApplication::where('id', $event->applicationId)->update([
            'approved_amount' => $event->approvedAmount,
            'interest_rate' => $event->interestRate,
            'terms' => $event->terms,
            'approved_by' => $event->approvedBy,
            'approved_at' => $event->approvedAt,
            'status' => 'approved',
        ]);
    }
    
    public function onLoanApplicationRejected(LoanApplicationRejected $event): void
    {
        LoanApplication::where('id', $event->applicationId)->update([
            'rejection_reasons' => $event->reasons,
            'rejected_by' => $event->rejectedBy,
            'rejected_at' => $event->rejectedAt,
            'status' => 'rejected',
        ]);
    }
}