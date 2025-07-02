<?php

namespace App\Domain\Batch\DataObjects;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

class BatchJob extends Data
{
    public function __construct(
        public string $uuid,
        public string $userUuid,
        public string $name,
        public string $type, // transfer, payment, conversion
        public array $items,
        public ?string $scheduledAt = null,
        public array $metadata = []
    ) {}
    
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
}