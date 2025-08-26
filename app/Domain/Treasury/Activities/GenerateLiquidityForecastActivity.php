<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities;

use App\Domain\Treasury\Services\LiquidityForecastingService;
use Workflow\Activity;

/**
 * Activity for generating liquidity forecast.
 */
class GenerateLiquidityForecastActivity
{
    public function __construct(
        private readonly LiquidityForecastingService $forecastingService
    ) {
    }

    #[Activity]
    public function execute(string $treasuryId, int $forecastDays): array
    {
        return $this->forecastingService->generateForecast(
            $treasuryId,
            $forecastDays
        );
    }
}
