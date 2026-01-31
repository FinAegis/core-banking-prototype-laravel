<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class MobileEvent extends TenantAwareStoredEvent
{
    public $table = 'mobile_events';
}
