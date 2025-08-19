<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Repositories;

use App\Domain\Monitoring\Models\MonitoringSnapshot;
use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;

final class MonitoringSnapshotRepository extends EloquentSnapshotRepository
{
    protected string $snapshotModel = MonitoringSnapshot::class;
}
