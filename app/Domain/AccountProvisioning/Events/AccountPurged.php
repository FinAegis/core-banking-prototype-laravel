<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Events;

final readonly class AccountPurged
{
    public function __construct(
        public int $userId,
        public ?int $operatorId,
    ) {
    }
}
