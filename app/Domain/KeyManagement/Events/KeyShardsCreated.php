<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KeyShardsCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userUuid,
        public readonly string $keyVersion
    ) {
    }
}
