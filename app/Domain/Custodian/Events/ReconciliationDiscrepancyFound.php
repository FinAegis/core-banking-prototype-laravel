<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReconciliationDiscrepancyFound
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly array $discrepancy
    ) {}
}