<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use App\Domain\AgentProtocol\Models\AgentProtocolSnapshot;
use Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository;

class AgentProtocolSnapshotRepository extends EloquentSnapshotRepository
{
    protected string $snapshotModel = AgentProtocolSnapshot::class;
}
