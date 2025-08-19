<?php

declare(strict_types=1);

namespace App\Domain\AI\Aggregates;

use App\Domain\AI\Events\AgentCreatedEvent;
use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\ConversationEndedEvent;
use App\Domain\AI\Events\ConversationStartedEvent;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use App\Domain\AI\Events\IntentClassifiedEvent;
use App\Domain\AI\Events\LLMErrorEvent;
use App\Domain\AI\Events\LLMRequestMadeEvent;
use App\Domain\AI\Events\LLMResponseReceivedEvent;
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
        // Check if confidence is below threshold and request human intervention
        $threshold = config('ai.confidence_threshold', 0.7);
        if ($confidence < $threshold) {
            $this->recordThat(new HumanInterventionRequestedEvent(
                $this->conversationId,
                'Low confidence decision',
                [
                    'decision'   => $decision,
                    'confidence' => $confidence,
                    'reasoning'  => $reasoning,
                ],
                $confidence,
                null
            ));
        }

        $this->recordThat(new AIDecisionMadeEvent(
            $this->conversationId,
            $this->agentType,
            $decision,
            $reasoning,
            $confidence,
            $requiresApproval || $confidence < $threshold,
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

    public function recordHumanOverride(
        string $originalDecision,
        string $overriddenDecision,
        string $reason
    ): self {
        $this->recordThat(new HumanInterventionRequestedEvent(
            $this->conversationId,
            $reason,
            [
                'original_decision'   => $originalDecision,
                'overridden_decision' => $overriddenDecision,
                'intervention_type'   => 'override',
            ],
            0.5, // Default confidence for overrides
            $overriddenDecision
        ));

        return $this;
    }

    public function requestHumanIntervention(string $reason, array $context = []): self
    {
        $this->recordThat(new HumanInterventionRequestedEvent(
            $this->conversationId,
            $reason,
            array_merge(['intervention_type' => 'intervention_required'], $context),
            $context['confidence'] ?? 0.5
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

    public function recordLLMRequest(string $userId, string $provider, string $message, array $options = []): self
    {
        $this->recordThat(new LLMRequestMadeEvent(
            $this->conversationId,
            $userId,
            $provider,
            $message,
            $options,
            now()->toIso8601String()
        ));

        return $this;
    }

    public function recordLLMResponse(string $provider, string $content, int $totalTokens, array $metadata = []): self
    {
        $this->recordThat(new LLMResponseReceivedEvent(
            $this->conversationId,
            $provider,
            $content,
            $totalTokens,
            $metadata,
            now()->toIso8601String()
        ));

        return $this;
    }

    public function recordLLMError(string $provider, string $error): self
    {
        $this->recordThat(new LLMErrorEvent(
            $this->conversationId,
            $provider,
            $error,
            now()->toIso8601String()
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

    protected function applyHumanInterventionRequestedEvent(HumanInterventionRequestedEvent $event): void
    {
        $this->context['intervention_requested'] = true;
        $this->context['intervention_reason'] = $event->reason;
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
