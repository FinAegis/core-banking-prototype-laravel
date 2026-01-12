<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Custodian\Connectors\DemoBankConnector;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Exchange\Services\DemoExchangeService;
use App\Domain\Exchange\Services\ExchangeService;
use App\Domain\Lending\Services\DemoLendingService;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Contracts\PayseraDepositServiceInterface;
use App\Domain\Payment\Services\DemoPaymentGatewayService;
use App\Domain\Payment\Services\DemoPaymentService;
use App\Domain\Payment\Services\DemoPayseraDepositService;
use App\Domain\Payment\Services\PaymentGatewayService;
use App\Domain\Payment\Services\PayseraDepositService;
use App\Domain\Payment\Services\ProductionPaymentService;
use App\Domain\Payment\Services\SandboxPaymentService;
use App\Domain\Stablecoin\Services\DemoStablecoinService;
use Illuminate\Support\ServiceProvider;

class DemoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind PaymentServiceInterface to the appropriate implementation based on environment
        if ($this->app->environment('demo')) {
            // Demo environment: Use DemoPaymentService that bypasses external APIs
            $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
            $this->app->bind(PayseraDepositServiceInterface::class, DemoPayseraDepositService::class);
        } elseif (config('demo.sandbox.enabled')) {
            // Sandbox mode: Use SandboxPaymentService with real sandbox APIs
            $this->app->bind(PaymentServiceInterface::class, SandboxPaymentService::class);
            $this->app->bind(PayseraDepositServiceInterface::class, PayseraDepositService::class);
        } else {
            // Production mode: Use ProductionPaymentService
            $this->app->bind(PaymentServiceInterface::class, ProductionPaymentService::class);
            $this->app->bind(PayseraDepositServiceInterface::class, PayseraDepositService::class);
        }

        // Register demo services for Exchange, Lending, and Stablecoin domains
        if ($this->app->environment('demo')) {
            // Exchange service
            $this->app->bind(ExchangeService::class, DemoExchangeService::class);

            // Lending service - bind by name since there may not be an interface yet
            $this->app->bind('lending.service', DemoLendingService::class);

            // Stablecoin service - bind by name since there may not be an interface yet
            $this->app->bind('stablecoin.service', DemoStablecoinService::class);
        }

        // Only activate demo services when in demo environment or sandbox mode
        if ($this->app->environment('demo') || config('demo.sandbox.enabled')) {
            // Replace payment gateway with demo version for demo environment only
            if ($this->app->environment('demo')) {
                $this->app->bind(PaymentGatewayService::class, DemoPaymentGatewayService::class);
            }

            // Register demo bank connector
            $this->app->booted(function () {
                $registry = $this->app->make(CustodianRegistry::class);

                // Add demo bank as an available connector
                $registry->register('demo_bank', new DemoBankConnector([
                    'name'    => 'Demo Bank',
                    'enabled' => true,
                ]));

                // In demo environment, also override existing connectors with demo versions
                if ($this->app->environment('demo') && config('demo.features.mock_external_apis', true)) {
                    // Replace real bank connectors with demo versions
                    $registry->register('paysera', new DemoBankConnector([
                        'name'    => 'Paysera (Demo)',
                        'enabled' => true,
                    ]));

                    $registry->register('santander', new DemoBankConnector([
                        'name'    => 'Santander (Demo)',
                        'enabled' => true,
                    ]));

                    $registry->register('deutsche_bank', new DemoBankConnector([
                        'name'    => 'Deutsche Bank (Demo)',
                        'enabled' => true,
                    ]));
                }
            });
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Add demo banner to views when in demo environment
        if ($this->app->environment('demo') && config('demo.ui.show_banner', true)) {
            view()->composer('*', function ($view) {
                $view->with('isDemoMode', $this->app->environment('demo'));
                $view->with('demoMessage', config('demo.ui.banner_text'));
            });
        }
    }
}
