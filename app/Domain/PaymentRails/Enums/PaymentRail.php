<?php

declare(strict_types=1);

namespace App\Domain\PaymentRails\Enums;

enum PaymentRail: string
{
    case ACH = 'ach';
    case FEDWIRE = 'fedwire';
    case RTP = 'rtp';
    case FEDNOW = 'fednow';
    case SEPA = 'sepa';
    case SEPA_INSTANT = 'sepa_instant';
    case SWIFT = 'swift';

    public function label(): string
    {
        return match ($this) {
            self::ACH          => 'ACH',
            self::FEDWIRE      => 'Fedwire',
            self::RTP          => 'RTP',
            self::FEDNOW       => 'FedNow',
            self::SEPA         => 'SEPA',
            self::SEPA_INSTANT => 'SEPA Instant',
            self::SWIFT        => 'SWIFT',
        };
    }

    public function isInstant(): bool
    {
        return match ($this) {
            self::RTP, self::FEDNOW, self::SEPA_INSTANT => true,
            default                                     => false,
        };
    }

    public function maxAmount(): ?int
    {
        return match ($this) {
            self::RTP    => (int) config('payment_rails.rtp.max_amount', 100000000),
            self::FEDNOW => (int) config('payment_rails.fednow.max_amount', 50000000),
            default      => null,
        };
    }
}
