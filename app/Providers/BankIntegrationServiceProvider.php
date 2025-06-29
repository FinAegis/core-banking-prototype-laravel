<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Banking\Contracts\IBankIntegrationService;
use App\Domain\Banking\Services\BankIntegrationService;
use App\Domain\Banking\Services\BankHealthMonitor;
use App\Domain\Banking\Services\BankRoutingService;
use App\Domain\Custodian\Connectors\PayseraConnector;
use App\Domain\Custodian\Connectors\DeutscheBankConnector;
use App\Domain\Custodian\Connectors\SantanderConnector;
use App\Domain\Banking\Connectors\BankConnectorAdapter;

class BankIntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register core services
        $this->app->singleton(BankHealthMonitor::class);
        $this->app->singleton(BankRoutingService::class);
        
        // Register bank integration service
        $this->app->singleton(IBankIntegrationService::class, function ($app) {
            $service = new BankIntegrationService(
                $app->make(BankHealthMonitor::class),
                $app->make(BankRoutingService::class)
            );
            
            // Register bank connectors
            $this->registerBankConnectors($service);
            
            return $service;
        });
    }
    
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Schedule health checks
        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            
            $schedule->call(function () {
                $monitor = app(BankHealthMonitor::class);
                $monitor->checkAllBanks();
            })->everyFiveMinutes()->name('bank-health-check')->withoutOverlapping();
        });
    }
    
    /**
     * Register all bank connectors
     */
    private function registerBankConnectors(IBankIntegrationService $service): void
    {
        // Paysera
        if (config('services.banks.paysera.enabled', true)) {
            $payseraAdapter = new BankConnectorAdapter(
                new PayseraConnector([
                    'name' => 'Paysera',
                    'client_id' => config('services.banks.paysera.client_id'),
                    'client_secret' => config('services.banks.paysera.client_secret'),
                    'base_url' => config('services.banks.paysera.base_url', 'https://bank.paysera.com/rest/v1'),
                ]),
                'PAYSERA',
                'Paysera'
            );
            $service->registerConnector('PAYSERA', $payseraAdapter);
        }
        
        // Deutsche Bank
        if (config('services.banks.deutsche.enabled', true)) {
            $deutscheAdapter = new BankConnectorAdapter(
                new DeutscheBankConnector([
                    'name' => 'Deutsche Bank',
                    'client_id' => config('services.banks.deutsche.client_id'),
                    'client_secret' => config('services.banks.deutsche.client_secret'),
                    'base_url' => config('services.banks.deutsche.base_url', 'https://api.db.com/v2'),
                ]),
                'DEUTSCHE',
                'Deutsche Bank'
            );
            $service->registerConnector('DEUTSCHE', $deutscheAdapter);
        }
        
        // Santander
        if (config('services.banks.santander.enabled', true)) {
            $santanderAdapter = new BankConnectorAdapter(
                new SantanderConnector([
                    'name' => 'Santander',
                    'client_id' => config('services.banks.santander.client_id'),
                    'client_secret' => config('services.banks.santander.client_secret'),
                    'base_url' => config('services.banks.santander.base_url', 'https://api.santander.com/v2'),
                ]),
                'SANTANDER',
                'Santander'
            );
            $service->registerConnector('SANTANDER', $santanderAdapter);
        }
        
        // Additional banks can be registered here
        // Revolut, Wise, etc.
    }
}