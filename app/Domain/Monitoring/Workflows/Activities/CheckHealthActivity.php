<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Workflows\Activities;

use App\Domain\Monitoring\Services\HealthChecker;

class CheckHealthActivity
{
    private HealthChecker $healthChecker;

    public function __construct(HealthChecker $healthChecker)
    {
        $this->healthChecker = $healthChecker;
    }

    public function execute(): array
    {
        return $this->healthChecker->check();
    }
}
