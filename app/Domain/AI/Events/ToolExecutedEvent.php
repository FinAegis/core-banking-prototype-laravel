<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ToolExecutedEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $toolName,
        public array $parameters,
        public array $result,
        public int $durationMs,
        public ?string $userId = null,
        public ?string $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }

    public function tags(): array
    {
        return [
            'mcp-tool',
            "tool:{$this->toolName}",
            "conversation:{$this->conversationId}",
        ];
    }

    public function wasSuccessful(): bool
    {
        return ! isset($this->result['error']);
    }

    public function getPerformanceCategory(): string
    {
        return match (true) {
            $this->durationMs < 100  => 'excellent',
            $this->durationMs < 500  => 'good',
            $this->durationMs < 1000 => 'acceptable',
            $this->durationMs < 3000 => 'slow',
            default                  => 'critical',
        };
    }

    public function getMetrics(): array
    {
        return [
            'tool_name'        => $this->toolName,
            'duration_ms'      => $this->durationMs,
            'performance'      => $this->getPerformanceCategory(),
            'success'          => $this->wasSuccessful(),
            'parameter_count'  => count($this->parameters),
            'has_user_context' => $this->userId !== null,
        ];
    }

    public function getAuditData(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'tool_name'       => $this->toolName,
            'parameters'      => $this->parameters,
            'result'          => $this->result,
            'duration_ms'     => $this->durationMs,
            'performance'     => $this->getPerformanceCategory(),
            'success'         => $this->wasSuccessful(),
            'user_id'         => $this->userId,
            'timestamp'       => $this->timestamp,
        ];
    }
}
