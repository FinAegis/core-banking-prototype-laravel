<?php

declare(strict_types=1);

namespace App\Domain\MachinePay\Enums;

/**
 * Lifecycle states for an MPP payment challenge.
 */
enum ChallengeStatus: string
{
    case ISSUED = 'issued';
    case PENDING_PAYMENT = 'pending_payment';
    case CREDENTIAL_RECEIVED = 'credential_received';
    case SETTLED = 'settled';
    case EXPIRED = 'expired';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED              => 'Issued',
            self::PENDING_PAYMENT     => 'Pending Payment',
            self::CREDENTIAL_RECEIVED => 'Credential Received',
            self::SETTLED             => 'Settled',
            self::EXPIRED             => 'Expired',
            self::FAILED              => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::SETTLED, self::EXPIRED, self::FAILED => true,
            default                                    => false,
        };
    }
}
