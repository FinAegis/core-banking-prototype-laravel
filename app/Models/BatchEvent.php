<?php

namespace App\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class BatchEvent extends EloquentStoredEvent
{
    public $table = 'batch_events';
}