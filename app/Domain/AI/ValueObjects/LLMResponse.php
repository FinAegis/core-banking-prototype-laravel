<?php

declare(strict_types=1);

namespace App\Domain\AI\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class LLMResponse implements Arrayable
{
    public function __construct(
        public readonly string $content,
        public readonly string $provider = 'unknown',
        public readonly string $model = 'unknown',
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly string $finishReason = 'stop',
        public readonly array $metadata = [],
        public readonly float $temperature = 0.7
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    public function getFinishReason(): string
    {
        return $this->finishReason;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isError(): bool
    {
        return $this->finishReason === 'error';
    }

    public function toArray(): array
    {
        return [
            'content'           => $this->content,
            'provider'          => $this->provider,
            'model'             => $this->model,
            'prompt_tokens'     => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens'      => $this->getTotalTokens(),
            'finish_reason'     => $this->finishReason,
            'temperature'       => $this->temperature,
            'metadata'          => $this->metadata,
        ];
    }
}
