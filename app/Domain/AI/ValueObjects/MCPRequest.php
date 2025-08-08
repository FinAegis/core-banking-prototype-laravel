<?php

declare(strict_types=1);

namespace App\Domain\AI\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

class MCPRequest implements Arrayable
{
    private string $id;

    private string $method;

    private array $params;

    private ?string $conversationId;

    private ?string $userId;

    private array $metadata;

    public function __construct(
        string $method,
        array $params = [],
        ?string $conversationId = null,
        ?string $userId = null,
        array $metadata = []
    ) {
        $this->id = Str::uuid()->toString();
        $this->method = $method;
        $this->params = $params;
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->metadata = $metadata;
    }

    public static function create(string $method, array $params = []): self
    {
        return new self($method, $params);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['method'] ?? '',
            $data['params'] ?? [],
            $data['conversation_id'] ?? null,
            $data['user_id'] ?? null,
            $data['metadata'] ?? []
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getConversationId(): ?string
    {
        return $this->conversationId;
    }

    public function setConversationId(string $conversationId): self
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;

        return $this;
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

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'method'          => $this->method,
            'params'          => $this->params,
            'conversation_id' => $this->conversationId,
            'user_id'         => $this->userId,
            'metadata'        => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray()) ?: '{}';
    }
}
