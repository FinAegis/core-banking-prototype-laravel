<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Storage;

use App\Domain\AI\ValueObjects\ConversationContext;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class ConversationStore implements ConversationStoreInterface
{
    private const PREFIX = 'ai:conversation:';

    private const TTL = 86400; // 24 hours default TTL

    /**
     * Store conversation context.
     */
    public function store(ConversationContext $context, ?int $ttl = null): void
    {
        $key = self::PREFIX . $context->getConversationId();
        $ttl = $ttl ?? self::TTL;

        try {
            Redis::setex($key, $ttl, json_encode($context->toArray()));

            // Also store in user's conversation list
            $userKey = self::PREFIX . 'user:' . $context->getUserId();
            Redis::zadd($userKey, time(), $context->getConversationId());

            // Trim old conversations (keep last 100)
            Redis::zremrangebyrank($userKey, 0, -101);

            Log::info('Conversation stored', [
                'conversation_id' => $context->getConversationId(),
                'user_id'         => $context->getUserId(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to store conversation', [
                'conversation_id' => $context->getConversationId(),
                'error'           => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve conversation context.
     */
    public function retrieve(string $conversationId): ?ConversationContext
    {
        $key = self::PREFIX . $conversationId;

        try {
            $data = Redis::get($key);

            if (! $data) {
                return null;
            }

            $array = json_decode($data, true);

            return new ConversationContext(
                $array['conversation_id'],
                $array['user_id'],
                $array['messages'] ?? [],
                $array['system_prompt'] ?? [],
                $array['metadata'] ?? []
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve conversation', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update conversation with new message.
     */
    public function addMessage(string $conversationId, string $role, string $content): void
    {
        $context = $this->retrieve($conversationId);

        if (! $context) {
            throw new RuntimeException("Conversation {$conversationId} not found");
        }

        $updatedContext = $context->withMessage($role, $content);
        $this->store($updatedContext);
    }

    /**
     * Get user's conversation history.
     */
    public function getUserConversations(string $userId, int $limit = 10): array
    {
        $userKey = self::PREFIX . 'user:' . $userId;

        try {
            // Get recent conversation IDs
            $conversationIds = Redis::zrevrange($userKey, 0, $limit - 1);

            $conversations = [];
            foreach ($conversationIds as $id) {
                $context = $this->retrieve($id);
                if ($context) {
                    $messages = $context->getMessages();
                    $conversations[] = [
                        'conversation_id' => $id,
                        'last_message'    => end($messages),
                        'message_count'   => count($messages),
                        'metadata'        => $context->getMetadata(),
                    ];
                }
            }

            return $conversations;
        } catch (Exception $e) {
            Log::error('Failed to get user conversations', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Delete conversation.
     */
    public function delete(string $conversationId): void
    {
        $key = self::PREFIX . $conversationId;

        try {
            $context = $this->retrieve($conversationId);

            if ($context) {
                // Remove from user's list
                $userKey = self::PREFIX . 'user:' . $context->getUserId();
                Redis::zrem($userKey, $conversationId);
            }

            // Delete conversation data
            Redis::del($key);

            Log::info('Conversation deleted', ['conversation_id' => $conversationId]);
        } catch (Exception $e) {
            Log::error('Failed to delete conversation', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear all conversations for a user.
     */
    public function clearUserConversations(string $userId): void
    {
        $userKey = self::PREFIX . 'user:' . $userId;

        try {
            // Get all conversation IDs
            $conversationIds = Redis::zrange($userKey, 0, -1);

            // Delete each conversation
            foreach ($conversationIds as $id) {
                $this->delete($id);
            }

            // Clear the user's list
            Redis::del($userKey);

            Log::info('User conversations cleared', ['user_id' => $userId]);
        } catch (Exception $e) {
            Log::error('Failed to clear user conversations', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Search conversations by content.
     */
    public function searchConversations(string $userId, string $searchTerm): array
    {
        $conversations = $this->getUserConversations($userId, 100);
        $results = [];

        foreach ($conversations as $conv) {
            $context = $this->retrieve($conv['conversation_id']);
            if (! $context) {
                continue;
            }

            // Search in messages
            foreach ($context->getMessages() as $message) {
                if (stripos($message['content'], $searchTerm) !== false) {
                    $results[] = [
                        'conversation_id' => $conv['conversation_id'],
                        'matched_message' => $message,
                        'context'         => array_slice($context->getMessages(), -3), // Last 3 messages
                    ];
                    break;
                }
            }
        }

        return $results;
    }
}
