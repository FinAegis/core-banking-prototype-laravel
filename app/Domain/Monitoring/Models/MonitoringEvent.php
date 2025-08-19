<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class MonitoringEvent extends EloquentStoredEvent
{
    public $table = 'monitoring_events';

    public $guarded = [];
}
