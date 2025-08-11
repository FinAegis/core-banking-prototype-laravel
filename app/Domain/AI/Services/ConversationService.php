<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ConversationService
{
    /**
     * Get or create a conversation.
     */
    public function getOrCreate(string $conversationId, int $userId): array
    {
        $key = "conversation:{$conversationId}";

        return Cache::remember($key, 3600, function () use ($conversationId, $userId) {
            return [
                'id'         => $conversationId,
                'user_id'    => $userId,
                'messages'   => [],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get user conversations.
     */
    public function getUserConversations(int $userId, int $limit = 10): array
    {
        // Demo implementation - returns sample conversations
        return [
            [
                'id'            => Str::uuid()->toString(),
                'title'         => 'Account Balance Inquiry',
                'last_message'  => 'Your balance is $12,456.78',
                'message_count' => 3,
                'created_at'    => now()->subHours(2)->toIso8601String(),
                'updated_at'    => now()->subHours(2)->toIso8601String(),
            ],
            [
                'id'            => Str::uuid()->toString(),
                'title'         => 'Transfer Request',
                'last_message'  => 'Transfer of $500 completed successfully',
                'message_count' => 5,
                'created_at'    => now()->subDays(1)->toIso8601String(),
                'updated_at'    => now()->subDays(1)->toIso8601String(),
            ],
        ];
    }

    /**
     * Get a specific conversation.
     */
    public function getConversation(string $conversationId, int $userId): ?array
    {
        $key = "conversation:{$conversationId}";
        $conversation = Cache::get($key);

        if ($conversation && $conversation['user_id'] === $userId) {
            return $conversation;
        }

        // Demo conversation
        return [
            'id'       => $conversationId,
            'messages' => [
                [
                    'role'      => 'user',
                    'content'   => 'What is my account balance?',
                    'timestamp' => now()->subMinutes(5)->toIso8601String(),
                ],
                [
                    'role'      => 'assistant',
                    'content'   => 'Your current account balance is $12,456.78.',
                    'timestamp' => now()->subMinutes(4)->toIso8601String(),
                ],
            ],
            'context'    => [],
            'created_at' => now()->subMinutes(5)->toIso8601String(),
        ];
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(string $conversationId, int $userId): bool
    {
        $key = "conversation:{$conversationId}";
        $conversation = Cache::get($key);

        if ($conversation && $conversation['user_id'] === $userId) {
            Cache::forget($key);

            return true;
        }

        return false;
    }
}
