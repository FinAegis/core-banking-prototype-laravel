<?php

namespace App\Providers;

use App\Domain\Exchange\Projectors\OrderBookProjector;
use App\Domain\Exchange\Projectors\OrderProjector;
use App\Domain\Exchange\Repositories\ExchangeEventRepository;
use App\Domain\Exchange\Services\ExchangeService;
use App\Domain\Exchange\Services\FeeCalculator;
use Illuminate\Support\ServiceProvider;
use Spatie\EventSourcing\Facades\Projectionist;

class ExchangeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register exchange services
        $this->app->singleton(ExchangeService::class);
        $this->app->singleton(FeeCalculator::class);
        
        // Register exchange event repository
        $this->app->bind('exchange.event-repository', ExchangeEventRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register projectors
        Projectionist::addProjectors([
            OrderProjector::class,
            OrderBookProjector::class,
        ]);
    }
}