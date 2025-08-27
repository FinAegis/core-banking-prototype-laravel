<?php

namespace App\Domain\Exchange\Console\Commands\LiquidityPool;

use App\Domain\Exchange\LiquidityPool\Services\PoolRebalancingService;
use Exception;
use Illuminate\Console\Command;

class RebalancePoolsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liquidity:rebalance 
                            {--pool= : Specific pool ID to rebalance}
                            {--strategy= : Rebalancing strategy (aggressive, conservative, adaptive)}
                            {--dry-run : Analyze without executing rebalancing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and rebalance liquidity pools to maintain optimal ratios';

    /**
     * Execute the console command.
     */
    public function handle(PoolRebalancingService $rebalancingService): int
    {
        $this->info('Starting pool rebalancing check...');

        $isDryRun = $this->option('dry-run');
        $poolId = $this->option('pool');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No rebalancing will be executed');
        }

        try {
            if ($poolId) {
                // Rebalance specific pool
                $pool = \App\Domain\Exchange\Projections\LiquidityPool::where('pool_id', $poolId)
                    ->firstOrFail();

                $result = $rebalancingService->checkAndRebalancePool($pool);
                $this->displayRebalancingResult($pool, $result);
            } else {
                // Check all pools
                $results = $rebalancingService->rebalanceAllPools();

                if (empty($results)) {
                    $this->info('No pools require rebalancing at this time.');
                } else {
                    $this->info('Rebalancing Results:');
                    foreach ($results as $poolId => $result) {
                        $pool = \App\Domain\Exchange\Projections\LiquidityPool::find($poolId);
                        if ($pool) {
                            $this->displayRebalancingResult($pool, $result);
                        }
                    }
                }
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to rebalance pools: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Display rebalancing result for a pool.
     */
    private function displayRebalancingResult($pool, array $result): void
    {
        $this->info("\nPool: {$pool->base_currency}/{$pool->quote_currency}");

        if (! $result['needs_rebalancing']) {
            $this->info('Status: No rebalancing needed');
            if (isset($result['reason'])) {
                $this->info('Reason: ' . $result['reason']);
            }

            return;
        }

        $this->warn('Status: Rebalancing needed');

        if (isset($result['analysis'])) {
            $analysis = $result['analysis'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Pool Price', $analysis['pool_price']],
                    ['External Price', $analysis['external_price']],
                    ['Price Deviation', ($analysis['price_deviation'] * 100) . '%'],
                    ['Current Inventory Ratio', $analysis['current_inventory_ratio']],
                    ['Target Inventory Ratio', $analysis['target_inventory_ratio']],
                    ['Inventory Imbalance', ($analysis['inventory_imbalance'] * 100) . '%'],
                ]
            );
        }

        if (isset($result['strategy'])) {
            $this->info('Strategy: ' . $result['strategy']['type']);
            $this->info('Max Slippage: ' . ($result['strategy']['max_slippage'] * 100) . '%');
        }

        if (isset($result['result'])) {
            $execResult = $result['result'];
            if ($execResult['status'] === 'success') {
                $this->info('✓ Rebalancing executed successfully');
                $this->info('Amount: ' . $execResult['executed_amount'] . ' ' . $execResult['executed_currency']);
                $this->info('New Ratio: ' . $execResult['new_ratio']);
            } else {
                $this->error('✗ Rebalancing failed: ' . ($execResult['error'] ?? 'Unknown error'));
            }
        }
    }
}
