<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\LLM;

use App\Domain\AI\ValueObjects\ConversationContext;
use App\Domain\AI\ValueObjects\LLMResponse;
use Generator;

interface LLMProviderInterface
{
    /**
     * Send a message to the LLM and get a response.
     */
    public function chat(
        string $message,
        ConversationContext $context,
        array $options = []
    ): LLMResponse;

    /**
     * Stream a response from the LLM.
     *
     * @return Generator<string>
     */
    public function stream(
        string $message,
        ConversationContext $context,
        array $options = []
    ): Generator;

    /**
     * Generate embeddings for text.
     *
     * @return array<float>
     */
    public function generateEmbeddings(string $text): array;

    /**
     * Check if the provider is available.
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Get usage statistics.
     */
    public function getUsageStats(): array;
}
