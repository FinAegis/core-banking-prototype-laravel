<?php

namespace App\Models;

use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

class PaymentWithdrawal extends EloquentStoredEvent
{
    public $table = 'payment_withdrawals';
}