<?php

namespace App\Domain\Exchange\ValueObjects;

class OrderMatchingInput
{
    public function __construct(
        public readonly string $orderId,
        public readonly ?int $maxIterations = 100
    ) {}
}