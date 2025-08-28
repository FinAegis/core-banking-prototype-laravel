<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events;

use App\Domain\Account\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealTimeAlertGenerated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public array $alertData
    ) {
    }
}
