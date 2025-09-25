<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use App\Domain\AgentProtocol\Models\AgentProtocolEvent;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

class AgentEventRepository extends EloquentStoredEventRepository
{
    protected string $storedEventModel = AgentProtocolEvent::class;
}
