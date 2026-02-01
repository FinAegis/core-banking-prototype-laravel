<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use DateTimeInterface;

/**
 * Result object for Proof of Innocence verification.
 */
final readonly class ProofOfInnocenceResult
{
    public function __construct(
        public bool $valid,
        public ?string $reason = null,
        public ?DateTimeInterface $validUntil = null,
    ) {
    }
}
