<?php

declare(strict_types=1);

namespace App\Domain\AI\Events\Trading;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Strategy Generated Event.
 *
 * Emitted when trading strategy is generated.
 */
class StrategyGeneratedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $strategyType,
        public readonly array $strategyData
    ) {
    }

    /**
     * Get event metadata.
     */
    public function eventMetadata(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'strategy_type'   => $this->strategyType,
            'action'          => $this->strategyData['recommended']['action'] ?? 'unknown',
            'confidence'      => $this->strategyData['recommended']['confidence'] ?? 0,
            'position_size'   => $this->strategyData['recommended']['size'] ?? 0,
            'timestamp'       => $this->strategyData['timestamp'] ?? now()->toIso8601String(),
        ];
    }
}
