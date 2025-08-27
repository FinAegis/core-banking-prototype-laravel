<?php

/**
 * AML Screening Aggregate for event sourcing.
 */

namespace App\Domain\Compliance\Aggregates;

use App\Domain\Compliance\Events\AmlScreeningCompleted;
use App\Domain\Compliance\Events\AmlScreeningMatchStatusUpdated;
use App\Domain\Compliance\Events\AmlScreeningResultsRecorded;
use App\Domain\Compliance\Events\AmlScreeningReviewed;
use App\Domain\Compliance\Events\AmlScreeningStarted;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

/**
 * AML Screening Aggregate handles anti-money laundering screening processes
 * using event sourcing patterns.
 */
class AmlScreeningAggregate extends AggregateRoot
{
    private string $entityId;

    private string $entityType;

    private string $screeningNumber;

    private string $type;

    private string $status = 'pending';

    private string $provider;

    private ?string $providerReference = null;

    private array $searchParameters = [];

    private array $results = [];

    private int $totalMatches = 0;

    private int $confirmedMatches = 0;

    private int $falsePositives = 0;

    private ?string $overallRisk = null;

    private ?string $reviewedBy = null;

    private ?string $reviewDecision = null;

    private ?string $reviewNotes = null;

    /**
     * Start a new AML screening.
     *
     * @param  string  $entityId  Entity identifier
     * @param  string  $entityType  Entity type (user, company, etc.)
     * @param  string  $screeningNumber  Unique screening number
     * @param  string  $type  Screening type (sanctions, pep, etc.)
     * @param  string  $provider  Screening provider name
     * @param  array  $searchParameters  Search parameters
     * @param  string|null  $providerReference  Provider's reference ID
     */
    public function startScreening(
        string $entityId,
        string $entityType,
        string $screeningNumber,
        string $type,
        string $provider,
        array $searchParameters,
        ?string $providerReference = null
    ): self {
        $this->recordThat(
            new AmlScreeningStarted(
                $entityId,
                $entityType,
                $screeningNumber,
                $type,
                $provider,
                $searchParameters,
                $providerReference
            )
        );

        return $this;
    }

    /**
     * Record screening results from provider.
     *
     * @param  array  $sanctionsResults  Sanctions screening results
     * @param  array  $pepResults  PEP screening results
     * @param  array  $adverseMediaResults  Adverse media results
     * @param  array  $otherResults  Other screening results
     * @param  int  $totalMatches  Total number of matches
     * @param  string  $overallRisk  Overall risk level
     * @param  array  $listsChecked  Lists checked during screening
     * @param  array|null  $apiResponse  Raw API response
     */
    public function recordResults(
        array $sanctionsResults,
        array $pepResults,
        array $adverseMediaResults,
        array $otherResults,
        int $totalMatches,
        string $overallRisk,
        array $listsChecked,
        ?array $apiResponse = null
    ): self {
        $this->recordThat(
            new AmlScreeningResultsRecorded(
                $sanctionsResults,
                $pepResults,
                $adverseMediaResults,
                $otherResults,
                $totalMatches,
                $overallRisk,
                $listsChecked,
                $apiResponse
            )
        );

        return $this;
    }

    /**
     * Update match status (confirm, dismiss, or investigate).
     *
     * @param  string  $matchId  Match identifier
     * @param  string  $action  Action to take (confirm, dismiss, investigate)
     * @param  array  $details  Additional details about the action
     * @param  string|null  $reason  Reason for the action
     *
     * @throws InvalidArgumentException
     */
    public function updateMatchStatus(
        string $matchId,
        string $action,
        array $details,
        ?string $reason = null
    ): self {
        if (! in_array($action, ['confirm', 'dismiss', 'investigate'])) {
            throw new InvalidArgumentException(
                'Invalid action. Must be confirm, dismiss, or investigate.'
            );
        }

        $this->recordThat(
            new AmlScreeningMatchStatusUpdated(
                $matchId,
                $action,
                $details,
                $reason
            )
        );

        return $this;
    }

    /**
     * Complete the screening.
     *
     * @param  string  $finalStatus  Final status (completed or failed)
     * @param  float|null  $processingTime  Processing time in seconds
     *
     * @throws InvalidArgumentException
     */
    public function completeScreening(
        string $finalStatus,
        ?float $processingTime = null
    ): self {
        if (! in_array($finalStatus, ['completed', 'failed'])) {
            throw new InvalidArgumentException(
                'Invalid status. Must be completed or failed.'
            );
        }

        $this->recordThat(
            new AmlScreeningCompleted(
                $finalStatus,
                $processingTime
            )
        );

        return $this;
    }

    /**
     * Review screening results.
     *
     * @param  string  $reviewedBy  Reviewer identifier
     * @param  string  $decision  Review decision (clear, escalate, block)
     * @param  string  $notes  Review notes
     *
     * @throws InvalidArgumentException
     */
    public function reviewScreening(
        string $reviewedBy,
        string $decision,
        string $notes
    ): self {
        if (! in_array($decision, ['clear', 'escalate', 'block'])) {
            throw new InvalidArgumentException(
                'Invalid decision. Must be clear, escalate, or block.'
            );
        }

        $this->recordThat(
            new AmlScreeningReviewed(
                $reviewedBy,
                $decision,
                $notes
            )
        );

        return $this;
    }

    /**
     * Apply event handlers.
     */
    protected function applyAmlScreeningStarted(AmlScreeningStarted $event): void
    {
        $this->entityId = $event->entityId;
        $this->entityType = $event->entityType;
        $this->screeningNumber = $event->screeningNumber;
        $this->type = $event->type;
        $this->provider = $event->provider;
        $this->searchParameters = $event->searchParameters;
        $this->providerReference = $event->providerReference;
        $this->status = 'in_progress';
    }

    /**
     * Apply AML screening results recorded event.
     */
    protected function applyAmlScreeningResultsRecorded(
        AmlScreeningResultsRecorded $event
    ): void {
        $this->results = [
            'sanctions'     => $event->sanctionsResults,
            'pep'           => $event->pepResults,
            'adverse_media' => $event->adverseMediaResults,
            'other'         => $event->otherResults,
        ];
        $this->totalMatches = $event->totalMatches;
        $this->overallRisk = $event->overallRisk;
    }

    /**
     * Apply AML screening match status updated event.
     */
    protected function applyAmlScreeningMatchStatusUpdated(
        AmlScreeningMatchStatusUpdated $event
    ): void {
        if ($event->action === 'confirm') {
            $this->confirmedMatches++;
        } elseif ($event->action === 'dismiss') {
            $this->falsePositives++;
        }
    }

    /**
     * Apply AML screening completed event.
     */
    protected function applyAmlScreeningCompleted(AmlScreeningCompleted $event): void
    {
        $this->status = $event->finalStatus;
    }

    /**
     * Apply AML screening reviewed event.
     */
    protected function applyAmlScreeningReviewed(AmlScreeningReviewed $event): void
    {
        $this->reviewedBy = $event->reviewedBy;
        $this->reviewDecision = $event->decision;
        $this->reviewNotes = $event->notes;
    }

    /**
     * Getters for aggregate state.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get total matches count.
     */
    public function getTotalMatches(): int
    {
        return $this->totalMatches;
    }

    /**
     * Get confirmed matches count.
     */
    public function getConfirmedMatches(): int
    {
        return $this->confirmedMatches;
    }

    /**
     * Get false positives count.
     */
    public function getFalsePositives(): int
    {
        return $this->falsePositives;
    }

    /**
     * Get overall risk level.
     */
    public function getOverallRisk(): ?string
    {
        return $this->overallRisk;
    }

    /**
     * Check if screening has been reviewed.
     */
    public function isReviewed(): bool
    {
        return $this->reviewedBy !== null;
    }

    /**
     * Check if screening requires review.
     */
    public function requiresReview(): bool
    {
        return $this->totalMatches > 0 && ! $this->isReviewed();
    }

    /**
     * Get entity ID.
     */
    public function getEntityId(): string
    {
        return $this->entityId;
    }

    /**
     * Get entity type.
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * Get screening number.
     */
    public function getScreeningNumber(): string
    {
        return $this->screeningNumber;
    }

    /**
     * Get screening type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get provider.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get provider reference.
     */
    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    /**
     * Get search parameters.
     */
    public function getSearchParameters(): array
    {
        return $this->searchParameters;
    }

    /**
     * Get results.
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get review decision.
     */
    public function getReviewDecision(): ?string
    {
        return $this->reviewDecision;
    }

    /**
     * Get review notes.
     */
    public function getReviewNotes(): ?string
    {
        return $this->reviewNotes;
    }
}
