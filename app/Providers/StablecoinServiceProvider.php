<?php

namespace App\Providers;

use App\Domain\Stablecoin\Contracts\CollateralServiceInterface;
use App\Domain\Stablecoin\Contracts\LiquidationServiceInterface;
use App\Domain\Stablecoin\Contracts\StabilityMechanismServiceInterface;
use App\Domain\Stablecoin\Contracts\StablecoinIssuanceServiceInterface;
use App\Domain\Stablecoin\Projectors\StablecoinProjector;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Stablecoin\Services\OracleAggregator;
use App\Domain\Stablecoin\Services\StabilityMechanismService;
use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use Illuminate\Support\ServiceProvider;
use Spatie\EventSourcing\Facades\Projectionist;

class StablecoinServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind interfaces to implementations
        $this->app->singleton(CollateralServiceInterface::class, CollateralService::class);
        $this->app->singleton(LiquidationServiceInterface::class, LiquidationService::class);
        $this->app->singleton(StabilityMechanismServiceInterface::class, StabilityMechanismService::class);
        $this->app->singleton(StablecoinIssuanceServiceInterface::class, StablecoinIssuanceService::class);
        
        // Register concrete services (for backward compatibility)
        $this->app->singleton(CollateralService::class);
        $this->app->singleton(LiquidationService::class);
        $this->app->singleton(OracleAggregator::class);
        $this->app->singleton(StabilityMechanismService::class);
        $this->app->singleton(StablecoinIssuanceService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register projectors
        Projectionist::addProjectors([
            StablecoinProjector::class,
        ]);
    }
}