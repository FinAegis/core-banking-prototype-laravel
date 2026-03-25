<?php

declare(strict_types=1);

namespace Zelta;

use Illuminate\Support\ServiceProvider;
use Zelta\DataObjects\PaymentConfig;

/**
 * Laravel auto-discovery service provider for the Zelta SDK.
 *
 * Registers the ZeltaClient singleton when used within a Laravel app.
 */
class ZeltaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZeltaClient::class, function ($app) {
            return new ZeltaClient(
                config: new PaymentConfig(
                    baseUrl: (string) config('zelta.base_url', 'https://api.zelta.app'),
                    apiKey: config('zelta.api_key') ? (string) config('zelta.api_key') : null,
                    autoPay: (bool) config('zelta.auto_pay', true),
                    timeoutSeconds: (int) config('zelta.timeout', 30),
                ),
            );
        });
    }
}
