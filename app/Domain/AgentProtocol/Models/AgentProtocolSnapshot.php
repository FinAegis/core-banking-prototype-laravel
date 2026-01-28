<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Models;

use App\Domain\Shared\EventSourcing\TenantAwareSnapshot;

class AgentProtocolSnapshot extends TenantAwareSnapshot
{
    protected $table = 'agent_protocol_snapshots';
}
