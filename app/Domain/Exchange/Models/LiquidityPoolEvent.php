<?php

namespace App\Domain\Exchange\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class LiquidityPoolEvent extends TenantAwareStoredEvent
{
    public $table = 'liquidity_pool_events';
}
