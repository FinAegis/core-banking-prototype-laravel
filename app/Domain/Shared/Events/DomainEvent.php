<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

use Illuminate\Support\Str;
use ReflectionClass;

abstract class DomainEvent
{
    public readonly string $eventId;

    public readonly string $occurredAt;

    public readonly ?string $correlationId;

    public readonly ?string $causationId;

    public readonly array $metadata;

    public function __construct()
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = now()->toIso8601String();
        $this->correlationId = request()->header('X-Correlation-ID');
        $this->causationId = request()->header('X-Causation-ID');
        $this->metadata = [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ];
    }

    abstract public function getAggregateId(): string;

    abstract public function getAggregateType(): string;

    public function getEventType(): string
    {
        return class_basename(static::class);
    }

    public function toArray(): array
    {
        return [
            'event_id'       => $this->eventId,
            'event_type'     => $this->getEventType(),
            'aggregate_id'   => $this->getAggregateId(),
            'aggregate_type' => $this->getAggregateType(),
            'occurred_at'    => $this->occurredAt,
            'correlation_id' => $this->correlationId,
            'causation_id'   => $this->causationId,
            'metadata'       => $this->metadata,
            'payload'        => $this->getPayload(),
        ];
    }

    protected function getPayload(): array
    {
        $payload = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isPublic() && ! $property->isStatic()) {
                $name = $property->getName();
                if (! in_array($name, ['eventId', 'occurredAt', 'correlationId', 'causationId', 'metadata'])) {
                    $payload[$name] = $this->$name;
                }
            }
        }

        return $payload;
    }
}
