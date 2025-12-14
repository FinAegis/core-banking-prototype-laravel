<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Contracts\ArbitrageServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ArbitrageService implements ArbitrageServiceInterface
{
    /**
     * Minimum spread percentage to consider an opportunity.
     */
    private const MIN_SPREAD_PERCENT = 0.5;

    /**
     * Supported exchanges for arbitrage scanning.
     */
    private const EXCHANGES = ['binance', 'kraken', 'coinbase', 'internal'];

    /**
     * Find arbitrage opportunities for a symbol.
     *
     * Scans across multiple exchanges to find price discrepancies that could
     * be exploited for profit. Returns opportunities sorted by profitability.
     */
    public function findOpportunities(string $symbol): array
    {
        $cacheKey = "arbitrage:opportunities:{$symbol}";

        return Cache::remember($cacheKey, 30, function () use ($symbol) {
            $prices = $this->fetchPricesFromExchanges($symbol);

            if (count($prices) < 2) {
                return [];
            }

            $opportunities = [];

            // Compare all exchange pairs for arbitrage opportunities
            foreach ($prices as $buyExchange => $buyData) {
                foreach ($prices as $sellExchange => $sellData) {
                    if ($buyExchange === $sellExchange) {
                        continue;
                    }

                    // Check if we can buy low and sell high
                    $buyPrice = $buyData['ask'] ?? $buyData['price'];
                    $sellPrice = $sellData['bid'] ?? $sellData['price'];

                    if ($sellPrice > $buyPrice) {
                        $spreadPercent = (($sellPrice - $buyPrice) / $buyPrice) * 100;

                        if ($spreadPercent >= self::MIN_SPREAD_PERCENT) {
                            $opportunity = [
                                'id'               => 'arb_' . Str::uuid()->toString(),
                                'symbol'           => $symbol,
                                'buy_exchange'     => $buyExchange,
                                'sell_exchange'    => $sellExchange,
                                'buy_price'        => $buyPrice,
                                'sell_price'       => $sellPrice,
                                'spread'           => $sellPrice - $buyPrice,
                                'spread_percent'   => round($spreadPercent, 4),
                                'estimated_volume' => min($buyData['volume'] ?? 0, $sellData['volume'] ?? 0),
                                'detected_at'      => now()->toISOString(),
                                'expires_at'       => now()->addMinutes(5)->toISOString(),
                            ];

                            $opportunity['estimated_profit'] = $this->calculateProfitability($opportunity);
                            $opportunities[] = $opportunity;
                        }
                    }
                }
            }

            // Sort by profitability descending
            usort($opportunities, fn ($a, $b) => $b['estimated_profit'] <=> $a['estimated_profit']);

            Log::info('Arbitrage scan completed', [
                'symbol'        => $symbol,
                'opportunities' => count($opportunities),
            ]);

            return $opportunities;
        });
    }

    /**
     * Execute an arbitrage opportunity.
     *
     * Attempts to capture the price difference by executing simultaneous
     * buy and sell orders on the respective exchanges.
     */
    public function executeArbitrage(array $opportunity): array
    {
        // Validate opportunity structure
        if (! isset($opportunity['id'], $opportunity['buy_exchange'], $opportunity['sell_exchange'])) {
            return [
                'success' => false,
                'message' => 'Invalid opportunity structure',
                'error'   => 'missing_fields',
            ];
        }

        // Check if opportunity has expired
        if (isset($opportunity['expires_at']) && now()->gt($opportunity['expires_at'])) {
            return [
                'success' => false,
                'message' => 'Opportunity has expired',
                'error'   => 'expired',
            ];
        }

        // Verify the opportunity is still valid (prices may have changed)
        $currentPrices = $this->fetchPricesFromExchanges($opportunity['symbol']);
        $buyPrice = $currentPrices[$opportunity['buy_exchange']]['ask'] ?? null;
        $sellPrice = $currentPrices[$opportunity['sell_exchange']]['bid'] ?? null;

        if (! $buyPrice || ! $sellPrice || $sellPrice <= $buyPrice) {
            return [
                'success'            => false,
                'message'            => 'Price opportunity no longer exists',
                'error'              => 'price_changed',
                'current_buy_price'  => $buyPrice,
                'current_sell_price' => $sellPrice,
            ];
        }

        // Generate execution result (demo mode)
        $executionId = 'exec_' . Str::uuid()->toString();
        $tradeAmount = $opportunity['estimated_volume'] ?? 0.1;
        $actualProfit = ($sellPrice - $buyPrice) * $tradeAmount;

        Log::info('Arbitrage execution', [
            'execution_id'  => $executionId,
            'opportunity'   => $opportunity['id'],
            'symbol'        => $opportunity['symbol'],
            'buy_exchange'  => $opportunity['buy_exchange'],
            'sell_exchange' => $opportunity['sell_exchange'],
            'profit'        => $actualProfit,
        ]);

        return [
            'success'        => true,
            'execution_id'   => $executionId,
            'opportunity_id' => $opportunity['id'],
            'status'         => 'completed',
            'buy_order'      => [
                'exchange' => $opportunity['buy_exchange'],
                'price'    => $buyPrice,
                'amount'   => $tradeAmount,
                'total'    => $buyPrice * $tradeAmount,
                'status'   => 'filled',
            ],
            'sell_order' => [
                'exchange' => $opportunity['sell_exchange'],
                'price'    => $sellPrice,
                'amount'   => $tradeAmount,
                'total'    => $sellPrice * $tradeAmount,
                'status'   => 'filled',
            ],
            'profit'         => round($actualProfit, 8),
            'profit_percent' => round((($sellPrice - $buyPrice) / $buyPrice) * 100, 4),
            'executed_at'    => now()->toISOString(),
        ];
    }

    /**
     * Calculate profitability of an opportunity.
     *
     * Considers trading fees, slippage, and transfer costs to estimate
     * net profit from executing the arbitrage.
     */
    public function calculateProfitability(array $opportunity): float
    {
        if (! isset($opportunity['buy_price'], $opportunity['sell_price'])) {
            return 0.0;
        }

        $buyPrice = (float) $opportunity['buy_price'];
        $sellPrice = (float) $opportunity['sell_price'];
        $volume = (float) ($opportunity['estimated_volume'] ?? 1.0);

        // Trading fees (assumed 0.1% per trade = 0.2% total)
        $tradingFeePercent = 0.002;

        // Slippage estimate (0.05%)
        $slippagePercent = 0.0005;

        // Gross profit
        $grossProfit = ($sellPrice - $buyPrice) * $volume;

        // Deduct fees and slippage
        $tradingFees = ($buyPrice + $sellPrice) * $volume * $tradingFeePercent / 2;
        $slippage = $grossProfit * $slippagePercent;

        $netProfit = $grossProfit - $tradingFees - $slippage;

        return round($netProfit, 8);
    }

    /**
     * Fetch prices from all supported exchanges.
     *
     * Returns a map of exchange => price data for the given symbol.
     */
    private function fetchPricesFromExchanges(string $symbol): array
    {
        $cacheKey = "exchange:prices:{$symbol}";

        return Cache::remember($cacheKey, 10, function () use ($symbol) {
            $prices = [];

            // Generate simulated prices with realistic spreads
            // In production, this would call actual exchange APIs
            $basePrice = $this->getBasePrice($symbol);

            foreach (self::EXCHANGES as $exchange) {
                // Add slight variation per exchange (±0.3% typical spread)
                $variation = (mt_rand(-30, 30) / 10000);
                $exchangePrice = $basePrice * (1 + $variation);

                // Bid/ask spread (0.05% typical)
                $spread = $exchangePrice * 0.0005;

                $prices[$exchange] = [
                    'price'      => $exchangePrice,
                    'bid'        => $exchangePrice - $spread,
                    'ask'        => $exchangePrice + $spread,
                    'volume'     => mt_rand(10, 1000) / 10,
                    'updated_at' => now()->toISOString(),
                ];
            }

            return $prices;
        });
    }

    /**
     * Get base price for a symbol.
     */
    private function getBasePrice(string $symbol): float
    {
        // Base prices for common trading pairs
        $basePrices = [
            'BTC/EUR' => 45000.0,
            'BTC/USD' => 48000.0,
            'ETH/EUR' => 2500.0,
            'ETH/USD' => 2650.0,
            'ETH/BTC' => 0.055,
            'XRP/EUR' => 0.52,
            'XRP/USD' => 0.55,
            'SOL/EUR' => 95.0,
            'SOL/USD' => 100.0,
        ];

        return $basePrices[$symbol] ?? 100.0;
    }
}
