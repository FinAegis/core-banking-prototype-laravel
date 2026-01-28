<?php

namespace App\Domain\Stablecoin\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class StablecoinEvent extends TenantAwareStoredEvent
{
    public $table = 'stablecoin_events';
}
