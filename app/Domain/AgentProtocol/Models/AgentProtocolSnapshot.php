<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Spatie\EventSourcing\Snapshots\EloquentSnapshot;

class AgentProtocolSnapshot extends EloquentSnapshot
{
    protected $table = 'agent_protocol_snapshots';
}
