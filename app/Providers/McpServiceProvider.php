<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\MCP\Resources\AccountBalanceResource;
use App\Domain\MCP\Resources\AccountProfileResource;
use App\Domain\MCP\Resources\RecentTransactionsResource;
use App\Domain\MCP\Resources\ResourceRegistry;
use App\Domain\MCP\Resources\SingleTransactionResource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResourceRegistry::class, function ($app) {
            $registry = new ResourceRegistry();
            $registry->register($app->make(AccountProfileResource::class));
            $registry->register($app->make(AccountBalanceResource::class));
            $registry->register($app->make(RecentTransactionsResource::class));
            $registry->register($app->make(SingleTransactionResource::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        $limits = (array) config('mcp.rate_limits', []);

        /** @var array<string, int> $aggregate */
        $aggregate = (array) ($limits['aggregate'] ?? []);

        RateLimiter::for('mcp.aggregate', function (Request $request) use ($aggregate) {
            $token = $request->attributes->get('mcp.token');
            $key = is_object($token) && method_exists($token, 'getKey')
                ? (string) $token->getKey()
                : (string) $request->ip();

            return [
                Limit::perMinute((int) ($aggregate['per_minute'] ?? 60))->by($key),
                Limit::perHour((int) ($aggregate['per_hour'] ?? 600))->by($key),
            ];
        });

        RateLimiter::for('mcp.discovery', function (Request $request) use ($limits) {
            return Limit::perMinute((int) ($limits['discovery_per_minute_per_ip'] ?? 60))
                ->by((string) $request->ip());
        });
    }
}
