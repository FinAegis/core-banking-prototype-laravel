<?php

declare(strict_types=1);

namespace App\Domain\AI\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class LLMResponse implements Arrayable
{
    public function __construct(
        private readonly string $content,
        private readonly string $model,
        private readonly int $promptTokens,
        private readonly int $completionTokens,
        private readonly float $temperature,
        private readonly array $metadata = []
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
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

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'content'           => $this->content,
            'model'             => $this->model,
            'prompt_tokens'     => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens'      => $this->getTotalTokens(),
            'temperature'       => $this->temperature,
            'metadata'          => $this->metadata,
        ];
    }
}
