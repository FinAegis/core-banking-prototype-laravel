<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustodianHealthChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $custodian,
        public readonly string $previousStatus,
        public readonly string $newStatus,
        public readonly \DateTimeInterface $timestamp
    ) {}
}