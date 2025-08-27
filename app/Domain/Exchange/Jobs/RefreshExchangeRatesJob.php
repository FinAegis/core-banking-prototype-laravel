<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Jobs;

use App\Domain\Exchange\Services\EnhancedExchangeRateService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshExchangeRatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly ?array $currencyPairs = null,
        public readonly bool $forceRefresh = false
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(EnhancedExchangeRateService $service): void
    {
        Log::info(
            'Starting exchange rate refresh job',
            [
            'pairs' => $this->currencyPairs,
            'force' => $this->forceRefresh,
            ]
        );

        if ($this->currencyPairs) {
            // Refresh specific pairs
            $results = ['refreshed' => [], 'failed' => []];

            foreach ($this->currencyPairs as $pair) {
                if (str_contains($pair, '/')) {
                    [$from, $to] = explode('/', $pair);
                    try {
                        $service->fetchAndStoreRate($from, $to);
                        $results['refreshed'][] = $pair;
                    } catch (Exception $e) {
                        $results['failed'][] = [
                            'pair'  => $pair,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        } else {
            // Refresh all active rates
            $results = $service->refreshAllRates();
        }

        Log::info('Exchange rate refresh completed', $results);

        // Dispatch webhook events if configured
        if (config('exchange.webhooks.enabled')) {
            $this->dispatchWebhooks($results);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error(
            'Exchange rate refresh job failed',
            [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            ]
        );
    }

    /**
     * Dispatch webhook notifications.
     */
    private function dispatchWebhooks(array $results): void
    {
        // This could trigger webhook events for rate updates
        // Implementation depends on webhook system
    }
}
