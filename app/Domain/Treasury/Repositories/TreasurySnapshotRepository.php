<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Repositories;

use App\Domain\Treasury\Models\TreasurySnapshot;
use InvalidArgumentException;
use Spatie\EventSourcing\Snapshots\EloquentSnapshot;
use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;

final class TreasurySnapshotRepository extends EloquentSnapshotRepository
{
    public function __construct(
        protected string $snapshotModel = TreasurySnapshot::class
    ) {
        if (! new $this->snapshotModel() instanceof EloquentSnapshot) {
            throw new InvalidArgumentException("The class {$this->snapshotModel} must extend EloquentSnapshot");
        }
    }
}
