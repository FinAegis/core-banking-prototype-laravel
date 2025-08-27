<?php

namespace App\Domain\Lending\Aggregates;

use App\Domain\Lending\Events\LoanApplicationApproved;
use App\Domain\Lending\Events\LoanApplicationCreditCheckCompleted;
use App\Domain\Lending\Events\LoanApplicationRejected;
use App\Domain\Lending\Events\LoanApplicationRiskAssessmentCompleted;
use App\Domain\Lending\Events\LoanApplicationSubmitted;
use App\Domain\Lending\Events\LoanApplicationWithdrawn;
use App\Domain\Lending\Exceptions\LoanApplicationException;
use App\Domain\Lending\Repositories\LendingEventRepository;
use App\Domain\Lending\ValueObjects\CreditScore;
use App\Domain\Lending\ValueObjects\RiskRating;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class LoanApplication extends AggregateRoot
{
    private string $applicationId;

    private string $borrowerId;

    private string $requestedAmount;

    private int $termMonths;

    private string $purpose;

    private string $status = 'pending';

    private ?CreditScore $creditScore = null;

    private ?RiskRating $riskRating = null;

    private ?string $approvedAmount = null;

    private ?float $interestRate = null;

    private array $rejectionReasons = [];

    private array $documents = [];

    public static function submit(
        string $applicationId,
        string $borrowerId,
        string $requestedAmount,
        int $termMonths,
        string $purpose,
        array $borrowerInfo
    ): self {
        if (BigDecimal::of($requestedAmount)->isLessThanOrEqualTo(0)) {
            throw new LoanApplicationException('Requested amount must be greater than zero');
        }

        if ($termMonths < 1 || $termMonths > 360) {
            throw new LoanApplicationException('Term must be between 1 and 360 months');
        }

        $application = static::retrieve($applicationId);

        $application->recordThat(
            new LoanApplicationSubmitted(
                applicationId: $applicationId,
                borrowerId: $borrowerId,
                requestedAmount: $requestedAmount,
                termMonths: $termMonths,
                purpose: $purpose,
                borrowerInfo: $borrowerInfo,
                submittedAt: new DateTimeImmutable()
            )
        );

        return $application;
    }

    public function completeCreditCheck(
        int $score,
        string $bureau,
        array $creditReport,
        string $checkedBy
    ): self {
        if ($this->status !== 'pending') {
            throw new LoanApplicationException('Can only perform credit check on pending applications');
        }

        $creditScore = new CreditScore($score, $bureau, $creditReport);

        $this->recordThat(
            new LoanApplicationCreditCheckCompleted(
                applicationId: $this->applicationId,
                score: $score,
                bureau: $bureau,
                report: $creditReport,
                checkedBy: $checkedBy,
                checkedAt: new DateTimeImmutable()
            )
        );

        return $this;
    }

    public function completeRiskAssessment(
        string $rating,
        float $defaultProbability,
        array $riskFactors,
        string $assessedBy
    ): self {
        if ($this->status !== 'pending') {
            throw new LoanApplicationException('Can only assess risk on pending applications');
        }

        if (! $this->creditScore) {
            throw new LoanApplicationException('Credit check must be completed before risk assessment');
        }

        $riskRating = new RiskRating($rating, $defaultProbability, $riskFactors);

        $this->recordThat(
            new LoanApplicationRiskAssessmentCompleted(
                applicationId: $this->applicationId,
                rating: $rating,
                defaultProbability: $defaultProbability,
                riskFactors: $riskFactors,
                assessedBy: $assessedBy,
                assessedAt: new DateTimeImmutable()
            )
        );

        return $this;
    }

    public function approve(
        string $approvedAmount,
        float $interestRate,
        array $terms,
        string $approvedBy
    ): self {
        if ($this->status !== 'pending') {
            throw new LoanApplicationException('Can only approve pending applications');
        }

        if (! $this->creditScore || ! $this->riskRating) {
            throw new LoanApplicationException('Credit check and risk assessment must be completed');
        }

        if (BigDecimal::of($approvedAmount)->isGreaterThan($this->requestedAmount)) {
            throw new LoanApplicationException('Approved amount cannot exceed requested amount');
        }

        if ($interestRate < 0 || $interestRate > 100) {
            throw new LoanApplicationException('Interest rate must be between 0 and 100');
        }

        $this->recordThat(
            new LoanApplicationApproved(
                applicationId: $this->applicationId,
                approvedAmount: $approvedAmount,
                interestRate: $interestRate,
                terms: $terms,
                approvedBy: $approvedBy,
                approvedAt: new DateTimeImmutable()
            )
        );

        return $this;
    }

    public function reject(array $reasons, string $rejectedBy): self
    {
        if ($this->status !== 'pending') {
            throw new LoanApplicationException('Can only reject pending applications');
        }

        $this->recordThat(
            new LoanApplicationRejected(
                applicationId: $this->applicationId,
                reasons: $reasons,
                rejectedBy: $rejectedBy,
                rejectedAt: new DateTimeImmutable()
            )
        );

        return $this;
    }

    public function withdraw(string $reason, string $withdrawnBy): self
    {
        if (! in_array($this->status, ['pending', 'approved'])) {
            throw new LoanApplicationException('Cannot withdraw application in current status');
        }

        $this->recordThat(
            new LoanApplicationWithdrawn(
                applicationId: $this->applicationId,
                reason: $reason,
                withdrawnBy: $withdrawnBy,
                withdrawnAt: new DateTimeImmutable()
            )
        );

        return $this;
    }

    // Event handlers
    protected function applyLoanApplicationSubmitted(LoanApplicationSubmitted $event): void
    {
        $this->applicationId = $event->applicationId;
        $this->borrowerId = $event->borrowerId;
        $this->requestedAmount = $event->requestedAmount;
        $this->termMonths = $event->termMonths;
        $this->purpose = $event->purpose;
        $this->status = 'pending';
    }

    protected function applyLoanApplicationCreditCheckCompleted(LoanApplicationCreditCheckCompleted $event): void
    {
        $this->creditScore = new CreditScore($event->score, $event->bureau, $event->report);
    }

    protected function applyLoanApplicationRiskAssessmentCompleted(LoanApplicationRiskAssessmentCompleted $event): void
    {
        $this->riskRating = new RiskRating($event->rating, $event->defaultProbability, $event->riskFactors);
    }

    protected function applyLoanApplicationApproved(LoanApplicationApproved $event): void
    {
        $this->status = 'approved';
        $this->approvedAmount = $event->approvedAmount;
        $this->interestRate = $event->interestRate;
    }

    protected function applyLoanApplicationRejected(LoanApplicationRejected $event): void
    {
        $this->status = 'rejected';
        $this->rejectionReasons = $event->reasons;
    }

    protected function applyLoanApplicationWithdrawn(LoanApplicationWithdrawn $event): void
    {
        $this->status = 'withdrawn';
    }

    // Getters
    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    public function getBorrowerId(): string
    {
        return $this->borrowerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRequestedAmount(): string
    {
        return $this->requestedAmount;
    }

    public function getApprovedAmount(): ?string
    {
        return $this->approvedAmount;
    }

    public function getInterestRate(): ?float
    {
        return $this->interestRate;
    }

    public function getCreditScore(): ?CreditScore
    {
        return $this->creditScore;
    }

    public function getRiskRating(): ?RiskRating
    {
        return $this->riskRating;
    }

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(LendingEventRepository::class);
    }
}
