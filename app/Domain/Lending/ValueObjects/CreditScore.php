<?php

namespace App\Domain\Lending\ValueObjects;

use InvalidArgumentException;

class CreditScore
{
    public function __construct(
        public readonly int $score,
        public readonly string $bureau,
        public readonly array $creditReport
    ) {
        if ($score < 300 || $score > 850) {
            throw new InvalidArgumentException('Credit score must be between 300 and 850');
        }
    }

    public function isExcellent(): bool
    {
        return $this->score >= 800;
    }

    public function isGood(): bool
    {
        return $this->score >= 700 && $this->score < 800;
    }

    public function isFair(): bool
    {
        return $this->score >= 600 && $this->score < 700;
    }

    public function isPoor(): bool
    {
        return $this->score < 600;
    }

    public function toArray(): array
    {
        return [
            'score'         => $this->score,
            'bureau'        => $this->bureau,
            'credit_report' => $this->creditReport,
        ];
    }
}
