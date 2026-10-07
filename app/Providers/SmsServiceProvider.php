<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

    public function boot(): void
    {
        // Per-user limits for unpaid POST /api/v1/sms/send requests (applied
        // by SmsSendGateMiddleware after Sanctum authentication).
        RateLimiter::for('sms-send', function (Request $request): array {
            $key = 'sms-send:' . ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [
                Limit::perMinute(max(1, (int) config('sms.rate_limit.per_user_per_minute', 5)))->by($key . ':minute'),
                Limit::perDay(max(1, (int) config('sms.rate_limit.per_user_per_day', 50)))->by($key . ':day'),
            ];
        });
    }
}
