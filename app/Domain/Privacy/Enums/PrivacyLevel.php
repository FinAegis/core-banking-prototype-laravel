<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Enums;

/**
 * Privacy levels for credential disclosure.
 */
enum PrivacyLevel: string
{
    case FULL_DISCLOSURE = 'full_disclosure';
    case SELECTIVE_DISCLOSURE = 'selective_disclosure';
    case ZERO_KNOWLEDGE = 'zero_knowledge';
    case RANGE_PROOF = 'range_proof';

    public function label(): string
    {
        return match ($this) {
            self::FULL_DISCLOSURE      => 'Full Disclosure',
            self::SELECTIVE_DISCLOSURE => 'Selective Disclosure',
            self::ZERO_KNOWLEDGE       => 'Zero Knowledge Proof',
            self::RANGE_PROOF          => 'Range Proof',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FULL_DISCLOSURE      => 'All credential attributes are revealed',
            self::SELECTIVE_DISCLOSURE => 'Only selected attributes are revealed',
            self::ZERO_KNOWLEDGE       => 'No attributes revealed, only proof of claim',
            self::RANGE_PROOF          => 'Proves value falls within a range without revealing exact value',
        };
    }

    /**
     * Returns the privacy strength (higher = more private).
     */
    public function strength(): int
    {
        return match ($this) {
            self::FULL_DISCLOSURE      => 0,
            self::SELECTIVE_DISCLOSURE => 1,
            self::RANGE_PROOF          => 2,
            self::ZERO_KNOWLEDGE       => 3,
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
