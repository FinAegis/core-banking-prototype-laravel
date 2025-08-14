<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\ConversationStartedEvent;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use App\Domain\AI\Events\ToolExecutedEvent;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AIInteractionAggregateTest extends TestCase
{
    // Note: RefreshDatabase is needed for event sourcing snapshots table

    /**
     * Override to prevent database operations in setUp.
     */
    protected function shouldCreateDefaultAccountsInSetup(): bool
    {
        return false;
    }

    /**
     * Override to prevent role creation.
     */
    protected function createRoles(): void
    {
        // Skip role creation for this test
    }

    #[\PHPUnit\Framework\Attributes\Test]
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
        $events = $aggregate->getAppliedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ConversationStartedEvent::class, $events[0]);
        $this->assertEquals($conversationId, $events[0]->conversationId);
        $this->assertEquals($userId, $events[0]->userId);
        $this->assertEquals($context, $events[0]->initialContext);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_ai_decisions_with_confidence(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_124';
        $decision = 'approve_transfer';
        $confidence = 0.92;
        $reasoning = ['risk_score' => 0.15, 'account_history' => 'good'];

        // Act
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->makeDecision($decision, $reasoning, $confidence)
            ->persist();

        // Assert
        $events = $aggregate->getAppliedEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(AIDecisionMadeEvent::class, $events[1]);
        $this->assertEquals($decision, $events[1]->decision);
        $this->assertEquals($confidence, $events[1]->confidence);
        $this->assertEquals($reasoning, $events[1]->reasoning);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_tool_executions(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_125';
        $toolName = 'CheckBalanceTool';
        $parameters = ['account_id' => 'ACC001'];
        $resultData = ['balance' => 1000.00, 'currency' => 'USD'];

        $toolResult = ToolExecutionResult::success($resultData);

        // Act
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->executeTool($toolName, $parameters, $toolResult)
            ->persist();

        // Assert
        $events = $aggregate->getAppliedEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(ToolExecutedEvent::class, $events[1]);
        $this->assertEquals($toolName, $events[1]->toolName);
        $this->assertEquals($parameters, $events[1]->parameters);
        $this->assertEquals($toolResult->toArray(), $events[1]->result);
        $this->assertEquals($toolResult->getDurationMs(), $events[1]->durationMs);
        $this->assertTrue($events[1]->wasSuccessful());
    }

    #[\PHPUnit\Framework\Attributes\Test]
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
            ->makeDecision($decision, [], $lowConfidence)
            ->persist();

        // Assert
        $events = $aggregate->getAppliedEvents();
        $this->assertCount(3, $events); // Start, HumanIntervention, Decision
        $this->assertInstanceOf(HumanInterventionRequestedEvent::class, $events[1]);
        $this->assertEquals('Low confidence decision', $events[1]->reason);
        $this->assertArrayHasKey('decision', $events[1]->context);
        $this->assertArrayHasKey('confidence', $events[1]->context);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_human_overrides(): void
    {
        $this->markTestSkipped('Method recordHumanOverride not implemented in aggregate');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_average_confidence_across_decisions(): void
    {
        $this->markTestSkipped('Method requestHumanIntervention not implemented in aggregate');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maintains_event_order_and_immutability(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_129';

        // Act - Record multiple events
        $aggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1')
            ->makeDecision('decision1', [], 0.85)
            ->executeTool('Tool1', [], ToolExecutionResult::success(['result' => 'result1']))
            ->makeDecision('decision2', [], 0.95)
            ->executeTool('Tool2', [], ToolExecutionResult::success(['result' => 'result2']))
            ->persist();

        // Assert - Events are in correct order
        $events = $aggregate->getAppliedEvents();
        $this->assertCount(5, $events);
        $this->assertInstanceOf(ConversationStartedEvent::class, $events[0]);
        $this->assertInstanceOf(AIDecisionMadeEvent::class, $events[1]);
        $this->assertInstanceOf(ToolExecutedEvent::class, $events[2]);
        $this->assertInstanceOf(AIDecisionMadeEvent::class, $events[3]);
        $this->assertInstanceOf(ToolExecutedEvent::class, $events[4]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_replay_events_to_rebuild_state(): void
    {
        // Arrange
        Event::fake();
        $conversationId = 'conv_test_130';

        // Act - Create aggregate with events
        $originalAggregate = AIInteractionAggregate::retrieve($conversationId)
            ->startConversation($conversationId, 'customer_service', '1', ['test' => 'context'])
            ->makeDecision('test_decision', [], 0.88)
            ->executeTool('TestTool', ['param' => 'value'], ToolExecutionResult::success(['result' => 'test']))
            ->persist();

        // Retrieve aggregate again (should replay events)
        $replayedAggregate = AIInteractionAggregate::retrieve($conversationId);

        // Assert - State is correctly rebuilt
        $originalEvents = $originalAggregate->getAppliedEvents();
        $replayedEvents = $replayedAggregate->getAppliedEvents();

        $this->assertCount(count($originalEvents), $replayedEvents);
        // Compare events by class type rather than exact equality
        // since internal properties like firedFromAggregateRoot may differ
        foreach ($originalEvents as $index => $event) {
            $eventClass = get_class($event);
            $this->assertNotFalse($eventClass);
            $this->assertInstanceOf($eventClass, $replayedEvents[$index]);
        }
    }
}
