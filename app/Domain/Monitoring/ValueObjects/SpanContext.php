<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\ValueObjects;

class SpanContext
{
    public function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId = null,
        public readonly array $baggage = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'trace_id'       => $this->traceId,
            'span_id'        => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'baggage'        => $this->baggage,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['trace_id'],
            $data['span_id'],
            $data['parent_span_id'] ?? null,
            $data['baggage'] ?? []
        );
    }

    public function withBaggage(string $key, mixed $value): self
    {
        $baggage = $this->baggage;
        $baggage[$key] = $value;

        return new self(
            $this->traceId,
            $this->spanId,
            $this->parentSpanId,
            $baggage
        );
    }
}
