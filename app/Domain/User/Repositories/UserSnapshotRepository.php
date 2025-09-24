<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\Models\UserSnapshot;
use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;

class UserSnapshotRepository extends EloquentSnapshotRepository
{
    protected string $snapshotModel = UserSnapshot::class;
}
