<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Events;

use App\Domain\Shared\Enums\EventQueues;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class StablecoinOperationCreated extends ShouldBeStored
{
    public string $queue = EventQueues::TRANSACTIONS->value;

    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly string $stablecoin,
        public readonly int $amount,
        public readonly ?string $collateralAsset,
        public readonly ?int $collateralAmount,
        public readonly ?int $collateralReturn,
        public readonly ?string $sourceAccount,
        public readonly ?string $recipientAccount,
        public readonly string $operatorUuid,
        public readonly ?string $positionUuid,
        public readonly string $reason,
        public readonly string $status,
        public readonly array $metadata = []
    ) {}
}