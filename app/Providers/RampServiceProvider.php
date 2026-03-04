<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Providers\MockRampProvider;
use App\Domain\Ramp\Services\RampService;
use Illuminate\Support\ServiceProvider;

class RampServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ramp.php',
            'ramp'
        );

        $this->app->bind(RampProviderInterface::class, function () {
            $provider = config('ramp.default_provider', 'mock');

            return match ($provider) {
                default => new MockRampProvider(),
            };
        });

        $this->app->bind(RampService::class, function ($app) {
            return new RampService(
                $app->make(RampProviderInterface::class)
            );
        });
    }
}
