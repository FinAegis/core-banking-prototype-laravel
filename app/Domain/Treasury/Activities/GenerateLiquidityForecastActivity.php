<?php

declare(strict_types=1);

namespace App\Domain\Treasury\Activities;

use App\Domain\Treasury\Services\LiquidityForecastingService;
use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

/**
 * Activity for generating liquidity forecast.
 */
#[ActivityInterface]
class GenerateLiquidityForecastActivity
{
    public function __construct(
        private readonly LiquidityForecastingService $forecastingService
    ) {
    }

    #[ActivityMethod]
    public function execute(string $treasuryId, int $forecastDays): array
    {
        return $this->forecastingService->generateForecast(
            $treasuryId,
            $forecastDays
        );
    }
}
