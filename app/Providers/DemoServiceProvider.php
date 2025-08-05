<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Custodian\Connectors\DemoBankConnector;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Payment\Contracts\PaymentServiceInterface;
use App\Domain\Payment\Services\DemoPaymentGatewayService;
use App\Domain\Payment\Services\DemoPaymentService;
use App\Domain\Payment\Services\PaymentGatewayService;
use App\Domain\Payment\Services\ProductionPaymentService;
use App\Domain\Payment\Services\SandboxPaymentService;
use Illuminate\Support\ServiceProvider;

class DemoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind PaymentServiceInterface to the appropriate implementation based on environment
        if (config('demo.mode')) {
            // Demo mode: Use DemoPaymentService that bypasses external APIs
            $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
        } elseif (config('demo.sandbox.enabled')) {
            // Sandbox mode: Use SandboxPaymentService with real sandbox APIs
            $this->app->bind(PaymentServiceInterface::class, SandboxPaymentService::class);
        } else {
            // Production mode: Use ProductionPaymentService
            $this->app->bind(PaymentServiceInterface::class, ProductionPaymentService::class);
        }

        // Only activate demo services when in demo or sandbox mode
        if (config('demo.mode') || config('demo.sandbox.enabled')) {
            // Replace payment gateway with demo version for demo mode only
            if (config('demo.mode')) {
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

                // In demo mode, also override existing connectors with demo versions
                if (config('demo.bypass_external_apis.banking_apis')) {
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
        // Add demo banner to views when in demo mode
        if (config('demo.indicators.show_banner')) {
            view()->composer('*', function ($view) {
                $view->with('isDemoMode', config('demo.mode'));
                $view->with('demoMessage', config('demo.indicators.banner_text'));
            });
        }
    }
}
