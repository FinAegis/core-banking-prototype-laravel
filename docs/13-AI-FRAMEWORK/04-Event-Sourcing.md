# Event Sourcing Patterns for AI Framework

## Overview

The AI framework uses event sourcing to keep an audit trail of agent decisions, tool calls and conversations. Each change is stored as an event that is never edited. The stored events show what an agent did and why, and they can be replayed to analyze its behavior.

`AIInteractionAggregate` in `app/Domain/AI/Aggregates/` records the events through Spatie Laravel Event Sourcing. It uses the default `stored_events` table. The event classes are in `app/Domain/AI/Events/`.

The code on this page is simplified. The real event constructors take different fields. For example, `AIDecisionMadeEvent` takes `conversationId`, `agentType`, `decision`, `reasoning`, `confidence`, `requiresApproval`, `userId` and `timestamp`. There is no `HumanOverrideEvent` class. `AIInteractionAggregate::recordHumanOverride()` stores an override as a `HumanInterventionRequestedEvent` with `intervention_type: override`. The projections, the replay service and the reactors on this page are examples to adapt. The AI domain has no projection or reactor classes yet.

## Core Concepts

### Event Store Architecture

```
┌─────────────────────────────────────────┐
│          AI Interaction                  │
├─────────────────────────────────────────┤
│  • User Input                           │
│  • AI Processing                        │
│  • Tool Execution                       │
│  • Decision Making                      │
└─────────────┬───────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│       AIInteractionAggregate             │
├─────────────────────────────────────────┤
│  • Apply Events                         │
│  • Record New Events                    │
│  • Validate State                       │
│  • Generate Projections                 │
└─────────────┬───────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│           Event Store                    │
├─────────────────────────────────────────┤
│  stored_events table                    │
│  • Event ID                             │
│  • Aggregate ID                         │
│  • Event Type                           │
│  • Event Data (JSON)                    │
│  • Metadata                             │
│  • Timestamp                            │
└─────────────────────────────────────────┘
```

## AI Domain Events

### Core Event Types

```php
namespace App\Domain\AI\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class ConversationStartedEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public int $userId,
        public array $context,
        public string $channel,
        public Carbon $startedAt
    ) {}
}

class AIDecisionMadeEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $decision,
        public float $confidence,
        public array $factors,
        public array $alternatives,
        public Carbon $timestamp
    ) {}
}

class ToolExecutedEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $toolName,
        public array $parameters,
        public mixed $result,
        public float $executionTime,
        public bool $success,
        public Carbon $executedAt
    ) {}
}

class HumanInterventionRequestedEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $reason,
        public array $context,
        public float $aiConfidence,
        public Carbon $requestedAt
    ) {}
}

class HumanOverrideEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $originalDecision,
        public string $overrideDecision,
        public int $overriddenBy,
        public string $reason,
        public Carbon $overriddenAt
    ) {}
}
```

## AIInteractionAggregate

`AIInteractionAggregate` is the aggregate root that holds the state of one AI interaction. The simplified version below shows the pattern.

```php
namespace App\Domain\AI\Aggregates;

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use App\Domain\AI\Events\*;

class AIInteractionAggregate extends AggregateRoot
{
    protected string $conversationId;
    protected int $userId;
    protected array $context = [];
    protected array $decisions = [];
    protected array $toolExecutions = [];
    protected float $totalConfidence = 0;
    protected int $decisionCount = 0;
    
    public function startConversation(
        string $conversationId,
        int $userId,
        array $context = []
    ): self {
        $this->recordThat(new ConversationStartedEvent(
            conversationId: $conversationId,
            userId: $userId,
            context: $context,
            channel: 'api',
            startedAt: now()
        ));
        
        return $this;
    }
    
    public function recordDecision(
        string $decision,
        float $confidence,
        array $factors = [],
        array $alternatives = []
    ): self {
        // Validate confidence threshold
        if ($confidence < config('ai.confidence_threshold', 0.7)) {
            $this->requestHumanIntervention(
                reason: 'Low confidence decision',
                context: compact('decision', 'confidence', 'factors')
            );
        }
        
        $this->recordThat(new AIDecisionMadeEvent(
            conversationId: $this->conversationId,
            decision: $decision,
            confidence: $confidence,
            factors: $factors,
            alternatives: $alternatives,
            timestamp: now()
        ));
        
        return $this;
    }
    
    public function recordToolExecution(
        string $toolName,
        array $parameters,
        mixed $result,
        float $executionTime,
        bool $success = true
    ): self {
        $this->recordThat(new ToolExecutedEvent(
            conversationId: $this->conversationId,
            toolName: $toolName,
            parameters: $parameters,
            result: $result,
            executionTime: $executionTime,
            success: $success,
            executedAt: now()
        ));
        
        return $this;
    }
    
    public function requestHumanIntervention(
        string $reason,
        array $context = []
    ): self {
        $this->recordThat(new HumanInterventionRequestedEvent(
            conversationId: $this->conversationId,
            reason: $reason,
            context: $context,
            aiConfidence: $this->getAverageConfidence(),
            requestedAt: now()
        ));
        
        return $this;
    }
    
    public function recordHumanOverride(
        string $originalDecision,
        string $overrideDecision,
        int $overriddenBy,
        string $reason
    ): self {
        $this->recordThat(new HumanOverrideEvent(
            conversationId: $this->conversationId,
            originalDecision: $originalDecision,
            overrideDecision: $overrideDecision,
            overriddenBy: $overriddenBy,
            reason: $reason,
            overriddenAt: now()
        ));
        
        return $this;
    }
    
    // Event Handlers
    
    protected function applyConversationStartedEvent(
        ConversationStartedEvent $event
    ): void {
        $this->conversationId = $event->conversationId;
        $this->userId = $event->userId;
        $this->context = $event->context;
    }
    
    protected function applyAIDecisionMadeEvent(
        AIDecisionMadeEvent $event
    ): void {
        $this->decisions[] = [
            'decision' => $event->decision,
            'confidence' => $event->confidence,
            'factors' => $event->factors,
            'timestamp' => $event->timestamp,
        ];
        
        $this->totalConfidence += $event->confidence;
        $this->decisionCount++;
    }
    
    protected function applyToolExecutedEvent(
        ToolExecutedEvent $event
    ): void {
        $this->toolExecutions[] = [
            'tool' => $event->toolName,
            'parameters' => $event->parameters,
            'result' => $event->result,
            'execution_time' => $event->executionTime,
            'success' => $event->success,
        ];
    }
    
    private function getAverageConfidence(): float
    {
        return $this->decisionCount > 0 
            ? $this->totalConfidence / $this->decisionCount 
            : 0.0;
    }
}
```

## Projections and Read Models

### Conversation Projection

```php
namespace App\Domain\AI\Projections;

use Spatie\EventSourcing\Projections\Projection;
use App\Domain\AI\Events\*;
use App\Models\AIConversation;

class ConversationProjection extends Projection
{
    public function onConversationStartedEvent(
        ConversationStartedEvent $event
    ): void {
        AIConversation::create([
            'id' => $event->conversationId,
            'user_id' => $event->userId,
            'context' => $event->context,
            'channel' => $event->channel,
            'started_at' => $event->startedAt,
            'status' => 'active',
        ]);
    }
    
    public function onAIDecisionMadeEvent(
        AIDecisionMadeEvent $event
    ): void {
        $conversation = AIConversation::find($event->conversationId);
        
        $conversation->decisions()->create([
            'decision' => $event->decision,
            'confidence' => $event->confidence,
            'factors' => $event->factors,
            'alternatives' => $event->alternatives,
            'timestamp' => $event->timestamp,
        ]);
        
        // Update conversation metrics
        $conversation->increment('decision_count');
        $conversation->update([
            'average_confidence' => $conversation->decisions()
                ->avg('confidence'),
            'last_activity_at' => $event->timestamp,
        ]);
    }
    
    public function onToolExecutedEvent(
        ToolExecutedEvent $event
    ): void {
        $conversation = AIConversation::find($event->conversationId);
        
        $conversation->toolExecutions()->create([
            'tool_name' => $event->toolName,
            'parameters' => $event->parameters,
            'result' => $event->result,
            'execution_time' => $event->executionTime,
            'success' => $event->success,
            'executed_at' => $event->executedAt,
        ]);
        
        // Update tool usage metrics
        $conversation->increment('tools_used_count');
        $conversation->increment(
            'total_execution_time',
            $event->executionTime
        );
    }
}
```

### Analytics Projection

```php
namespace App\Domain\AI\Projections;

use App\Domain\AI\Events\*;
use App\Models\AIAnalytics;

class AIAnalyticsProjection extends Projection
{
    public function onAIDecisionMadeEvent(
        AIDecisionMadeEvent $event
    ): void {
        AIAnalytics::firstOrCreate(
            ['date' => now()->toDateString()],
            ['decisions' => 0, 'total_confidence' => 0]
        )->increment('decisions')
          ->increment('total_confidence', $event->confidence * 100);
    }
    
    public function onToolExecutedEvent(
        ToolExecutedEvent $event
    ): void {
        // Track tool usage
        DB::table('ai_tool_usage')
            ->updateOrInsert(
                [
                    'tool_name' => $event->toolName,
                    'date' => now()->toDateString(),
                ],
                [
                    'executions' => DB::raw('executions + 1'),
                    'total_time' => DB::raw('total_time + ' . $event->executionTime),
                    'failures' => DB::raw('failures + ' . ($event->success ? 0 : 1)),
                ]
            );
    }
    
    public function onHumanInterventionRequestedEvent(
        HumanInterventionRequestedEvent $event
    ): void {
        // Track intervention requests
        DB::table('ai_interventions')
            ->insert([
                'conversation_id' => $event->conversationId,
                'reason' => $event->reason,
                'ai_confidence' => $event->aiConfidence,
                'requested_at' => $event->requestedAt,
                'status' => 'pending',
            ]);
    }
}
```

## Event Replay and Time Travel

### Replaying Events

```php
namespace App\Domain\AI\Services;

use App\Domain\AI\Aggregates\AIInteractionAggregate;

class EventReplayService
{
    public function replayConversation(string $conversationId): array
    {
        // Retrieve aggregate with all events
        $aggregate = AIInteractionAggregate::retrieve($conversationId);
        
        // Get stored events
        $events = $aggregate->getRecordedEvents();
        
        // Replay events to build state
        $state = [];
        foreach ($events as $event) {
            $state[] = [
                'type' => class_basename($event),
                'data' => $event->toArray(),
                'timestamp' => $event->createdAt(),
            ];
        }
        
        return $state;
    }
    
    public function replayToPoint(
        string $conversationId,
        Carbon $pointInTime
    ): AIInteractionAggregate {
        // Get events up to specific time
        $events = DB::table('stored_events')
            ->where('aggregate_uuid', $conversationId)
            ->where('created_at', '<=', $pointInTime)
            ->orderBy('id')
            ->get();
        
        // Rebuild aggregate to that point
        $aggregate = new AIInteractionAggregate();
        
        foreach ($events as $event) {
            $aggregate->apply($event);
        }
        
        return $aggregate;
    }
}
```

## Event Handlers and Reactors

### Notification Reactor

```php
namespace App\Domain\AI\Reactors;

use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use App\Notifications\HumanInterventionRequired;

class NotificationReactor extends Reactor
{
    public function onHumanInterventionRequestedEvent(
        HumanInterventionRequestedEvent $event
    ): void {
        // Find appropriate human reviewers
        $reviewers = User::role('ai_reviewer')
            ->where('available', true)
            ->get();
        
        // Notify reviewers
        Notification::send($reviewers, new HumanInterventionRequired(
            conversationId: $event->conversationId,
            reason: $event->reason,
            context: $event->context
        ));
        
        // Log to monitoring system
        Log::channel('ai_monitoring')->warning('Human intervention requested', [
            'conversation_id' => $event->conversationId,
            'reason' => $event->reason,
            'confidence' => $event->aiConfidence,
        ]);
    }
}
```

### Compliance Reactor

```php
namespace App\Domain\AI\Reactors;

use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\Compliance\Services\ComplianceService;

class ComplianceReactor extends Reactor
{
    public function __construct(
        private ComplianceService $complianceService
    ) {}
    
    public function onAIDecisionMadeEvent(
        AIDecisionMadeEvent $event
    ): void {
        // Check if decision requires compliance review
        if ($this->requiresComplianceReview($event)) {
            $this->complianceService->createReview([
                'type' => 'ai_decision',
                'reference_id' => $event->conversationId,
                'decision' => $event->decision,
                'factors' => $event->factors,
                'confidence' => $event->confidence,
            ]);
        }
    }
    
    private function requiresComplianceReview(
        AIDecisionMadeEvent $event
    ): bool {
        // Check decision type
        $regulatedDecisions = [
            'loan_approval',
            'account_closure',
            'suspicious_activity_report',
            'large_transfer_approval',
        ];
        
        return in_array($event->decision, $regulatedDecisions);
    }
}
```

## Testing Event-Sourced Components

```php
namespace Tests\Feature\AI;

use Tests\TestCase;
use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\*;
use Spatie\EventSourcing\Facades\Projectionist;

class AIInteractionAggregateTest extends TestCase
{
    /** @test */
    public function it_records_conversation_events(): void
    {
        // Create aggregate
        $aggregate = AIInteractionAggregate::retrieve('conv_123')
            ->startConversation('conv_123', 1, ['channel' => 'web'])
            ->recordDecision('balance_inquiry', 0.95, ['intent' => 'check_balance'])
            ->recordToolExecution('CheckBalanceTool', ['account' => 'ACC001'], ['balance' => 1000], 0.5)
            ->persist();
        
        // Assert events were recorded
        $this->assertCount(3, $aggregate->getRecordedEvents());
        
        // Verify projection was updated
        $this->assertDatabaseHas('ai_conversations', [
            'id' => 'conv_123',
            'user_id' => 1,
            'decision_count' => 1,
            'tools_used_count' => 1,
        ]);
    }
    
    /** @test */
    public function it_requests_human_intervention_for_low_confidence(): void
    {
        config(['ai.confidence_threshold' => 0.8]);
        
        $aggregate = AIInteractionAggregate::retrieve('conv_124')
            ->startConversation('conv_124', 1)
            ->recordDecision('risky_decision', 0.6);  // Below threshold
        
        // Assert intervention event was recorded
        $events = $aggregate->getRecordedEvents();
        $this->assertCount(3, $events);  // Start, Intervention, Decision
        
        $this->assertInstanceOf(
            HumanInterventionRequestedEvent::class,
            $events[1]
        );
    }
}
```

## Best Practices

1. Name events in the past tense, for example `DecisionMade` rather than `MakeDecision`.
2. Put all the data needed to rebuild the state into the event.
3. Never change a stored event.
4. Make event handlers idempotent, so a replay does not repeat side effects.
5. Use projections for data that is read often.
6. Test both the recording of events and the projections.
7. Track how fast events are processed.

## Next Steps

- [API Reference](05-API-Reference.md)
- [Workflow Development](03-Workflows.md)