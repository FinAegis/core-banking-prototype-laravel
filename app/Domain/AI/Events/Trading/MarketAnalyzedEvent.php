<?php

declare(strict_types=1);

namespace App\Domain\AI\Events\Trading;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Market Analyzed Event.
 *
 * Emitted when market analysis is completed.
 */
class MarketAnalyzedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $symbol,
        public readonly array $analysisResult
    ) {
    }

    /**
     * Get event metadata.
     */
    public function eventMetadata(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'symbol'          => $this->symbol,
            'indicators'      => array_keys($this->analysisResult['indicators'] ?? []),
            'pattern_count'   => count($this->analysisResult['patterns']['patterns'] ?? []),
            'sentiment'       => $this->analysisResult['sentiment']['overall'] ?? 'unknown',
            'timestamp'       => $this->analysisResult['timestamp'] ?? now()->toIso8601String(),
        ];
    }
}
