<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Enums\MerchantStatus;
use App\Domain\Commerce\Events\MerchantOnboarded;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service for merchant onboarding and lifecycle management.
 *
 * Handles the complete merchant onboarding flow:
 * - Application submission
 * - KYB (Know Your Business) verification
 * - Risk assessment
 * - Approval/rejection
 * - Status management
 */
class MerchantOnboardingService
{
    /**
     * In-memory merchant storage (in production, this would be persisted).
     *
     * @var array<string, array<string, mixed>>
     */
    private array $merchants = [];

    /**
     * Submit a new merchant application.
     *
     * @param array<string, mixed> $businessDetails
     *
     * @return array{merchant_id: string, status: string}
     */
    public function submitApplication(
        string $businessName,
        string $businessType,
        string $country,
        string $contactEmail,
        array $businessDetails = [],
    ): array {
        $merchantId = Str::uuid()->toString();
        $now = new DateTimeImmutable();

        $this->merchants[$merchantId] = [
            'merchant_id'      => $merchantId,
            'business_name'    => $businessName,
            'business_type'    => $businessType,
            'country'          => $country,
            'contact_email'    => $contactEmail,
            'business_details' => $businessDetails,
            'status'           => MerchantStatus::PENDING->value,
            'created_at'       => $now->format('c'),
            'updated_at'       => $now->format('c'),
            'status_history'   => [
                [
                    'status'     => MerchantStatus::PENDING->value,
                    'changed_at' => $now->format('c'),
                    'reason'     => 'Application submitted',
                ],
            ],
        ];

        return [
            'merchant_id' => $merchantId,
            'status'      => MerchantStatus::PENDING->value,
        ];
    }

    /**
     * Start the review process for a merchant.
     */
    public function startReview(string $merchantId, string $reviewerId): void
    {
        $this->updateMerchantStatus(
            merchantId: $merchantId,
            newStatus: MerchantStatus::UNDER_REVIEW,
            reason: "Review started by {$reviewerId}",
        );
    }

    /**
     * Approve a merchant application.
     *
     * @param array<string, mixed> $approvalDetails
     */
    public function approve(
        string $merchantId,
        string $approverId,
        array $approvalDetails = [],
    ): void {
        $merchant = $this->getMerchant($merchantId);

        $this->updateMerchantStatus(
            merchantId: $merchantId,
            newStatus: MerchantStatus::APPROVED,
            reason: "Approved by {$approverId}",
            additionalData: ['approval_details' => $approvalDetails],
        );
    }

    /**
     * Activate a merchant (after approval and setup completion).
     */
    public function activate(string $merchantId): void
    {
        $merchant = $this->getMerchant($merchantId);

        $this->updateMerchantStatus(
            merchantId: $merchantId,
            newStatus: MerchantStatus::ACTIVE,
            reason: 'Merchant setup completed',
        );

        Event::dispatch(new MerchantOnboarded(
            merchantId: $merchantId,
            merchantName: $merchant['business_name'],
            status: MerchantStatus::ACTIVE,
            onboardedAt: new DateTimeImmutable(),
        ));
    }

    /**
     * Suspend a merchant.
     */
    public function suspend(string $merchantId, string $reason): void
    {
        $this->updateMerchantStatus(
            merchantId: $merchantId,
            newStatus: MerchantStatus::SUSPENDED,
            reason: $reason,
        );
    }

    /**
     * Reactivate a suspended merchant.
     */
    public function reactivate(string $merchantId, string $reason): void
    {
        $this->updateMerchantStatus(
            merchantId: $merchantId,
            newStatus: MerchantStatus::ACTIVE,
            reason: $reason,
        );
    }

    /**
     * Terminate a merchant relationship.
     */
    public function terminate(string $merchantId, string $reason): void
    {
        $this->updateMerchantStatus(
            merchantId: $merchantId,
            newStatus: MerchantStatus::TERMINATED,
            reason: $reason,
        );
    }

    /**
     * Get merchant details.
     *
     * @return array<string, mixed>
     */
    public function getMerchant(string $merchantId): array
    {
        if (! isset($this->merchants[$merchantId])) {
            throw new InvalidArgumentException("Merchant not found: {$merchantId}");
        }

        return $this->merchants[$merchantId];
    }

    /**
     * Get merchant status.
     */
    public function getMerchantStatus(string $merchantId): MerchantStatus
    {
        $merchant = $this->getMerchant($merchantId);

        return MerchantStatus::from($merchant['status']);
    }

    /**
     * Check if merchant can accept payments.
     */
    public function canAcceptPayments(string $merchantId): bool
    {
        return $this->getMerchantStatus($merchantId)->canAcceptPayments();
    }

    /**
     * Get merchant status history.
     *
     * @return array<array{status: string, changed_at: string, reason: string}>
     */
    public function getStatusHistory(string $merchantId): array
    {
        $merchant = $this->getMerchant($merchantId);

        return $merchant['status_history'];
    }

    /**
     * Perform risk assessment on a merchant.
     *
     * @return array{risk_score: float, risk_factors: array<string>, recommendation: string}
     */
    public function assessRisk(string $merchantId): array
    {
        $merchant = $this->getMerchant($merchantId);

        // Demo risk assessment logic
        $riskFactors = [];
        $riskScore = 0.0;

        // High-risk business types
        $highRiskTypes = ['gambling', 'crypto', 'adult', 'weapons'];
        if (in_array(strtolower($merchant['business_type']), $highRiskTypes, true)) {
            $riskFactors[] = 'High-risk business category';
            $riskScore += 0.3;
        }

        // High-risk countries
        $highRiskCountries = ['AF', 'KP', 'IR', 'SY'];
        if (in_array(strtoupper($merchant['country']), $highRiskCountries, true)) {
            $riskFactors[] = 'High-risk jurisdiction';
            $riskScore += 0.4;
        }

        // Determine recommendation
        $recommendation = match (true) {
            $riskScore >= 0.7 => 'reject',
            $riskScore >= 0.4 => 'enhanced_review',
            default           => 'approve',
        };

        return [
            'risk_score'     => min(1.0, $riskScore),
            'risk_factors'   => $riskFactors,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Update merchant status.
     *
     * @param array<string, mixed> $additionalData
     */
    private function updateMerchantStatus(
        string $merchantId,
        MerchantStatus $newStatus,
        string $reason,
        array $additionalData = [],
    ): void {
        $merchant = $this->getMerchant($merchantId);
        $currentStatus = MerchantStatus::from($merchant['status']);

        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw new RuntimeException(
                "Cannot transition from {$currentStatus->value} to {$newStatus->value}"
            );
        }

        $now = new DateTimeImmutable();

        $this->merchants[$merchantId]['status'] = $newStatus->value;
        $this->merchants[$merchantId]['updated_at'] = $now->format('c');

        $this->merchants[$merchantId]['status_history'][] = [
            'status'     => $newStatus->value,
            'changed_at' => $now->format('c'),
            'reason'     => $reason,
        ];

        foreach ($additionalData as $key => $value) {
            $this->merchants[$merchantId][$key] = $value;
        }
    }
}
