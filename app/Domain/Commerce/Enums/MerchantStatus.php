<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Enums;

/**
 * Status of a merchant in the onboarding and lifecycle process.
 */
enum MerchantStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::PENDING      => 'Pending',
            self::UNDER_REVIEW => 'Under Review',
            self::APPROVED     => 'Approved',
            self::ACTIVE       => 'Active',
            self::SUSPENDED    => 'Suspended',
            self::TERMINATED   => 'Terminated',
        };
    }

    public function canAcceptPayments(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canTransitionTo(MerchantStatus $newStatus): bool
    {
        return match ($this) {
            self::PENDING      => in_array($newStatus, [self::UNDER_REVIEW, self::TERMINATED]),
            self::UNDER_REVIEW => in_array($newStatus, [self::APPROVED, self::TERMINATED]),
            self::APPROVED     => in_array($newStatus, [self::ACTIVE, self::TERMINATED]),
            self::ACTIVE       => in_array($newStatus, [self::SUSPENDED, self::TERMINATED]),
            self::SUSPENDED    => in_array($newStatus, [self::ACTIVE, self::TERMINATED]),
            self::TERMINATED   => false,
        };
    }

    /**
     * @return array<MerchantStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING      => [self::UNDER_REVIEW, self::TERMINATED],
            self::UNDER_REVIEW => [self::APPROVED, self::TERMINATED],
            self::APPROVED     => [self::ACTIVE, self::TERMINATED],
            self::ACTIVE       => [self::SUSPENDED, self::TERMINATED],
            self::SUSPENDED    => [self::ACTIVE, self::TERMINATED],
            self::TERMINATED   => [],
        };
    }
}
