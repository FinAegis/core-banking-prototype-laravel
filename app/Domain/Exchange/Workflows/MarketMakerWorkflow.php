<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Workflows;

use App\Domain\Exchange\Activities\AdjustInventoryActivity;
use App\Domain\Exchange\Activities\CalculateOptimalQuotesActivity;
use App\Domain\Exchange\Activities\CancelOrderActivity;
use App\Domain\Exchange\Activities\MonitorMarketConditionsActivity;
use App\Domain\Exchange\Activities\PlaceOrderActivity;
use App\Domain\Exchange\Events\MarketMakerStarted;
use App\Domain\Exchange\Events\MarketMakerStopped;
use App\Domain\Exchange\Events\QuotesUpdated;
use Illuminate\Support\Facades\Log;
use Workflow\Activity\ActivityOptions;
use Workflow\Workflow;
use Workflow\WorkflowInterface;

/**
 * Market maker workflow that provides continuous liquidity by placing orders
 * on both sides of the order book and adjusting prices based on inventory and market conditions.
 */
#[WorkflowInterface]
class MarketMakerWorkflow
{
    private array $activeOrders = [];

    private bool $isRunning = true;

    private array $config;

    private array $marketConditions = [];

    /**
     * Main market making workflow execution.
     *
     * @param array $config Configuration for market making:
     *   - pool_id: string - Liquidity pool ID
     *   - base_currency: string - Base currency code
     *   - quote_currency: string - Quote currency code
     *   - spread_bps: int - Target spread in basis points
     *   - order_size: float - Size of each order
     *   - max_inventory: float - Maximum inventory per asset
     *   - rebalance_threshold: float - Inventory imbalance threshold for rebalancing
     *   - quote_refresh_interval: int - Seconds between quote updates
     *   - risk_limits: array - Risk management parameters
     *
     * @return \Generator
     */
    public function execute(array $config): \Generator
    {
        $this->config = $config;

        // Record market maker start
        yield Workflow::sideEffect(fn () => event(new MarketMakerStarted(
            poolId: $config['pool_id'],
            baseCurrency: $config['base_currency'],
            quoteCurrency: $config['quote_currency'],
            config: $config,
            startedAt: now()
        )));

        try {
            // Initial market assessment
            $this->marketConditions = yield Workflow::executeActivity(
                MonitorMarketConditionsActivity::class,
                [$config['pool_id']],
                ActivityOptions::new()
                    ->withStartToCloseTimeout(30)
                    ->withRetryOptions(3, 2)
            );

            $cycleCount = 0;
            $lastRebalance = now();

            while ($this->isRunning && $cycleCount < ($config['max_cycles'] ?? PHP_INT_MAX)) {
                // Step 1: Cancel stale orders
                yield from $this->cancelStaleOrders();

                // Step 2: Check inventory and rebalance if needed
                if (now()->diffInMinutes($lastRebalance) > ($config['rebalance_interval'] ?? 60)) {
                    $needsRebalance = $this->checkInventoryBalance();

                    if ($needsRebalance) {
                        yield from $this->rebalanceInventory();
                        $lastRebalance = now();
                    }
                }

                // Step 3: Calculate optimal quotes based on current conditions
                $quotes = yield Workflow::executeActivity(
                    CalculateOptimalQuotesActivity::class,
                    [
                        $config['pool_id'],
                        $this->marketConditions,
                        $config['spread_bps'],
                        $config['order_size'],
                    ],
                    ActivityOptions::new()
                        ->withStartToCloseTimeout(10)
                        ->withRetryOptions(3, 1)
                );

                // Step 4: Place new orders
                yield from $this->placeQuoteOrders($quotes);

                // Step 5: Update market conditions
                $this->marketConditions = yield Workflow::executeActivity(
                    MonitorMarketConditionsActivity::class,
                    [$config['pool_id']],
                    ActivityOptions::new()
                        ->withStartToCloseTimeout(30)
                        ->withRetryOptions(3, 2)
                );

                // Step 6: Check risk limits
                yield from $this->checkRiskLimits();

                // Wait before next cycle
                yield Workflow::timer($config['quote_refresh_interval'] ?? 10);

                $cycleCount++;

                // Log progress
                if ($cycleCount % 10 === 0) {
                    Log::info('Market maker cycle completed', [
                        'pool_id'           => $config['pool_id'],
                        'cycle'             => $cycleCount,
                        'active_orders'     => count($this->activeOrders),
                        'market_conditions' => $this->marketConditions,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Market maker workflow failed', [
                'pool_id' => $config['pool_id'],
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            // Cancel all active orders on error
            yield from $this->cancelAllOrders();

            throw $e;
        } finally {
            // Clean up: cancel all remaining orders
            yield from $this->cancelAllOrders();

            // Record market maker stop
            yield Workflow::sideEffect(fn () => event(new MarketMakerStopped(
                poolId: $config['pool_id'],
                reason: $this->isRunning ? 'completed' : 'stopped',
                stoppedAt: now()
            )));
        }

        return [
            'status'           => 'completed',
            'cycles_completed' => $cycleCount,
            'final_conditions' => $this->marketConditions,
        ];
    }

    /**
     * Cancel orders that are too far from current market price.
     */
    private function cancelStaleOrders(): \Generator
    {
        $cancelledCount = 0;

        foreach ($this->activeOrders as $orderId => $order) {
            $shouldCancel = false;

            // Check if order is stale (older than threshold)
            if (now()->diffInSeconds($order['placed_at']) > ($this->config['order_ttl'] ?? 60)) {
                $shouldCancel = true;
            }

            // Check if order is too far from market price
            $priceDiff = abs($order['price'] - $this->marketConditions['mid_price']) / $this->marketConditions['mid_price'];
            if ($priceDiff > ($this->config['max_price_deviation'] ?? 0.02)) {
                $shouldCancel = true;
            }

            if ($shouldCancel) {
                yield Workflow::executeActivity(
                    CancelOrderActivity::class,
                    [$orderId],
                    ActivityOptions::new()
                        ->withStartToCloseTimeout(10)
                        ->withRetryOptions(3, 1)
                );

                unset($this->activeOrders[$orderId]);
                $cancelledCount++;
            }
        }

        if ($cancelledCount > 0) {
            Log::info('Cancelled stale orders', [
                'pool_id'         => $this->config['pool_id'],
                'cancelled_count' => $cancelledCount,
            ]);
        }
    }

    /**
     * Check if inventory needs rebalancing.
     */
    private function checkInventoryBalance(): bool
    {
        $inventory = $this->marketConditions['inventory'] ?? [];

        $baseInventory = $inventory[$this->config['base_currency']] ?? 0;
        $quoteInventory = $inventory[$this->config['quote_currency']] ?? 0;

        // Calculate inventory value ratio
        $baseValue = $baseInventory * $this->marketConditions['mid_price'];
        $totalValue = $baseValue + $quoteInventory;

        if ($totalValue == 0) {
            return false;
        }

        $baseRatio = $baseValue / $totalValue;
        $imbalance = abs(0.5 - $baseRatio);

        return $imbalance > ($this->config['rebalance_threshold'] ?? 0.2);
    }

    /**
     * Rebalance inventory to maintain target ratio.
     */
    private function rebalanceInventory(): \Generator
    {
        Log::info('Rebalancing inventory', [
            'pool_id'           => $this->config['pool_id'],
            'current_inventory' => $this->marketConditions['inventory'],
        ]);

        yield Workflow::executeActivity(
            AdjustInventoryActivity::class,
            [
                $this->config['pool_id'],
                $this->config['base_currency'],
                $this->config['quote_currency'],
                0.5, // Target 50/50 ratio
            ],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60)
                ->withRetryOptions(3, 2)
        );
    }

    /**
     * Place bid and ask orders based on calculated quotes.
     */
    private function placeQuoteOrders(array $quotes): \Generator
    {
        // Cancel existing orders if we're updating quotes
        if (! empty($this->activeOrders)) {
            yield from $this->cancelAllOrders();
        }

        // Place bid orders
        foreach ($quotes['bids'] as $bid) {
            $orderId = yield Workflow::executeActivity(
                PlaceOrderActivity::class,
                [
                    'type'           => 'limit',
                    'side'           => 'buy',
                    'base_currency'  => $this->config['base_currency'],
                    'quote_currency' => $this->config['quote_currency'],
                    'amount'         => $bid['size'],
                    'price'          => $bid['price'],
                    'pool_id'        => $this->config['pool_id'],
                ],
                ActivityOptions::new()
                    ->withStartToCloseTimeout(10)
                    ->withRetryOptions(3, 1)
            );

            $this->activeOrders[$orderId] = [
                'side'      => 'buy',
                'price'     => $bid['price'],
                'size'      => $bid['size'],
                'placed_at' => now(),
            ];
        }

        // Place ask orders
        foreach ($quotes['asks'] as $ask) {
            $orderId = yield Workflow::executeActivity(
                PlaceOrderActivity::class,
                [
                    'type'           => 'limit',
                    'side'           => 'sell',
                    'base_currency'  => $this->config['base_currency'],
                    'quote_currency' => $this->config['quote_currency'],
                    'amount'         => $ask['size'],
                    'price'          => $ask['price'],
                    'pool_id'        => $this->config['pool_id'],
                ],
                ActivityOptions::new()
                    ->withStartToCloseTimeout(10)
                    ->withRetryOptions(3, 1)
            );

            $this->activeOrders[$orderId] = [
                'side'      => 'sell',
                'price'     => $ask['price'],
                'size'      => $ask['size'],
                'placed_at' => now(),
            ];
        }

        // Record quotes update
        yield Workflow::sideEffect(fn () => event(new QuotesUpdated(
            poolId: $this->config['pool_id'],
            bids: $quotes['bids'],
            asks: $quotes['asks'],
            spread: $quotes['spread'],
            timestamp: now()
        )));

        Log::info('Placed quote orders', [
            'pool_id'   => $this->config['pool_id'],
            'bid_count' => count($quotes['bids']),
            'ask_count' => count($quotes['asks']),
            'spread'    => $quotes['spread'],
        ]);
    }

    /**
     * Check if any risk limits are breached.
     */
    private function checkRiskLimits(): \Generator
    {
        $riskLimits = $this->config['risk_limits'] ?? [];

        // Check maximum inventory
        $inventory = $this->marketConditions['inventory'] ?? [];
        foreach ($inventory as $currency => $amount) {
            $maxInventory = $riskLimits['max_inventory'][$currency] ?? PHP_FLOAT_MAX;
            if ($amount > $maxInventory) {
                Log::warning('Inventory limit exceeded', [
                    'pool_id'  => $this->config['pool_id'],
                    'currency' => $currency,
                    'amount'   => $amount,
                    'limit'    => $maxInventory,
                ]);

                $this->isRunning = false;

                return;
            }
        }

        // Check maximum loss
        $pnl = $this->marketConditions['pnl'] ?? 0;
        $maxLoss = $riskLimits['max_loss'] ?? PHP_FLOAT_MAX;
        if ($pnl < -$maxLoss) {
            Log::warning('Maximum loss exceeded', [
                'pool_id' => $this->config['pool_id'],
                'pnl'     => $pnl,
                'limit'   => -$maxLoss,
            ]);

            $this->isRunning = false;

            return;
        }

        // Check volatility threshold
        $volatility = $this->marketConditions['volatility'] ?? 0;
        $maxVolatility = $riskLimits['max_volatility'] ?? 1.0;
        if ($volatility > $maxVolatility) {
            Log::warning('Volatility threshold exceeded', [
                'pool_id'    => $this->config['pool_id'],
                'volatility' => $volatility,
                'limit'      => $maxVolatility,
            ]);

            // Pause trading temporarily
            yield Workflow::timer(60); // Wait 1 minute
        }
    }

    /**
     * Cancel all active orders.
     */
    private function cancelAllOrders(): \Generator
    {
        foreach (array_keys($this->activeOrders) as $orderId) {
            yield Workflow::executeActivity(
                CancelOrderActivity::class,
                [$orderId],
                ActivityOptions::new()
                    ->withStartToCloseTimeout(10)
                    ->withRetryOptions(3, 1)
            );
        }

        $this->activeOrders = [];

        Log::info('Cancelled all orders', [
            'pool_id' => $this->config['pool_id'],
        ]);
    }

    /**
     * Stop the market maker gracefully.
     */
    public function stop(): void
    {
        $this->isRunning = false;
    }
}
