<?php

declare(strict_types=1);

namespace App\Domain\SMS\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SmsFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $messageId,
        public readonly string $providerMessageId,
        public readonly string $reason,
    ) {
    }
}
