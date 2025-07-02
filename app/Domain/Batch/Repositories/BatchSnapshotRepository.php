<?php

declare(strict_types=1);

namespace App\Domain\Batch\Repositories;

use App\Domain\Batch\Snapshots\BatchSnapshot;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\Snapshots\EloquentSnapshot;
use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;

final class BatchSnapshotRepository extends EloquentSnapshotRepository
{
    /**
     * @param string $snapshotModel
     *
     * @throws InvalidEloquentStoredEventModel
     */
    public function __construct(
        protected string $snapshotModel = BatchSnapshot::class
    )
    {
        if (! new $this->snapshotModel() instanceof EloquentSnapshot) {
            throw new InvalidEloquentStoredEventModel("The class {$this->snapshotModel} must extend EloquentSnapshot");
        }
    }
}