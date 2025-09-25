<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;
use App\Domain\AgentProtocol\Models\AgentProtocolEvent;

class AgentEventRepository extends EloquentStoredEventRepository
{
    protected string $storedEventModel = AgentProtocolEvent::class;
}
