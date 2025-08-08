<?php

declare(strict_types=1);

namespace App\Domain\AI\Aggregates;

use App\Domain\AI\Events\AgentCreatedEvent;
use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\ConversationEndedEvent;
use App\Domain\AI\Events\ConversationStartedEvent;
use App\Domain\AI\Events\IntentClassifiedEvent;
use App\Domain\AI\Events\ToolExecutedEvent;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class AIInteractionAggregate extends AggregateRoot
{
    protected string $conversationId;

    protected string $agentType;

    protected ?string $userId = null;

    protected array $context = [];

    protected array $executedTools = [];

    protected bool $isActive = false;

    public function startConversation(
        string $conversationId,
        string $agentType,
        ?string $userId = null,
        array $initialContext = []
    ): self {
        $this->recordThat(new ConversationStartedEvent(
            $conversationId,
            $agentType,
            $userId,
            $initialContext
        ));

        return $this;
    }

    public function createAgent(string $agentId, string $agentType, array $capabilities): self
    {
        $this->recordThat(new AgentCreatedEvent(
            $this->conversationId,
            $agentId,
            $agentType,
            $capabilities
        ));

        return $this;
    }

    public function classifyIntent(string $query, string $intent, float $confidence): self
    {
        $this->recordThat(new IntentClassifiedEvent(
            $this->conversationId,
            $query,
            $intent,
            $confidence
        ));

        return $this;
    }

    public function makeDecision(
        string $decision,
        array $reasoning,
        float $confidence,
        bool $requiresApproval = false
    ): self {
        $this->recordThat(new AIDecisionMadeEvent(
            $this->conversationId,
            $this->agentType,
            $decision,
            $reasoning,
            $confidence,
            $requiresApproval,
            $this->userId
        ));

        return $this;
    }

    public function executeTool(
        string $toolName,
        array $parameters,
        ToolExecutionResult $result
    ): self {
        $this->recordThat(new ToolExecutedEvent(
            $this->conversationId,
            $toolName,
            $parameters,
            $result->toArray(),
            $result->getDurationMs(),
            $this->userId
        ));

        return $this;
    }

    public function endConversation(array $summary = []): self
    {
        $this->recordThat(new ConversationEndedEvent(
            $this->conversationId,
            $summary,
            count($this->executedTools)
        ));

        return $this;
    }

    // Event handlers
    protected function applyConversationStartedEvent(ConversationStartedEvent $event): void
    {
        $this->conversationId = $event->conversationId;
        $this->agentType = $event->agentType;
        $this->userId = $event->userId;
        $this->context = $event->initialContext;
        $this->isActive = true;
    }

    protected function applyAgentCreatedEvent(AgentCreatedEvent $event): void
    {
        $this->context['agent_id'] = $event->agentId;
        $this->context['capabilities'] = $event->capabilities;
    }

    protected function applyIntentClassifiedEvent(IntentClassifiedEvent $event): void
    {
        $this->context['last_intent'] = $event->intent;
        $this->context['last_confidence'] = $event->confidence;
    }

    protected function applyAIDecisionMadeEvent(AIDecisionMadeEvent $event): void
    {
        $this->context['last_decision'] = $event->decision;
        $this->context['requires_approval'] = $event->requiresApproval;
    }

    protected function applyToolExecutedEvent(ToolExecutedEvent $event): void
    {
        $this->executedTools[] = $event->toolName;
        $this->context['last_tool'] = $event->toolName;
        $this->context['last_tool_result'] = $event->result;
    }

    protected function applyConversationEndedEvent(ConversationEndedEvent $event): void
    {
        $this->isActive = false;
        $this->context['summary'] = $event->summary;
    }

    public function getConversationId(): string
    {
        return $this->conversationId;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getExecutedTools(): array
    {
        return $this->executedTools;
    }
}
