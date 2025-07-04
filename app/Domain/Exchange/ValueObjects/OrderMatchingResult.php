<?php

namespace App\Domain\Exchange\ValueObjects;

class OrderMatchingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly string $orderId,
        public readonly ?string $status = null,
        public readonly ?string $filledAmount = null,
        public readonly array $trades = []
    ) {}
}