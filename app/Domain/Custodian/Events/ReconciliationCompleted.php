<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReconciliationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $date,
        public readonly array $results,
        public readonly array $discrepancies
    ) {}
}