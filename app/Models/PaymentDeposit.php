<?php

namespace App\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class PaymentDeposit extends EloquentStoredEvent
{
    public $table = 'payment_deposits';
}