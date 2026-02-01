<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

use App\Domain\AI\ValueObjects\LLMResponse;

/**
 * Interface for LLM (Large Language Model) providers.
 */
interface LLMProviderInterface
{
    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Get the default model for this provider.
     */
    public function getDefaultModel(): string;

    /**
     * Get available models for this provider.
     *
     * @return array<string>
     */
    public function getAvailableModels(): array;

    /**
     * Send a completion request.
     *
     * @param string $systemPrompt
     * @param string $userMessage
     * @param array<string, mixed> $options
     * @return LLMResponse
     */
    public function complete(string $systemPrompt, string $userMessage, array $options = []): LLMResponse;

    /**
     * Send a chat completion request with message history.
     *
     * @param array<array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return LLMResponse
     */
    public function chat(array $messages, array $options = []): LLMResponse;

    /**
     * Check if the provider is available and configured.
     */
    public function isAvailable(): bool;

    /**
     * Get pricing per 1K tokens for a model.
     *
     * @param string $model
     * @return array{input: float, output: float}
     */
    public function getPricing(string $model): array;

    /**
     * Get the maximum context length for a model.
     *
     * @param string $model
     * @return int
     */
    public function getMaxContextLength(string $model): int;

    /**
     * Check if the provider supports streaming responses.
     */
    public function supportsStreaming(): bool;

    /**
     * Check if the provider supports function/tool calling.
     */
    public function supportsToolCalling(): bool;
}
