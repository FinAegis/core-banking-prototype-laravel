<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\AI\Storage;

use App\Domain\AI\ValueObjects\ConversationContext;
use App\Infrastructure\AI\Storage\ConversationStore;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Throwable;

class ConversationStoreExtendedTest extends TestCase
{
    private ConversationStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if Redis is not available
        try {
            $connection = Redis::connection();
            $connection->ping();
        } catch (Throwable) {
            $this->markTestSkipped('Redis is not available');
        }

        $this->store = app(ConversationStore::class);
    }

    public function test_store_and_retrieve_conversation(): void
    {
        $convId = 'test-conv-' . uniqid();
        $context = new ConversationContext(
            conversationId: $convId,
            userId: 'user-123',
        );

        $this->store->store($context);

        $retrieved = $this->store->retrieve($convId);

        self::assertNotNull($retrieved);
        self::assertInstanceOf(ConversationContext::class, $retrieved);
        expect($retrieved->getConversationId())->toBe($convId);
        expect($retrieved->getUserId())->toBe('user-123');

        // Cleanup
        $this->store->delete($convId);
    }

    public function test_retrieve_returns_null_for_missing(): void
    {
        $result = $this->store->retrieve('nonexistent-conv-' . uniqid());

        expect($result)->toBeNull();
    }

    public function test_add_message_updates_context(): void
    {
        $convId = 'test-msg-' . uniqid();
        $context = new ConversationContext(
            conversationId: $convId,
            userId: 'user-456',
        );

        $this->store->store($context);
        $this->store->addMessage($convId, 'user', 'Hello, what is my balance?');

        $retrieved = $this->store->retrieve($convId);

        self::assertNotNull($retrieved);
        self::assertInstanceOf(ConversationContext::class, $retrieved);

        $messages = $retrieved->getMessages();
        expect($messages)->not->toBeEmpty();

        $lastMessage = end($messages);
        if (is_array($lastMessage)) {
            expect($lastMessage['role'])->toBe('user');
            expect($lastMessage['content'])->toContain('balance');
        }

        // Cleanup
        $this->store->delete($convId);
    }

    public function test_delete_removes_conversation(): void
    {
        $convId = 'test-delete-' . uniqid();
        $context = new ConversationContext(
            conversationId: $convId,
            userId: 'user-789',
        );

        $this->store->store($context);
        expect($this->store->retrieve($convId))->not->toBeNull();

        $this->store->delete($convId);
        expect($this->store->retrieve($convId))->toBeNull();
    }

    public function test_get_user_conversations_returns_array(): void
    {
        $userId = 'user-list-' . uniqid();
        $convId = 'test-list-' . uniqid();
        $context = new ConversationContext(
            conversationId: $convId,
            userId: $userId,
        );

        $this->store->store($context);

        $conversations = $this->store->getUserConversations($userId, 10);

        expect($conversations)->toBeArray();

        // Cleanup
        $this->store->delete($convId);
    }

    public function test_clear_user_conversations_removes_all(): void
    {
        $userId = 'user-clear-' . uniqid();

        for ($i = 0; $i < 3; $i++) {
            $context = new ConversationContext(
                conversationId: 'test-clear-' . $i . '-' . uniqid(),
                userId: $userId,
            );
            $this->store->store($context);
        }

        $this->store->clearUserConversations($userId);

        $conversations = $this->store->getUserConversations($userId, 10);
        expect($conversations)->toBeEmpty();
    }

    public function test_store_with_custom_ttl(): void
    {
        $convId = 'test-ttl-' . uniqid();
        $context = new ConversationContext(
            conversationId: $convId,
            userId: 'user-ttl',
        );

        $this->store->store($context, 60);

        $retrieved = $this->store->retrieve($convId);
        expect($retrieved)->not->toBeNull();

        // Cleanup
        $this->store->delete($convId);
    }
}
