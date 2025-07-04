<?php

namespace App\Domain\Stablecoin\ValueObjects;

use Carbon\Carbon;

class AggregatedPrice
{
    public function __construct(
        public readonly string $base,
        public readonly string $quote,
        public readonly string $price,
        public readonly array $sources,
        public readonly string $aggregationMethod,
        public readonly Carbon $timestamp,
        public readonly float $confidence = 1.0,
        public readonly ?array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'quote' => $this->quote,
            'price' => $this->price,
            'sources' => $this->sources,
            'aggregation_method' => $this->aggregationMethod,
            'timestamp' => $this->timestamp->toIso8601String(),
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
        ];
    }
    
    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.8;
    }
    
    public function getSourceCount(): int
    {
        return count($this->sources);
    }
}