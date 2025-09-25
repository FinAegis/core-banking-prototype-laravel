<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;
use App\Domain\AgentProtocol\Models\AgentProtocolSnapshot;

class AgentSnapshotRepository extends EloquentSnapshotRepository
{
    protected string $snapshotModel = AgentProtocolSnapshot::class;
}
