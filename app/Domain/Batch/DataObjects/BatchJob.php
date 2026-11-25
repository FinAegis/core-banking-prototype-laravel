<?php

declare(strict_types=1);

namespace App\Domain\Batch\DataObjects;

use Illuminate\Support\Str;

/**
 * The batch as recorded in the BatchJobCreated event.
 *
 * A plain object with only its own fields, so the stored event can be read
 * back. As a Spatie Data object it also serialized Data's internal fields
 * (wrap, dataContext), which the event serializer then failed to read, so no
 * batch aggregate could ever be retrieved.
 */
class BatchJob
{
    public function __construct(
        public string $uuid,
        public string $userUuid,
        public string $name,
        public string $type, // transfer, payment
        public array $items,
        public ?string $scheduledAt = null,
        public array $metadata = []
    ) {
    }

    public static function create(
        string $userUuid,
        string $name,
        string $type,
        array $items,
        ?string $scheduledAt = null,
        array $metadata = []
    ): self {
        return new self(
            uuid: (string) Str::uuid(),
            userUuid: $userUuid,
            name: $name,
            type: $type,
            items: $items,
            scheduledAt: $scheduledAt,
            metadata: $metadata
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: (string) ($data['uuid'] ?? ''),
            userUuid: (string) ($data['userUuid'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            items: is_array($data['items'] ?? null) ? $data['items'] : [],
            scheduledAt: isset($data['scheduledAt']) ? (string) $data['scheduledAt'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    /**
     * @return array{uuid: string, userUuid: string, name: string, type: string, items: array<mixed>, scheduledAt: string|null, metadata: array<mixed>}
     */
    public function toArray(): array
    {
        return [
            'uuid'        => $this->uuid,
            'userUuid'    => $this->userUuid,
            'name'        => $this->name,
            'type'        => $this->type,
            'items'       => $this->items,
            'scheduledAt' => $this->scheduledAt,
            'metadata'    => $this->metadata,
        ];
    }
}
