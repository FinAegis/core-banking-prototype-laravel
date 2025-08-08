<?php

declare(strict_types=1);

namespace App\Domain\AI\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class MCPResponse implements Arrayable, Jsonable
{
    private bool $success;

    private array $data;

    private ?string $error;

    private int $code;

    private array $metadata;

    public function __construct(
        bool $success,
        array $data = [],
        ?string $error = null,
        int $code = 200,
        array $metadata = []
    ) {
        $this->success = $success;
        $this->data = $data;
        $this->error = $error;
        $this->code = $code;
        $this->metadata = $metadata;
    }

    public static function success(array $data, array $metadata = []): self
    {
        return new self(true, $data, null, 200, $metadata);
    }

    public static function error(string $error, int $code = 500, array $metadata = []): self
    {
        return new self(false, [], $error, $code, $metadata);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isError(): bool
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

    public function getCode(): int
    {
        return $this->code;
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
        $response = [
            'success' => $this->success,
            'code'    => $this->code,
        ];

        if ($this->success) {
            $response['data'] = $this->data;
        } else {
            $response['error'] = $this->error;
        }

        if (! empty($this->metadata)) {
            $response['metadata'] = $this->metadata;
        }

        return $response;
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '{}';
    }

    public function toHttpResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->toArray(), $this->code);
    }
}
