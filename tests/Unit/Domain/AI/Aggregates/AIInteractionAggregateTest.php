<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Aggregates;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\AgentCreatedEvent;
use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\ConversationEndedEvent;
use App\Domain\AI\Events\ConversationStartedEvent;
use App\Domain\AI\Events\IntentClassifiedEvent;
use App\Domain\AI\Events\ToolExecutedEvent;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AIInteractionAggregateTest extends TestCase
{
    private AIInteractionAggregate $aggregate;

    private string $conversationId;

    private string $userId;

    private string $agentType;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->conversationId = 'test-conversation-' . uniqid();
        $this->userId = 'test-user-' . uniqid();
        $this->agentType = 'customer_service';
        $this->aggregate = AIInteractionAggregate::retrieve($this->conversationId);
    }

    #[Test]
    public function it_starts_conversation_and_records_event(): void
    {
        // Act
        $this->aggregate->startConversation(
            $this->conversationId,
            $this->agentType,
            $this->userId,
            [
                'channel'    => 'api',
                'session_id' => 'test-session',
            ]
        );

        // Assert
        $this->assertEquals($this->conversationId, $this->aggregate->getConversationId());
        $this->assertTrue($this->aggregate->isActive());
        $this->assertArrayHasKey('channel', $this->aggregate->getContext());

        // Verify event would be recorded
        $events = $this->aggregate->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ConversationStartedEvent::class, $events[0]);
        $this->assertEquals($this->conversationId, $events[0]->conversationId);
        $this->assertEquals($this->userId, $events[0]->userId);
    }

    #[Test]
    public function it_creates_agent_and_records_event(): void
    {
        // Arrange
        $this->aggregate->startConversation($this->conversationId, $this->agentType, $this->userId, []);

        // Act
        $this->aggregate->createAgent(
            'agent-123',
            'customer_service',
            ['account_management', 'transfers']
        );

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $agentEvent = $events[1];
        $this->assertInstanceOf(AgentCreatedEvent::class, $agentEvent);
        $this->assertEquals('customer_service', $agentEvent->agentType);
        $this->assertEquals(['account_management', 'transfers'], $agentEvent->capabilities);
    }

    #[Test]
    public function it_classifies_intent_and_records_event(): void
    {
        // Arrange
        $this->aggregate->startConversation($this->conversationId, $this->agentType, $this->userId, []);

        // Act
        $this->aggregate->classifyIntent(
            'What is my account balance?',
            'check_balance',
            0.95
        );

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $intentEvent = $events[1];
        $this->assertInstanceOf(IntentClassifiedEvent::class, $intentEvent);
        $this->assertEquals('check_balance', $intentEvent->intent);
        $this->assertEquals(0.95, $intentEvent->confidence);
        $this->assertEquals('What is my account balance?', $intentEvent->query);
    }

    #[Test]
    public function it_makes_decision_and_records_event(): void
    {
        // Arrange
        $this->aggregate->startConversation($this->conversationId, $this->agentType, $this->userId, []);

        // Act
        $this->aggregate->makeDecision(
            'execute_balance_check',
            ['reason' => 'User requested balance information'],
            0.95,
            false
        );

        // Assert
        $events = $this->aggregate->getRecordedEvents();
        $decisionEvent = $events[1];
        $this->assertInstanceOf(AIDecisionMadeEvent::class, $decisionEvent);
        $this->assertEquals('execute_balance_check', $decisionEvent->decision);
        $this->assertArrayHasKey('reason', $decisionEvent->reasoning);
        $this->assertEquals(0.95, $decisionEvent->confidence);
    }

    #[Test]
    public function it_executes_tool_and_tracks_execution(): void
    {
        // Arrange
        $this->aggregate->startConversation($this->conversationId, $this->agentType, $this->userId, []);

        // Act
        $result = new ToolExecutionResult(
            success: true,
            data: [
                'balance'  => 1000.00,
                'currency' => 'USD',
            ],
            error: null,
            durationMs: 200
        );

        $this->aggregate->executeTool(
            'account.balance',
            ['account_uuid' => 'test-account'],
            $result
        );

        // Assert
        $executedTools = $this->aggregate->getExecutedTools();
        $this->assertCount(1, $executedTools);
        $this->assertEquals('account.balance', $executedTools[0]);

        $events = $this->aggregate->getRecordedEvents();
        $toolEvent = $events[1];
        $this->assertInstanceOf(ToolExecutedEvent::class, $toolEvent);
        $this->assertEquals('account.balance', $toolEvent->toolName);
        $this->assertArrayHasKey('balance', $toolEvent->result['data']);
    }

    #[Test]
    public function it_ends_conversation_and_marks_inactive(): void
    {
        // Arrange
        $this->aggregate->startConversation($this->conversationId, $this->agentType, $this->userId, []);
        $this->assertTrue($this->aggregate->isActive());

        // Act
        $this->aggregate->endConversation(['reason' => 'completed']);

        // Assert
        $this->assertFalse($this->aggregate->isActive());

        $events = $this->aggregate->getRecordedEvents();
        $endEvent = $events[1];
        $this->assertInstanceOf(ConversationEndedEvent::class, $endEvent);
        $this->assertArrayHasKey('reason', $endEvent->summary);
    }

    #[Test]
    public function it_allows_operations_after_conversation_ended(): void
    {
        // Arrange
        $this->aggregate->startConversation($this->conversationId, $this->agentType, $this->userId, []);
        $this->aggregate->endConversation(['reason' => 'completed']);

        // Act
        $result = new ToolExecutionResult(true, ['data' => 'test'], null, 100);
        $this->aggregate->executeTool('test.tool', [], $result);

        // Assert - Tool execution still works but conversation is marked as inactive
        $this->assertFalse($this->aggregate->isActive());
        $executedTools = $this->aggregate->getExecutedTools();
        $this->assertCount(1, $executedTools);
    }

    #[Test]
    public function it_tracks_multiple_tool_executions(): void
    {
        // Arrange
        $this->aggregate->startConversation($this->conversationId, $this->agentType, $this->userId, []);

        // Act
        $result1 = new ToolExecutionResult(true, ['balance' => 1000], null, 150);
        $this->aggregate->executeTool('account.balance', [], $result1);

        $result2 = new ToolExecutionResult(true, ['success' => true], null, 300);
        $this->aggregate->executeTool('transfer.execute', [], $result2);

        $result3 = new ToolExecutionResult(true, ['balance' => 500], null, 120);
        $this->aggregate->executeTool('account.balance', [], $result3);

        // Assert
        $executedTools = $this->aggregate->getExecutedTools();
        $this->assertCount(3, $executedTools);
        $this->assertEquals('account.balance', $executedTools[0]);
        $this->assertEquals('transfer.execute', $executedTools[1]);
        $this->assertEquals('account.balance', $executedTools[2]);

        // Verify all tool execution events
        $events = $this->aggregate->getRecordedEvents();
        $toolEvents = array_filter($events, fn ($e) => $e instanceof ToolExecutedEvent);
        $this->assertCount(3, $toolEvents);
    }

    #[Test]
    public function it_maintains_conversation_context(): void
    {
        // Arrange
        $context = [
            'channel'      => 'web',
            'ip_address'   => '192.168.1.1',
            'user_agent'   => 'Mozilla/5.0',
            'session_data' => ['key' => 'value'],
        ];

        // Act
        $this->aggregate->startConversation(
            $this->conversationId,
            $this->agentType,
            $this->userId,
            $context
        );

        // Assert
        $retrievedContext = $this->aggregate->getContext();
        $this->assertEquals($context, $retrievedContext);
        $this->assertEquals('web', $retrievedContext['channel']);
        $this->assertArrayHasKey('session_data', $retrievedContext);
    }

    #[Test]
    public function it_applies_events_correctly_when_reconstituted(): void
    {
        // Arrange - Start conversation and execute tools
        $this->aggregate->startConversation(
            $this->conversationId,
            $this->agentType,
            $this->userId,
            ['channel' => 'api']
        );

        $this->aggregate->classifyIntent(
            'transfer money',
            'transfer_money',
            0.89
        );

        $result = new ToolExecutionResult(
            success: true,
            data: ['success' => true],
            error: null,
            durationMs: 250
        );

        $this->aggregate->executeTool(
            'transfer.execute',
            ['amount' => 100],
            $result
        );

        $this->aggregate->endConversation(['tools_executed' => 1]);

        // Assert
        $this->assertEquals($this->conversationId, $this->aggregate->getConversationId());
        $this->assertFalse($this->aggregate->isActive()); // Should be inactive after EndedEvent
        $this->assertCount(1, $this->aggregate->getExecutedTools());
        $this->assertEquals('transfer.execute', $this->aggregate->getExecutedTools()[0]);

        // Verify events were recorded
        $events = $this->aggregate->getRecordedEvents();
        $this->assertCount(4, $events);
        $this->assertInstanceOf(ConversationStartedEvent::class, $events[0]);
        $this->assertInstanceOf(IntentClassifiedEvent::class, $events[1]);
        $this->assertInstanceOf(ToolExecutedEvent::class, $events[2]);
        $this->assertInstanceOf(ConversationEndedEvent::class, $events[3]);
    }
}
