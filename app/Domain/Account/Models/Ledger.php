<?php

namespace App\Domain\Account\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class Ledger extends TenantAwareStoredEvent
{
    public $table = 'ledgers';
}
