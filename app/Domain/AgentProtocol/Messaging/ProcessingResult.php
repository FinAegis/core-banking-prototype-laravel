<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Messaging;

/**
 * Result of processing an incoming A2A message.
 */
final class ProcessingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly bool $duplicate = false,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Create a successful result.
     */
    public static function success(mixed $data = null, array $metadata = []): self
    {
        return new self(
            success: true,
            data: $data,
            metadata: $metadata
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $error, array $metadata = []): self
    {
        return new self(
            success: false,
            error: $error,
            metadata: $metadata
        );
    }

    /**
     * Create a duplicate delivery result.
     */
    public static function duplicate(): self
    {
        return new self(
            success: true,
            duplicate: true
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'   => $this->success,
            'data'      => $this->data,
            'error'     => $this->error,
            'duplicate' => $this->duplicate,
            'metadata'  => $this->metadata,
        ];
    }
}
