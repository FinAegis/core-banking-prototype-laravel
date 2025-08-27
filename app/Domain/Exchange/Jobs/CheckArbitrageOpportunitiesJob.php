<?php

namespace App\Domain\Exchange\Jobs;

use App\Domain\Exchange\Services\ExternalLiquidityService;
use App\Models\Currency;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckArbitrageOpportunitiesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('exchange');
    }

    public function handle(ExternalLiquidityService $liquidityService): void
    {
        // Get tradeable currency pairs
        $currencies = Currency::where('is_active', true)->get();
        $pairs = [];

        foreach ($currencies as $baseCurrency) {
            foreach ($currencies as $quoteCurrency) {
                if ($baseCurrency->code !== $quoteCurrency->code) {
                    $pairs[] = [
                        'base'  => $baseCurrency->code,
                        'quote' => $quoteCurrency->code,
                    ];
                }
            }
        }

        // Check each pair for arbitrage opportunities
        foreach ($pairs as $pair) {
            try {
                $opportunities = $liquidityService->findArbitrageOpportunities(
                    $pair['base'],
                    $pair['quote']
                );

                if (! empty($opportunities)) {
                    Log::info(
                        'Arbitrage opportunities found',
                        [
                        'pair'          => "{$pair['base']}/{$pair['quote']}",
                        'opportunities' => $opportunities,
                        ]
                    );

                    // Here you could dispatch jobs to execute arbitrage trades
                    // For now, we just log them
                }
            } catch (Exception $e) {
                Log::error(
                    'Failed to check arbitrage opportunities',
                    [
                    'pair'  => "{$pair['base']}/{$pair['quote']}",
                    'error' => $e->getMessage(),
                    ]
                );
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error(
            'Arbitrage check job failed',
            [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            ]
        );
    }
}
