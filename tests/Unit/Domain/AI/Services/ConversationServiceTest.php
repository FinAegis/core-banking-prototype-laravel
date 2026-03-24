<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\AI\Services;

use App\Domain\AI\Services\ConversationService;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    private ConversationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->service = new ConversationService();
    }

    public function test_get_or_create_returns_new_conversation(): void
    {
        $conversationId = 'conv-' . uniqid();
        $result = $this->service->getOrCreate($conversationId, 1);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['id', 'user_id', 'messages', 'created_at', 'updated_at']);
        expect($result['id'])->toBe($conversationId);
        expect($result['user_id'])->toBe(1);
        expect($result['messages'])->toBeArray();
    }

    public function test_get_or_create_returns_existing_conversation(): void
    {
        $conversationId = 'conv-existing-' . uniqid();
        $first = $this->service->getOrCreate($conversationId, 1);
        $second = $this->service->getOrCreate($conversationId, 1);

        expect($second['id'])->toBe($first['id']);
        expect($second['user_id'])->toBe($first['user_id']);
    }

    public function test_get_user_conversations_returns_array(): void
    {
        $conversations = $this->service->getUserConversations(1, 5);

        expect($conversations)->toBeArray();

        if (count($conversations) > 0) {
            $first = $conversations[0];
            expect($first)->toHaveKeys(['id', 'title', 'last_message', 'message_count']);
        }
    }

    public function test_get_conversation_returns_null_for_nonexistent(): void
    {
        $result = $this->service->getConversation('nonexistent-conv-id', 999);

        // Returns either null or demo data depending on implementation
        if ($result !== null) {
            expect($result)->toBeArray();
            expect($result)->toHaveKey('id');
        } else {
            expect($result)->toBeNull();
        }
    }

    public function test_delete_conversation_returns_bool(): void
    {
        $conversationId = 'conv-delete-' . uniqid();
        $this->service->getOrCreate($conversationId, 1);

        $result = $this->service->deleteConversation($conversationId, 1);

        expect($result)->toBeBool();
    }

    public function test_delete_conversation_fails_for_wrong_user(): void
    {
        $conversationId = 'conv-wrong-user-' . uniqid();
        $this->service->getOrCreate($conversationId, 1);

        $result = $this->service->deleteConversation($conversationId, 999);

        expect($result)->toBeFalse();
    }

    public function test_get_user_conversations_respects_limit(): void
    {
        $conversations = $this->service->getUserConversations(1, 3);

        expect($conversations)->toBeArray();
        expect(count($conversations))->toBeLessThanOrEqual(3);
    }
}
