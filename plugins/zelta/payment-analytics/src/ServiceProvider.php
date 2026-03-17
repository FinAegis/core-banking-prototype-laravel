<?php

declare(strict_types=1);

namespace Plugins\Zelta\PaymentAnalytics;

use App\Infrastructure\Plugins\PluginHookInterface;
use App\Infrastructure\Plugins\PluginHookManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Payment Analytics Plugin — Example plugin demonstrating hook listeners.
 *
 * Listens to payment.completed and payment.failed hooks to track
 * real-time payment metrics in cache.
 */
class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        /** @var PluginHookManager $hookManager */
        $hookManager = $this->app->make(PluginHookManager::class);

        $hookManager->register(new PaymentCompletedListener());
        $hookManager->register(new PaymentFailedListener());
    }
}

/**
 * Tracks successful payments — increments counters and running totals.
 */
class PaymentCompletedListener implements PluginHookInterface
{
    public function getHookName(): string
    {
        return 'payment.completed';
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function handle(array $payload): void
    {
        $amount = (float) ($payload['amount'] ?? 0);
        $currency = $payload['currency'] ?? 'USD';

        // Increment daily counters
        $dateKey = now()->format('Y-m-d');
        Cache::increment("plugin:payment_analytics:completed:{$dateKey}");
        Cache::increment("plugin:payment_analytics:volume:{$dateKey}", (int) ($amount * 100));

        Log::debug('PaymentAnalytics: payment tracked', [
            'amount'   => $amount,
            'currency' => $currency,
        ]);
    }
}

/**
 * Tracks failed payments for error rate calculation.
 */
class PaymentFailedListener implements PluginHookInterface
{
    public function getHookName(): string
    {
        return 'payment.failed';
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function handle(array $payload): void
    {
        $dateKey = now()->format('Y-m-d');
        Cache::increment("plugin:payment_analytics:failed:{$dateKey}");

        Log::debug('PaymentAnalytics: failed payment tracked', [
            'reason' => $payload['error'] ?? 'unknown',
        ]);
    }
}
