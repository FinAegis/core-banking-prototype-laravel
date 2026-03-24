<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsService;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/sms.php',
            'sms'
        );

        $this->app->singleton(VertexSmsClient::class, function () {
            return new VertexSmsClient();
        });

        $this->app->singleton(SmsPricingService::class, function ($app) {
            return new SmsPricingService(
                $app->make(VertexSmsClient::class),
            );
        });

        $this->app->singleton(SmsService::class, function ($app) {
            return new SmsService(
                $app->make(VertexSmsClient::class),
                $app->make(SmsPricingService::class),
            );
        });
    }
}
