<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\Contracts\VisaCliPaymentGatewayInterface;
use App\Domain\VisaCli\Services\DemoVisaCliClient;
use App\Domain\VisaCli\Services\VisaCliPaymentGatewayService;
use App\Domain\VisaCli\Services\VisaCliProcessClient;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the VisaCli domain.
 *
 * Binds Visa CLI contracts to implementations based on configuration.
 * Supports demo and process (real binary) drivers.
 */
class VisaCliServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VisaCliClientInterface::class, function ($app) {
            $driver = config('visacli.driver', 'demo');

            return match ($driver) {
                'process' => new VisaCliProcessClient(),
                default   => new DemoVisaCliClient(),
            };
        });

        $this->app->bind(VisaCliPaymentGatewayInterface::class, VisaCliPaymentGatewayService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
