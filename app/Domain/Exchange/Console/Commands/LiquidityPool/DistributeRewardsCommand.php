<?php

namespace App\Domain\Exchange\Console\Commands\LiquidityPool;

use App\Domain\Exchange\LiquidityPool\Services\LiquidityIncentivesService;
use Exception;
use Illuminate\Console\Command;

class DistributeRewardsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liquidity:distribute-rewards 
                            {--pool= : Specific pool ID to distribute rewards for}
                            {--dry-run : Calculate rewards without distributing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and distribute rewards to liquidity providers';

    /**
     * Execute the console command.
     */
    public function handle(LiquidityIncentivesService $incentivesService): int
    {
        $this->info('Starting liquidity rewards distribution...');

        $isDryRun = $this->option('dry-run');
        $poolId = $this->option('pool');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No rewards will be distributed');
        }

        try {
            if ($poolId) {
                // Distribute rewards for specific pool
                $pool = \App\Domain\Exchange\Projections\LiquidityPool::where('pool_id', $poolId)
                    ->firstOrFail();

                $rewards = $incentivesService->calculatePoolRewards($pool);

                $this->info("Pool: {$pool->base_currency}/{$pool->quote_currency}");
                $this->info("TVL: {$rewards['tvl']}");
                $this->info("Total Rewards: {$rewards['total_rewards']} {$rewards['reward_currency']}");
                $this->info("Performance Multiplier: {$rewards['performance_multiplier']}x");

                if (! $isDryRun) {
                    $incentivesService->distributeRewards();
                    $this->info('Rewards distributed successfully!');
                }
            } else {
                // Distribute rewards for all pools
                $results = $incentivesService->distributeRewards();

                $this->info('Distribution Results:');
                foreach ($results as $poolId => $result) {
                    if ($result['status'] === 'success') {
                        $rewards = $result['rewards'];
                        $this->info("Pool {$poolId}: {$rewards['total_rewards']} {$rewards['reward_currency']} distributed");
                    } else {
                        $this->error("Pool {$poolId}: {$result['error']}");
                    }
                }
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to distribute rewards: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
