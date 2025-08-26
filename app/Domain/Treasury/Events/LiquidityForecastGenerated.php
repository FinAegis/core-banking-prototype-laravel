<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Events;

use Carbon\Carbon;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class LiquidityForecastGenerated extends ShouldBeStored
{
    public function __construct(
        public readonly string $aggregateRootUuid,
        public readonly array $forecast,
        public readonly array $riskMetrics,
        public readonly Carbon $generatedAt,
        public readonly string $generatedBy
    ) {
    }
}
