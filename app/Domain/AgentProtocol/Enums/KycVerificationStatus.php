<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Enums;

enum KycVerificationStatus: string
{
    case PENDING = 'pending';
    case IN_REVIEW = 'in_review';
    case REQUIRES_REVIEW = 'requires_review';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING         => 'Pending Submission',
            self::IN_REVIEW       => 'Under Review',
            self::REQUIRES_REVIEW => 'Manual Review Required',
            self::VERIFIED        => 'Verified',
            self::REJECTED        => 'Rejected',
            self::EXPIRED         => 'Expired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING         => 'gray',
            self::IN_REVIEW       => 'blue',
            self::REQUIRES_REVIEW => 'yellow',
            self::VERIFIED        => 'green',
            self::REJECTED        => 'red',
            self::EXPIRED         => 'orange',
        };
    }

    public function canTransact(): bool
    {
        return $this === self::VERIFIED;
    }

    public function canResubmit(): bool
    {
        return in_array($this, [self::REJECTED, self::EXPIRED], true);
    }

    public function requiresAction(): bool
    {
        return in_array($this, [self::PENDING, self::REQUIRES_REVIEW], true);
    }
}
