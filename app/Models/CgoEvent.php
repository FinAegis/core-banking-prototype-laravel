<?php

namespace App\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class CgoEvent extends EloquentStoredEvent
{
    public $table = 'cgo_events';
}