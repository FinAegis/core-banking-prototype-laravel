<?php

declare(strict_types=1);

namespace App\Domain\SMS\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsSent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array{rail?: string, payment_id?: string, receipt_id?: string} $paymentMeta
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $to,
        public readonly int $parts,
        public readonly string $priceUsdc,
        public readonly array $paymentMeta = [],
    ) {
    }
}
