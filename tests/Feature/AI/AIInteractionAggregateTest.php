<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\ConversationStartedEvent;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use App\Domain\AI\Events\HumanOverrideEvent;
use App\Domain\AI\Events\ToolExecutedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AIInteractionAggregateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_starts_a_conversation_and_records_event(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_123';
        $userId = 1;
        $context = ['channel' => 'api', 'session' => 'test'];

        // Act
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', (string) $userId, $context)
            ->persist();

        // Assert
        $events = $aggregate->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ConversationStartedEvent::class, $events[0]);
        $this->assertEquals($conversationId, $events[0]->conversationId);
        $this->assertEquals($userId, $events[0]->userId);
        $this->assertEquals($context, $events[0]->context);
    }

    /** @test */
    public function it_records_ai_decisions_with_confidence(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_124';
        $decision = 'approve_transfer';
        $confidence = 0.92;
        $factors = ['risk_score' => 0.15, 'account_history' => 'good'];
        $alternatives = ['manual_review', 'decline'];

        // Act
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->recordDecision($decision, $confidence, $factors, $alternatives)
            ->persist();

        // Assert
        $events = $aggregate->getRecordedEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(AIDecisionMadeEvent::class, $events[1]);
        $this->assertEquals($decision, $events[1]->decision);
        $this->assertEquals($confidence, $events[1]->confidence);
        $this->assertEquals($factors, $events[1]->factors);
        $this->assertEquals($alternatives, $events[1]->alternatives);
    }

    /** @test */
    public function it_records_tool_executions(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_125';
        $toolName = 'CheckBalanceTool';
        $parameters = ['account_id' => 'ACC001'];
        $result = ['balance' => 1000.00, 'currency' => 'USD'];
        $executionTime = 0.125;

        // Act
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->recordToolExecution($toolName, $parameters, $result, $executionTime)
            ->persist();

        // Assert
        $events = $aggregate->getRecordedEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(ToolExecutedEvent::class, $events[1]);
        $this->assertEquals($toolName, $events[1]->toolName);
        $this->assertEquals($parameters, $events[1]->parameters);
        $this->assertEquals($result, $events[1]->result);
        $this->assertEquals($executionTime, $events[1]->executionTime);
        $this->assertTrue($events[1]->success);
    }

    /** @test */
    public function it_requests_human_intervention_for_low_confidence(): void
    {
        // Arrange
        Event::fake();
        config(['ai.confidence_threshold' => 0.8]);
        $conversationId = 'conv_test_126';
        $decision = 'risky_operation';
        $lowConfidence = 0.45;

        // Act
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->recordDecision($decision, $lowConfidence)
            ->persist();

        // Assert
        $events = $aggregate->getRecordedEvents();
        $this->assertCount(3, $events); // Start, HumanIntervention, Decision
        $this->assertInstanceOf(HumanInterventionRequestedEvent::class, $events[1]);
        $this->assertEquals('Low confidence decision', $events[1]->reason);
        $this->assertArrayHasKey('decision', $events[1]->context);
        $this->assertArrayHasKey('confidence', $events[1]->context);
    }

    /** @test */
    public function it_records_human_overrides(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_127';
        $originalDecision = 'auto_approve';
        $overrideDecision = 'manual_decline';
        $overriddenBy = 2;
        $reason = 'Risk factors not properly evaluated';

        // Act
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->recordDecision($originalDecision, 0.75)
            ->recordHumanOverride($originalDecision, $overrideDecision, $overriddenBy, $reason)
            ->persist();

        // Assert
        $events = $aggregate->getRecordedEvents();
        $humanOverrideEvent = end($events);
        $this->assertInstanceOf(HumanOverrideEvent::class, $humanOverrideEvent);
        $this->assertEquals($originalDecision, $humanOverrideEvent->originalDecision);
        $this->assertEquals($overrideDecision, $humanOverrideEvent->overrideDecision);
        $this->assertEquals($overriddenBy, $humanOverrideEvent->overriddenBy);
        $this->assertEquals($reason, $humanOverrideEvent->reason);
    }

    /** @test */
    public function it_calculates_average_confidence_across_decisions(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_128';

        // Act
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->recordDecision('decision1', 0.8)
            ->recordDecision('decision2', 0.9)
            ->recordDecision('decision3', 0.7)
            ->persist();

        // Calculate expected average
        $expectedAverage = (0.8 + 0.9 + 0.7) / 3;

        // Assert via human intervention which uses average
        $aggregate->requestHumanIntervention('Test reason');
        $events = $aggregate->getRecordedEvents();
        $interventionEvent = end($events);

        $this->assertInstanceOf(HumanInterventionRequestedEvent::class, $interventionEvent);
        $this->assertEqualsWithDelta($expectedAverage, $interventionEvent->aiConfidence, 0.01);
    }

    /** @test */
    public function it_maintains_event_order_and_immutability(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_129';

        // Act - Record multiple events
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->recordDecision('decision1', 0.85)
            ->recordToolExecution('Tool1', [], 'result1', 0.1)
            ->recordDecision('decision2', 0.95)
            ->recordToolExecution('Tool2', [], 'result2', 0.2)
            ->persist();

        // Assert - Events are in correct order
        $events = $aggregate->getRecordedEvents();
        $this->assertCount(5, $events);
        $this->assertInstanceOf(ConversationStartedEvent::class, $events[0]);
        $this->assertInstanceOf(AIDecisionMadeEvent::class, $events[1]);
        $this->assertInstanceOf(ToolExecutedEvent::class, $events[2]);
        $this->assertInstanceOf(AIDecisionMadeEvent::class, $events[3]);
        $this->assertInstanceOf(ToolExecutedEvent::class, $events[4]);
    }

    /** @test */
    public function it_can_replay_events_to_rebuild_state(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_130';

        // Act - Create aggregate with events
        $originalAggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1', ['test' => 'context'])
            ->recordDecision('test_decision', 0.88)
            ->recordToolExecution('TestTool', ['param' => 'value'], 'result', 0.5)
            ->persist();

        // Retrieve aggregate again (should replay events)
        $replayedAggregate = AIInteractionAggregate::retrieve($conversationId);

        // Assert - State is correctly rebuilt
        $originalEvents = $originalAggregate->getRecordedEvents();
        $replayedEvents = $replayedAggregate->getRecordedEvents();

        $this->assertCount(count($originalEvents), $replayedEvents);
        $this->assertEquals($originalEvents, $replayedEvents);
    }
}
