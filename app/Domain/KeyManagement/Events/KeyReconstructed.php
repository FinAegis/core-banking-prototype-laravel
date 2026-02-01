<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KeyReconstructed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string> $shardsUsed
     */
    public function __construct(
        public readonly string $userUuid,
        public readonly string $purpose,
        public readonly array $shardsUsed
    ) {
    }
}
