<?php

declare(strict_types=1);

namespace App\Domain\Performance\Repositories;

use App\Domain\Performance\Models\PerformanceSnapshot;
use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;

class PerformanceSnapshotRepository extends EloquentSnapshotRepository
{
    protected string $snapshotModel = PerformanceSnapshot::class;
}
