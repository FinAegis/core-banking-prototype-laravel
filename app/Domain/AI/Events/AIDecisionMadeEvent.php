<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class AIDecisionMadeEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $agentType,
        public string $decision,
        public array $reasoning,
        public float $confidence,
        public bool $requiresApproval,
        public ?string $userId = null,
        public ?string $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }

    public function tags(): array
    {
        return [
            'ai-decision',
            "agent:{$this->agentType}",
            "conversation:{$this->conversationId}",
        ];
    }

    public function getConfidenceLevel(): string
    {
        return match (true) {
            $this->confidence >= 0.95 => 'very_high',
            $this->confidence >= 0.85 => 'high',
            $this->confidence >= 0.70 => 'medium',
            $this->confidence >= 0.50 => 'low',
            default                   => 'very_low',
        };
    }

    public function shouldTriggerAlert(): bool
    {
        return $this->requiresApproval || $this->confidence < 0.70;
    }

    public function getAuditData(): array
    {
        return [
            'conversation_id'   => $this->conversationId,
            'agent_type'        => $this->agentType,
            'decision'          => $this->decision,
            'reasoning'         => $this->reasoning,
            'confidence'        => $this->confidence,
            'confidence_level'  => $this->getConfidenceLevel(),
            'requires_approval' => $this->requiresApproval,
            'user_id'           => $this->userId,
            'timestamp'         => $this->timestamp,
        ];
    }
}
