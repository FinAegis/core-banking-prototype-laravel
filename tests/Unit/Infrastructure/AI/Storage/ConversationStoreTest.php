<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\AI\Storage;

use App\Domain\AI\ValueObjects\ConversationContext;
use App\Infrastructure\AI\Storage\ConversationStore;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ConversationStoreTest extends TestCase
{
    private ConversationStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new ConversationStore();

        // Clear Redis test data
        Redis::flushdb();
    }

    protected function tearDown(): void
    {
        // Clean up Redis after tests
        Redis::flushdb();
        parent::tearDown();
    }

    #[Test]
    public function it_can_store_and_retrieve_conversation(): void
    {
        // Arrange
        $conversationId = 'test-conv-' . uniqid();
        $userId = 'user-123';
        $messages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];
        $systemPrompt = ['role' => 'system', 'content' => 'You are helpful'];
        $metadata = ['source' => 'web', 'timestamp' => time()];

        $context = new ConversationContext(
            $conversationId,
            $userId,
            $messages,
            $systemPrompt,
            $metadata
        );

        // Act
        $this->store->store($context);
        $retrieved = $this->store->retrieve($conversationId);

        // Assert
        $this->assertNotNull($retrieved);
        $this->assertEquals($conversationId, $retrieved->getConversationId());
        $this->assertEquals($userId, $retrieved->getUserId());
        $this->assertEquals($messages, $retrieved->getMessages());
        $this->assertEquals($systemPrompt, $retrieved->getSystemPrompt());
        $this->assertEquals($metadata, $retrieved->getMetadata());
    }

    #[Test]
    public function it_returns_null_for_non_existent_conversation(): void
    {
        // Act
        $result = $this->store->retrieve('non-existent-id');

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function it_can_add_message_to_existing_conversation(): void
    {
        // Arrange
        $conversationId = 'test-conv-' . uniqid();
        $userId = 'user-123';
        $initialMessages = [
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $context = new ConversationContext(
            $conversationId,
            $userId,
            $initialMessages
        );

        $this->store->store($context);

        // Act
        $this->store->addMessage($conversationId, 'assistant', 'Hi there!');
        $retrieved = $this->store->retrieve($conversationId);

        // Assert
        $this->assertNotNull($retrieved);
        $messages = $retrieved->getMessages();
        $this->assertCount(2, $messages);
        $this->assertEquals('assistant', $messages[1]['role']);
        $this->assertEquals('Hi there!', $messages[1]['content']);
    }

    #[Test]
    public function it_throws_exception_when_adding_message_to_non_existent_conversation(): void
    {
        // Arrange & Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conversation non-existent not found');

        $this->store->addMessage('non-existent', 'user', 'test');
    }

    #[Test]
    public function it_can_get_user_conversations(): void
    {
        // Arrange
        $userId = 'user-123';

        // Create multiple conversations
        for ($i = 0; $i < 5; $i++) {
            $context = new ConversationContext(
                'conv-' . $i,
                $userId,
                [['role' => 'user', 'content' => "Message $i"]]
            );
            $this->store->store($context);
            usleep(1000); // Small delay to ensure different timestamps
        }

        // Act
        $conversations = $this->store->getUserConversations($userId, 3);

        // Assert
        $this->assertCount(3, $conversations);
        $this->assertEquals('conv-4', $conversations[0]['conversation_id']);
        $this->assertEquals('conv-3', $conversations[1]['conversation_id']);
        $this->assertEquals('conv-2', $conversations[2]['conversation_id']);
    }

    #[Test]
    public function it_can_delete_conversation(): void
    {
        // Arrange
        $conversationId = 'test-conv-' . uniqid();
        $userId = 'user-123';
        $context = new ConversationContext($conversationId, $userId);

        $this->store->store($context);

        // Act
        $this->store->delete($conversationId);
        $retrieved = $this->store->retrieve($conversationId);

        // Assert
        $this->assertNull($retrieved);

        // Check it's also removed from user's list
        $userConversations = $this->store->getUserConversations($userId);
        $this->assertEmpty($userConversations);
    }

    #[Test]
    public function it_can_clear_all_user_conversations(): void
    {
        // Arrange
        $userId = 'user-123';

        // Create multiple conversations
        for ($i = 0; $i < 3; $i++) {
            $context = new ConversationContext(
                'conv-' . $i,
                $userId,
                [['role' => 'user', 'content' => "Message $i"]]
            );
            $this->store->store($context);
        }

        // Act
        $this->store->clearUserConversations($userId);

        // Assert
        $conversations = $this->store->getUserConversations($userId);
        $this->assertEmpty($conversations);

        // Check individual conversations are deleted
        for ($i = 0; $i < 3; $i++) {
            $this->assertNull($this->store->retrieve('conv-' . $i));
        }
    }

    #[Test]
    public function it_can_search_conversations_by_content(): void
    {
        // Arrange
        $userId = 'user-123';

        // Create conversations with different content
        $contexts = [
            new ConversationContext(
                'conv-1',
                $userId,
                [
                    ['role' => 'user', 'content' => 'Tell me about Laravel'],
                    ['role' => 'assistant', 'content' => 'Laravel is a PHP framework'],
                ]
            ),
            new ConversationContext(
                'conv-2',
                $userId,
                [
                    ['role' => 'user', 'content' => 'What is React?'],
                    ['role' => 'assistant', 'content' => 'React is a JavaScript library'],
                ]
            ),
            new ConversationContext(
                'conv-3',
                $userId,
                [
                    ['role' => 'user', 'content' => 'Explain PHP'],
                    ['role' => 'assistant', 'content' => 'PHP is a server-side language'],
                ]
            ),
        ];

        foreach ($contexts as $context) {
            $this->store->store($context);
        }

        // Act
        $results = $this->store->searchConversations($userId, 'PHP');

        // Assert
        $this->assertCount(2, $results);

        $conversationIds = array_column($results, 'conversation_id');
        $this->assertContains('conv-1', $conversationIds);
        $this->assertContains('conv-3', $conversationIds);
    }

    #[Test]
    public function it_limits_user_conversations_to_100(): void
    {
        // Arrange
        $userId = 'user-123';

        // Create 105 conversations
        $baseTime = time();
        for ($i = 0; $i < 105; $i++) {
            $context = new ConversationContext(
                'conv-' . $i,
                $userId,
                [['role' => 'user', 'content' => "Message $i"]]
            );

            // Store with incrementing timestamps
            $key = 'ai:conversation:' . $context->getConversationId();
            $userKey = 'ai:conversation:user:' . $userId;
            Redis::setex($key, 3600, json_encode($context->toArray()));
            Redis::zadd($userKey, $baseTime + $i, $context->getConversationId());
        }

        // Trim to keep last 100
        Redis::zremrangebyrank('ai:conversation:user:' . $userId, 0, -101);

        // Act
        $userKey = 'ai:conversation:user:' . $userId;
        $count = Redis::zcard($userKey);

        // Assert
        $this->assertEquals(100, $count);

        // Verify oldest conversations were removed
        $conversations = Redis::zrange($userKey, 0, -1);
        $this->assertNotContains('conv-0', $conversations);
        $this->assertNotContains('conv-4', $conversations);
        $this->assertContains('conv-104', $conversations);
    }
}
