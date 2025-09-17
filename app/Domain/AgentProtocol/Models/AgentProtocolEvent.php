<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class AgentProtocolEvent extends EloquentStoredEvent
{
    protected $table = 'agent_protocol_events';
}
