<?php

declare(strict_types=1);

namespace App\Domain\AI\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class ConversationContext implements Arrayable
{
    /**
     * @param array<array{role: string, content: string}> $messages
     */
    public function __construct(
        private readonly string $conversationId,
        private readonly string $userId,
        private readonly array $messages = [],
        private readonly array $systemPrompt = [],
        private readonly array $metadata = []
    ) {
    }

    public function getConversationId(): string
    {
        return $this->conversationId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getSystemPrompt(): array
    {
        return $this->systemPrompt;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function withMessage(string $role, string $content): self
    {
        $messages = $this->messages;
        $messages[] = ['role' => $role, 'content' => $content];

        return new self(
            $this->conversationId,
            $this->userId,
            $messages,
            $this->systemPrompt,
            $this->metadata
        );
    }

    public function withSystemPrompt(string $prompt): self
    {
        return new self(
            $this->conversationId,
            $this->userId,
            $this->messages,
            ['role' => 'system', 'content' => $prompt],
            $this->metadata
        );
    }

    public function toArray(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id'         => $this->userId,
            'messages'        => $this->messages,
            'system_prompt'   => $this->systemPrompt,
            'metadata'        => $this->metadata,
        ];
    }
}
