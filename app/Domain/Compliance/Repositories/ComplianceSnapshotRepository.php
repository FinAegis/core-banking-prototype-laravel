<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories;

use App\Domain\Compliance\Models\ComplianceSnapshot;
use InvalidArgumentException;
use Spatie\EventSourcing\Snapshots\EloquentSnapshot;
use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;

final class ComplianceSnapshotRepository extends EloquentSnapshotRepository
{
    public function __construct(
        protected string $snapshotModel = ComplianceSnapshot::class
    ) {
        if (! new $this->snapshotModel() instanceof EloquentSnapshot) {
            throw new InvalidArgumentException("The class {$this->snapshotModel} must extend EloquentSnapshot");
        }
    }
}
