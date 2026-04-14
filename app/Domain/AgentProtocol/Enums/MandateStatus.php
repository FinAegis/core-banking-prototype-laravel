<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Enums;

/**
 * AP2 Mandate lifecycle states.
 */
enum MandateStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case ACCEPTED = 'accepted';
    case EXECUTED = 'executed';
    case COMPLETED = 'completed';
    case REVOKED = 'revoked';
    case DISPUTED = 'disputed';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => 'Draft',
            self::ISSUED    => 'Issued',
            self::ACCEPTED  => 'Accepted',
            self::EXECUTED  => 'Executed',
            self::COMPLETED => 'Completed',
            self::REVOKED   => 'Revoked',
            self::DISPUTED  => 'Disputed',
            self::EXPIRED   => 'Expired',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::REVOKED, self::EXPIRED => true,
            default                                       => false,
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::ISSUED, self::ACCEPTED, self::EXECUTED => true,
            default                                      => false,
        };
    }
}
