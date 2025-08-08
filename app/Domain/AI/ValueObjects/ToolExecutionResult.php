<?php

declare(strict_types=1);

namespace App\Domain\AI\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

class ToolExecutionResult implements Arrayable
{
    private bool $success;

    private array $data;

    private ?string $error;

    private int $durationMs;

    private bool $cached;

    private array $metadata;

    public function __construct(
        bool $success,
        array $data = [],
        ?string $error = null,
        int $durationMs = 0,
        bool $cached = false,
        array $metadata = []
    ) {
        $this->success = $success;
        $this->data = $data;
        $this->error = $error;
        $this->durationMs = $durationMs;
        $this->cached = $cached;
        $this->metadata = $metadata;
    }

    public static function success(array $data, int $durationMs = 0): self
    {
        return new self(true, $data, null, $durationMs);
    }

    public static function failure(string $error, int $durationMs = 0): self
    {
        return new self(false, [], $error, $durationMs);
    }

    public static function fromCache(array $data): self
    {
        return new self(true, $data, null, 0, true);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return ! $this->success;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function wasCached(): bool
    {
        return $this->cached;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    public function getPerformanceCategory(): string
    {
        return match (true) {
            $this->durationMs < 100  => 'excellent',
            $this->durationMs < 500  => 'good',
            $this->durationMs < 1000 => 'acceptable',
            $this->durationMs < 3000 => 'slow',
            default                  => 'critical',
        };
    }

    public function toArray(): array
    {
        return [
            'success'     => $this->success,
            'data'        => $this->data,
            'error'       => $this->error,
            'duration_ms' => $this->durationMs,
            'cached'      => $this->cached,
            'performance' => $this->getPerformanceCategory(),
            'metadata'    => $this->metadata,
        ];
    }
}
