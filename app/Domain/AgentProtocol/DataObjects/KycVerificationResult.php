<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

use App\Domain\AgentProtocol\Enums\KycVerificationLevel;
use App\Domain\AgentProtocol\Enums\KycVerificationStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class KycVerificationResult extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly KycVerificationStatus $status,
        public readonly string $agentId,
        public readonly ?string $reason = null,
        public readonly ?KycVerificationLevel $verificationLevel = null,
        public readonly ?int $riskScore = null,
        public readonly ?Carbon $expiresAt = null,
        public readonly array $transactionLimits = [],
        public readonly array $failedChecks = [],
        public readonly array $complianceFlags = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'],
            status: KycVerificationStatus::from($data['status']),
            agentId: $data['agent_id'],
            reason: $data['reason'] ?? null,
            verificationLevel: isset($data['verification_level'])
                ? KycVerificationLevel::from($data['verification_level'])
                : null,
            riskScore: $data['risk_score'] ?? null,
            expiresAt: isset($data['expires_at'])
                ? Carbon::parse($data['expires_at'])
                : null,
            transactionLimits: $data['transaction_limits'] ?? [],
            failedChecks: $data['failed_checks'] ?? [],
            complianceFlags: $data['compliance_flags'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'success'            => $this->success,
            'status'             => $this->status->value,
            'agent_id'           => $this->agentId,
            'reason'             => $this->reason,
            'verification_level' => $this->verificationLevel?->value,
            'risk_score'         => $this->riskScore,
            'expires_at'         => $this->expiresAt?->toIso8601String(),
            'transaction_limits' => $this->transactionLimits,
            'failed_checks'      => $this->failedChecks,
            'compliance_flags'   => $this->complianceFlags,
        ];
    }

    public function isVerified(): bool
    {
        return $this->success && $this->status === KycVerificationStatus::VERIFIED;
    }

    public function requiresReview(): bool
    {
        return $this->status === KycVerificationStatus::REQUIRES_REVIEW;
    }

    public function isRejected(): bool
    {
        return $this->status === KycVerificationStatus::REJECTED;
    }

    public function isHighRisk(): bool
    {
        return $this->riskScore !== null && $this->riskScore > 70;
    }

    public function hasComplianceIssues(): bool
    {
        return ! empty($this->complianceFlags);
    }

    public function getDailyLimit(): float
    {
        return $this->transactionLimits['daily'] ?? 0.0;
    }

    public function getWeeklyLimit(): float
    {
        return $this->transactionLimits['weekly'] ?? 0.0;
    }

    public function getMonthlyLimit(): float
    {
        return $this->transactionLimits['monthly'] ?? 0.0;
    }
}
