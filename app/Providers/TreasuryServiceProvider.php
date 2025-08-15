<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Treasury\Repositories\TreasuryEventRepository;
use App\Domain\Treasury\Repositories\TreasurySnapshotRepository;
use App\Domain\Treasury\Sagas\RiskManagementSaga;
use App\Domain\Treasury\Services\RegulatoryReportingService;
use App\Domain\Treasury\Services\YieldOptimizationService;
use Illuminate\Support\ServiceProvider;

class TreasuryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Treasury event repository
        $this->app->bind('treasury.event-repository', TreasuryEventRepository::class);
        $this->app->singleton(TreasuryEventRepository::class);

        // Register Treasury snapshot repository
        $this->app->bind('treasury.snapshot-repository', TreasurySnapshotRepository::class);
        $this->app->singleton(TreasurySnapshotRepository::class);

        // Register Treasury services
        $this->app->singleton(YieldOptimizationService::class);
        $this->app->singleton(RegulatoryReportingService::class);

        // Register Treasury saga
        $this->app->singleton(RiskManagementSaga::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Treasury domain is ready for operations
    }
}
