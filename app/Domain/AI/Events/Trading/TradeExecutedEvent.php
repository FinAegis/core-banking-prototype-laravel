<?php

declare(strict_types=1);

namespace App\Domain\AI\Events\Trading;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Trade Executed Event.
 *
 * Emitted when a trade is successfully executed.
 */
class TradeExecutedEvent extends ShouldBeStored
{
    public function __construct(
        public readonly string $conversationId,
        public readonly string $tradeId,
        public readonly array $strategy,
        public readonly array $execution
    ) {
    }

    /**
     * Get event metadata.
     */
    public function eventMetadata(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'trade_id'        => $this->tradeId,
            'action'          => $this->strategy['action'] ?? 'unknown',
            'symbol'          => $this->strategy['symbol'] ?? 'unknown',
            'executed_price'  => $this->execution['executed_price'] ?? 0,
            'executed_amount' => $this->execution['executed_amount'] ?? 0,
            'fee'             => $this->execution['fee'] ?? 0,
            'timestamp'       => $this->execution['timestamp'] ?? now()->toIso8601String(),
        ];
    }
}
