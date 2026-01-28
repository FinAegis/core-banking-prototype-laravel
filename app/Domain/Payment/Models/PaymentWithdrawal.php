<?php

namespace App\Domain\Payment\Models;

use App\Domain\Shared\EventSourcing\TenantAwareStoredEvent;

class PaymentWithdrawal extends TenantAwareStoredEvent
{
    public $table = 'payment_withdrawals';
}
