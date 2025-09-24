<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use App\Domain\AgentProtocol\Enums\KycVerificationStatus;
use App\Domain\AgentProtocol\Events\AgentKycDocumentsSubmitted;
use App\Domain\AgentProtocol\Events\AgentKycInitiated;
use App\Domain\AgentProtocol\Events\AgentKycRejected;
use App\Domain\AgentProtocol\Events\AgentKycRequiresReview;
use App\Domain\AgentProtocol\Events\AgentKycVerified;
use App\Domain\AgentProtocol\Events\AgentTransactionLimitExceeded;
use App\Domain\AgentProtocol\Events\AgentTransactionLimitReset;
use App\Domain\AgentProtocol\Events\AgentTransactionLimitSet;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use Carbon\Carbon;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class AgentComplianceAggregate extends AggregateRoot
{
    protected string $agentId = '';

    protected KycVerificationStatus $kycStatus = KycVerificationStatus::PENDING;

    protected KycVerificationLevel $verificationLevel = KycVerificationLevel::BASIC;

    protected array $documents = [];

    protected array $verificationResults = [];

    protected float $dailyTransactionLimit = 0.0;

    protected float $weeklyTransactionLimit = 0.0;

    protected float $monthlyTransactionLimit = 0.0;

    protected float $dailyTransactionTotal = 0.0;

    protected float $weeklyTransactionTotal = 0.0;

    protected float $monthlyTransactionTotal = 0.0;

    protected ?Carbon $lastLimitReset = null;

    protected ?Carbon $kycVerifiedAt = null;

    protected ?Carbon $kycExpiresAt = null;

    protected int $riskScore = 0;

    protected array $complianceFlags = [];

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    /**
     * Initiate KYC process for agent.
     */
    public static function initiateKyc(
        string $agentId,
        string $agentDid,
        KycVerificationLevel $level,
        array $requiredDocuments = []
    ): self {
        $aggregate = new self();
        $aggregate->recordThat(new AgentKycInitiated(
            agentId: $agentId,
            agentDid: $agentDid,
            verificationLevel: $level,
            requiredDocuments: $requiredDocuments,
            initiatedAt: now()
        ));

        return $aggregate;
    }

    /**
     * Submit KYC documents for verification.
     */
    public function submitDocuments(array $documents): self
    {
        if ($this->kycStatus === KycVerificationStatus::VERIFIED) {
            throw new InvalidArgumentException('KYC already verified for this agent');
        }

        $this->recordThat(new AgentKycDocumentsSubmitted(
            agentId: $this->agentId,
            documents: $documents,
            submittedAt: now()
        ));

        return $this;
    }

    /**
     * Verify KYC with results.
     */
    public function verifyKyc(
        array $verificationResults,
        int $riskScore,
        Carbon $expiresAt,
        array $complianceFlags = []
    ): self {
        if ($this->kycStatus === KycVerificationStatus::VERIFIED) {
            throw new InvalidArgumentException('KYC already verified');
        }

        // Check if all required verifications passed
        $allPassed = collect($verificationResults)->every(fn ($result) => $result['status'] === 'passed');

        if (! $allPassed) {
            $this->recordThat(new AgentKycRejected(
                agentId: $this->agentId,
                reason: 'Verification checks failed',
                failedChecks: collect($verificationResults)
                    ->filter(fn ($result) => $result['status'] !== 'passed')
                    ->keys()
                    ->toArray(),
                rejectedAt: now()
            ));

            return $this;
        }

        // Check risk score threshold based on verification level
        $maxRiskScore = match ($this->verificationLevel) {
            KycVerificationLevel::BASIC    => 70,
            KycVerificationLevel::ENHANCED => 50,
            KycVerificationLevel::FULL     => 30,
        };

        if ($riskScore > $maxRiskScore) {
            $this->recordThat(new AgentKycRequiresReview(
                agentId: $this->agentId,
                riskScore: $riskScore,
                reason: 'Risk score exceeds threshold for verification level',
                reviewRequiredAt: now()
            ));

            return $this;
        }

        $this->recordThat(new AgentKycVerified(
            agentId: $this->agentId,
            verificationLevel: $this->verificationLevel,
            verificationResults: $verificationResults,
            riskScore: $riskScore,
            expiresAt: $expiresAt,
            complianceFlags: $complianceFlags,
            verifiedAt: now()
        ));

        // Set initial transaction limits based on verification level and risk score
        $this->setTransactionLimits($this->calculateTransactionLimits($riskScore));

        return $this;
    }

    /**
     * Reject KYC with reason.
     */
    public function rejectKyc(string $reason, array $failedChecks = []): self
    {
        if ($this->kycStatus === KycVerificationStatus::VERIFIED) {
            throw new InvalidArgumentException('Cannot reject already verified KYC');
        }

        $this->recordThat(new AgentKycRejected(
            agentId: $this->agentId,
            reason: $reason,
            failedChecks: $failedChecks,
            rejectedAt: now()
        ));

        return $this;
    }

    /**
     * Set transaction limits for agent.
     */
    public function setTransactionLimits(array $limits): self
    {
        $this->recordThat(new AgentTransactionLimitSet(
            agentId: $this->agentId,
            dailyLimit: $limits['daily'] ?? $this->dailyTransactionLimit,
            weeklyLimit: $limits['weekly'] ?? $this->weeklyTransactionLimit,
            monthlyLimit: $limits['monthly'] ?? $this->monthlyTransactionLimit,
            effectiveAt: now()
        ));

        return $this;
    }

    /**
     * Check if transaction is within limits.
     */
    public function checkTransactionLimit(float $amount, string $period = 'daily'): bool
    {
        return match ($period) {
            'daily'   => ($this->dailyTransactionTotal + $amount) <= $this->dailyTransactionLimit,
            'weekly'  => ($this->weeklyTransactionTotal + $amount) <= $this->weeklyTransactionLimit,
            'monthly' => ($this->monthlyTransactionTotal + $amount) <= $this->monthlyTransactionLimit,
            default   => throw new InvalidArgumentException("Invalid period: {$period}")
        };
    }

    /**
     * Record transaction limit exceeded event.
     */
    public function recordLimitExceeded(float $amount, string $period): self
    {
        $this->recordThat(new AgentTransactionLimitExceeded(
            agentId: $this->agentId,
            amount: $amount,
            period: $period,
            currentTotal: match ($period) {
                'daily'   => $this->dailyTransactionTotal,
                'weekly'  => $this->weeklyTransactionTotal,
                'monthly' => $this->monthlyTransactionTotal,
                default   => 0.0
            },
            limit: match ($period) {
                'daily'   => $this->dailyTransactionLimit,
                'weekly'  => $this->weeklyTransactionLimit,
                'monthly' => $this->monthlyTransactionLimit,
                default   => 0.0
            },
            exceededAt: now()
        ));

        return $this;
    }

    /**
     * Reset transaction limits (e.g., daily reset).
     */
    public function resetTransactionLimits(string $period): self
    {
        $this->recordThat(new AgentTransactionLimitReset(
            agentId: $this->agentId,
            period: $period,
            resetAt: now()
        ));

        return $this;
    }

    /**
     * Calculate transaction limits based on risk score and verification level.
     */
    protected function calculateTransactionLimits(int $riskScore): array
    {
        // Base limits by verification level
        $baseLimits = match ($this->verificationLevel) {
            KycVerificationLevel::BASIC => [
                'daily'   => 1000,
                'weekly'  => 5000,
                'monthly' => 10000,
            ],
            KycVerificationLevel::ENHANCED => [
                'daily'   => 5000,
                'weekly'  => 25000,
                'monthly' => 50000,
            ],
            KycVerificationLevel::FULL => [
                'daily'   => 10000,
                'weekly'  => 50000,
                'monthly' => 100000,
            ],
        };

        // Adjust based on risk score
        $multiplier = match (true) {
            $riskScore <= 20 => 1.5,    // Low risk - increase limits
            $riskScore <= 40 => 1.0,    // Medium risk - standard limits
            $riskScore <= 60 => 0.75,   // Medium-high risk - reduce limits
            default          => 0.5,              // High risk - significantly reduce limits
        };

        return [
            'daily'   => $baseLimits['daily'] * $multiplier,
            'weekly'  => $baseLimits['weekly'] * $multiplier,
            'monthly' => $baseLimits['monthly'] * $multiplier,
        ];
    }

    // Event handlers
    protected function applyAgentKycInitiated(AgentKycInitiated $event): void
    {
        $this->agentId = $event->agentId;
        $this->verificationLevel = $event->verificationLevel;
        $this->kycStatus = KycVerificationStatus::PENDING;
    }

    protected function applyAgentKycDocumentsSubmitted(AgentKycDocumentsSubmitted $event): void
    {
        $this->documents = array_merge($this->documents, $event->documents);
        $this->kycStatus = KycVerificationStatus::IN_REVIEW;
    }

    protected function applyAgentKycVerified(AgentKycVerified $event): void
    {
        $this->kycStatus = KycVerificationStatus::VERIFIED;
        $this->verificationResults = $event->verificationResults;
        $this->riskScore = $event->riskScore;
        $this->kycVerifiedAt = $event->verifiedAt;
        $this->kycExpiresAt = $event->expiresAt;
        $this->complianceFlags = $event->complianceFlags;
    }

    protected function applyAgentKycRejected(AgentKycRejected $event): void
    {
        $this->kycStatus = KycVerificationStatus::REJECTED;
    }

    protected function applyAgentKycRequiresReview(AgentKycRequiresReview $event): void
    {
        $this->kycStatus = KycVerificationStatus::REQUIRES_REVIEW;
        $this->riskScore = $event->riskScore;
    }

    protected function applyAgentTransactionLimitSet(AgentTransactionLimitSet $event): void
    {
        $this->dailyTransactionLimit = $event->dailyLimit;
        $this->weeklyTransactionLimit = $event->weeklyLimit;
        $this->monthlyTransactionLimit = $event->monthlyLimit;
    }

    protected function applyAgentTransactionLimitExceeded(AgentTransactionLimitExceeded $event): void
    {
        // Log the exceeded limit event (handled by projectors)
    }

    protected function applyAgentTransactionLimitReset(AgentTransactionLimitReset $event): void
    {
        switch ($event->period) {
            case 'daily':
                $this->dailyTransactionTotal = 0.0;
                break;
            case 'weekly':
                $this->weeklyTransactionTotal = 0.0;
                break;
            case 'monthly':
                $this->monthlyTransactionTotal = 0.0;
                break;
        }
        $this->lastLimitReset = $event->resetAt;
    }

    // Getters
    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getKycStatus(): KycVerificationStatus
    {
        return $this->kycStatus;
    }

    public function getVerificationLevel(): KycVerificationLevel
    {
        return $this->verificationLevel;
    }

    public function getRiskScore(): int
    {
        return $this->riskScore;
    }

    public function isKycVerified(): bool
    {
        return $this->kycStatus === KycVerificationStatus::VERIFIED
            && $this->kycExpiresAt
            && $this->kycExpiresAt->isFuture();
    }

    public function getDailyTransactionLimit(): float
    {
        return $this->dailyTransactionLimit;
    }

    public function getWeeklyTransactionLimit(): float
    {
        return $this->weeklyTransactionLimit;
    }

    public function getMonthlyTransactionLimit(): float
    {
        return $this->monthlyTransactionLimit;
    }
}
