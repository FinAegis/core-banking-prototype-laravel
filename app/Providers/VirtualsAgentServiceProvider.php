<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\VirtualsAgent\Services\AgentOnboardingService;
use App\Domain\VirtualsAgent\Services\VirtualsAgentService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the VirtualsAgent domain.
 *
 * Registers agent onboarding and payment orchestration services
 * as singletons so they share spending-limit and payment-service
 * instances within each request lifecycle.
 */
class VirtualsAgentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AgentOnboardingService::class);
        $this->app->singleton(VirtualsAgentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
