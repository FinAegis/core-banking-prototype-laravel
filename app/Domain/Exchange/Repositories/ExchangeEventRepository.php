<?php

namespace App\Domain\Exchange\Repositories;

use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

class ExchangeEventRepository extends EloquentStoredEventRepository
{
    protected string $storedEventTable = 'exchange_events';
    protected string $storedEventSnapshotTable = 'exchange_event_snapshots';
}