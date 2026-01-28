<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class AgentProtocolEvent extends TenantAwareStoredEvent
{
    protected $table = 'agent_protocol_events';
}
