<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ramp\Clients\OnramperClient;
use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Providers\MockRampProvider;
use App\Domain\Ramp\Providers\OnramperProvider;
use App\Domain\Ramp\Providers\StripeBridgeProvider;
use App\Domain\Ramp\Registries\RampProviderRegistry;
use App\Domain\Ramp\Services\RampService;
use App\Domain\Ramp\Services\StripeBridgeService;
use Illuminate\Support\ServiceProvider;

class RampServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ramp.php',
            'ramp'
        );

        $this->app->singleton(OnramperClient::class, function () {
            return new OnramperClient();
        });

        $this->app->singleton(StripeBridgeService::class, function () {
            return new StripeBridgeService();
        });

        $this->app->bind(RampProviderInterface::class, function ($app) {
            $provider = config('ramp.default_provider', 'mock');

            return match ($provider) {
                'stripe_bridge' => new StripeBridgeProvider($app->make(StripeBridgeService::class)),
                'onramper'      => new OnramperProvider($app->make(OnramperClient::class)),
                default         => new MockRampProvider(),
            };
        });

        $this->app->bind(RampService::class, function ($app) {
            return new RampService(
                $app->make(RampProviderInterface::class)
            );
        });

        $this->app->singleton(RampProviderRegistry::class, function ($app) {
            return new RampProviderRegistry([
                'onramper'      => static fn () => new OnramperProvider($app->make(OnramperClient::class)),
                'stripe_bridge' => static fn () => new StripeBridgeProvider($app->make(StripeBridgeService::class)),
                'mock'          => static fn () => new MockRampProvider(),
            ]);
        });
    }
}
