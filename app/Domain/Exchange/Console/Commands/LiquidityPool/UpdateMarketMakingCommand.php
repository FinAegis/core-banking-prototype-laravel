<?php

namespace App\Domain\Exchange\Console\Commands\LiquidityPool;

use App\Domain\Exchange\LiquidityPool\Services\AutomatedMarketMakerService;
use App\Domain\Exchange\Services\OrderService;
use Exception;
use Illuminate\Console\Command;

class UpdateMarketMakingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liquidity:update-market-making 
                            {--pool= : Specific pool ID to update market making for}
                            {--cancel-existing : Cancel existing AMM orders before placing new ones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update automated market making orders for liquidity pools';

    /**
     * Execute the console command.
     */
    public function handle(
        AutomatedMarketMakerService $ammService,
        OrderService $orderService
    ): int {
        $this->info('Updating market making orders...');

        $poolId = $this->option('pool');
        $cancelExisting = $this->option('cancel-existing');

        try {
            $pools = $poolId
                ? [\App\Domain\Exchange\Projections\LiquidityPool::where('pool_id', $poolId)->firstOrFail()]
                : \App\Domain\Exchange\Projections\LiquidityPool::active()->get();

            foreach ($pools as $pool) {
                $this->info("\nProcessing pool: {$pool->base_currency}/{$pool->quote_currency}");

                // Cancel existing AMM orders if requested
                if ($cancelExisting) {
                    $this->cancelExistingAMMOrders($pool->pool_id, $orderService);
                }

                // Generate new market making orders
                $orders = $ammService->generateMarketMakingOrders($pool->pool_id);

                $this->info('Generated ' . count($orders) . ' market making orders');

                // Place orders
                $placedOrders = 0;
                foreach ($orders as $order) {
                    try {
                        $orderService->placeOrder(
                            accountId: 'amm-' . $pool->pool_id, // AMM account
                            type: $order['type'] === 'buy' ? 'BUY' : 'SELL',
                            baseCurrency: $pool->base_currency,
                            quoteCurrency: $pool->quote_currency,
                            price: $order['price'],
                            quantity: $order['quantity'],
                            orderType: 'LIMIT',
                            metadata: [
                                'source'  => 'amm',
                                'pool_id' => $pool->pool_id,
                                'level'   => $order['level'],
                            ]
                        );
                        $placedOrders++;
                    } catch (Exception $e) {
                        $this->warn('Failed to place order: ' . $e->getMessage());
                    }
                }

                $this->info("Successfully placed {$placedOrders} orders");

                // Adjust parameters based on performance
                $adjustments = $ammService->adjustMarketMakingParameters($pool->pool_id);
                if (! empty($adjustments)) {
                    $this->info('Suggested adjustments:');
                    foreach ($adjustments as $key => $value) {
                        $this->info("  - {$key}: {$value}");
                    }
                }
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to update market making: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Cancel existing AMM orders for a pool.
     */
    private function cancelExistingAMMOrders(string $poolId, OrderService $orderService): void
    {
        $existingOrders = \App\Domain\Exchange\Projections\Order::where('metadata->pool_id', $poolId)
            ->where('metadata->source', 'amm')
            ->whereIn('status', ['PENDING', 'PARTIALLY_FILLED'])
            ->get();

        $this->info('Cancelling ' . $existingOrders->count() . ' existing AMM orders');

        foreach ($existingOrders as $order) {
            try {
                $orderService->cancelOrder($order->order_id);
            } catch (Exception $e) {
                $this->warn("Failed to cancel order {$order->order_id}: " . $e->getMessage());
            }
        }
    }
}
