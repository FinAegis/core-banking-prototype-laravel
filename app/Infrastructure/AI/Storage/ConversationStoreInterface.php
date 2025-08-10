<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Storage;

use App\Domain\AI\ValueObjects\ConversationContext;

interface ConversationStoreInterface
{
    /**
     * Store conversation context.
     */
    public function store(ConversationContext $context, ?int $ttl = null): void;

    /**
     * Retrieve conversation context.
     */
    public function retrieve(string $conversationId): ?ConversationContext;

    /**
     * Update conversation with new message.
     */
    public function addMessage(string $conversationId, string $role, string $content): void;

    /**
     * Get user's conversation history.
     */
    public function getUserConversations(string $userId, int $limit = 10): array;

    /**
     * Delete conversation.
     */
    public function delete(string $conversationId): void;

    /**
     * Clear all conversations for a user.
     */
    public function clearUserConversations(string $userId): void;

    /**
     * Search conversations by content.
     */
    public function searchConversations(string $userId, string $searchTerm): array;
}
