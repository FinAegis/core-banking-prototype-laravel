<?php

declare(strict_types=1);

namespace App\Domain\RegTech\Enums;

enum Jurisdiction: string
{
    case US = 'US';
    case EU = 'EU';
    case UK = 'UK';
    case SG = 'SG';

    public function name(): string
    {
        return match ($this) {
            self::US => 'United States',
            self::EU => 'European Union',
            self::UK => 'United Kingdom',
            self::SG => 'Singapore',
        };
    }

    public function currency(): string
    {
        return match ($this) {
            self::US => 'USD',
            self::EU => 'EUR',
            self::UK => 'GBP',
            self::SG => 'SGD',
        };
    }

    public function timezone(): string
    {
        return match ($this) {
            self::US => 'America/New_York',
            self::EU => 'Europe/Paris',
            self::UK => 'Europe/London',
            self::SG => 'Asia/Singapore',
        };
    }

    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
