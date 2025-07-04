<?php

namespace App\Domain\Stablecoin\ValueObjects;

use Carbon\Carbon;

class PriceData
{
    public function __construct(
        public readonly string $base,
        public readonly string $quote,
        public readonly string $price,
        public readonly string $source,
        public readonly Carbon $timestamp,
        public readonly ?string $volume24h = null,
        public readonly ?string $changePercent24h = null,
        public readonly ?array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'quote' => $this->quote,
            'price' => $this->price,
            'source' => $this->source,
            'timestamp' => $this->timestamp->toIso8601String(),
            'volume_24h' => $this->volume24h,
            'change_percent_24h' => $this->changePercent24h,
            'metadata' => $this->metadata,
        ];
    }
    
    public function isStale(int $maxAgeSeconds = 300): bool
    {
        return $this->timestamp->diffInSeconds(now()) > $maxAgeSeconds;
    }
}