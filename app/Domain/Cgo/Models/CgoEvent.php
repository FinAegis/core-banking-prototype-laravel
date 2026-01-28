<?php

namespace App\Domain\Cgo\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class CgoEvent extends TenantAwareStoredEvent
{
    public $table = 'cgo_events';
}
