<?php

namespace App\Domain\Payment\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class PaymentDeposit extends TenantAwareStoredEvent
{
    public $table = 'payment_deposits';
}
