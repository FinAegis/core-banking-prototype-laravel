<?php

namespace App\Providers;

use App\Domain\Exchange\Jobs\CheckArbitrageOpportunitiesJob;
use App\Domain\Exchange\Services\ExternalExchangeConnectorRegistry;
use App\Domain\Exchange\Services\ExternalLiquidityService;
use Illuminate\Support\ServiceProvider;

class ExternalExchangeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the connector registry as a singleton
        $this->app->singleton(ExternalExchangeConnectorRegistry::class, function ($app) {
            return new ExternalExchangeConnectorRegistry();
        });

        // Register the liquidity service
        $this->app->singleton(ExternalLiquidityService::class, function ($app) {
            return new ExternalLiquidityService(
                $app->make(ExternalExchangeConnectorRegistry::class),
                $app->make(\App\Domain\Exchange\Services\ExchangeService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Schedule arbitrage checking if enabled
        if (config('trading.arbitrage.enabled', false)) {
            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                
                $interval = config('trading.arbitrage.check_interval', 60);
                
                $schedule->job(new CheckArbitrageOpportunitiesJob())
                    ->everyMinutes($interval / 60)
                    ->withoutOverlapping()
                    ->onOneServer()
                    ->runInBackground();
            });
        }
    }
}