<?php

namespace App\Console\Commands;

use App\Domain\Basket\Services\BasketRebalancingService;
use App\Models\BasketAsset;
use Illuminate\Console\Command;

class RebalanceBasketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baskets:rebalance 
                            {--basket= : Specific basket code to rebalance}
                            {--force : Force rebalancing even if not due}
                            {--dry-run : Simulate rebalancing without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebalance dynamic baskets based on their rebalancing frequency';

    /**
     * Execute the console command.
     */
    public function handle(BasketRebalancingService $rebalancingService): int
    {
        $basketCode = $this->option('basket');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        if ($basketCode) {
            // Rebalance specific basket
            $basket = BasketAsset::where('code', $basketCode)->first();
            
            if (!$basket) {
                $this->error("Basket with code '{$basketCode}' not found.");
                return Command::FAILURE;
            }

            if ($basket->type !== 'dynamic') {
                $this->error("Basket '{$basketCode}' is not a dynamic basket.");
                return Command::FAILURE;
            }

            $this->info("Processing basket: {$basket->name} ({$basket->code})");
            
            if ($dryRun) {
                $result = $rebalancingService->simulateRebalancing($basket);
                $this->displaySimulationResults($basket, $result);
            } else {
                if (!$force && !$rebalancingService->needsRebalancing($basket)) {
                    $this->info("Basket does not need rebalancing yet. Use --force to override.");
                    return Command::SUCCESS;
                }

                $result = $rebalancingService->rebalance($basket);
                $this->displayRebalancingResults($basket, $result);
            }
        } else {
            // Rebalance all baskets that need it
            $this->info('Checking all dynamic baskets for rebalancing...');
            
            $baskets = BasketAsset::where('type', 'dynamic')
                ->where('is_active', true)
                ->get();

            if ($baskets->isEmpty()) {
                $this->info('No dynamic baskets found.');
                return Command::SUCCESS;
            }

            $rebalancedCount = 0;
            
            foreach ($baskets as $basket) {
                $this->info("\nProcessing basket: {$basket->name} ({$basket->code})");
                
                if ($dryRun) {
                    $result = $rebalancingService->simulateRebalancing($basket);
                    $this->displaySimulationResults($basket, $result);
                    $rebalancedCount++;
                } else {
                    if ($force || $rebalancingService->needsRebalancing($basket)) {
                        $result = $rebalancingService->rebalance($basket);
                        $this->displayRebalancingResults($basket, $result);
                        $rebalancedCount++;
                    } else {
                        $this->info("Basket does not need rebalancing yet.");
                    }
                }
            }

            $this->info("\nCompleted. {$rebalancedCount} basket(s) processed.");
        }

        return Command::SUCCESS;
    }

    /**
     * Display simulation results
     */
    private function displaySimulationResults(BasketAsset $basket, array $result): void
    {
        $this->info("Simulation results for {$basket->name}:");
        
        if (empty($result['adjustments'])) {
            $this->info("No adjustments needed.");
            return;
        }

        $this->table(
            ['Asset', 'Current Weight', 'New Weight', 'Change'],
            collect($result['adjustments'])->map(function ($adjustment) {
                return [
                    $adjustment['asset_code'],
                    number_format($adjustment['old_weight'], 2) . '%',
                    number_format($adjustment['new_weight'], 2) . '%',
                    ($adjustment['adjustment'] > 0 ? '+' : '') . number_format($adjustment['adjustment'], 2) . '%',
                ];
            })->toArray()
        );
    }

    /**
     * Display rebalancing results
     */
    private function displayRebalancingResults(BasketAsset $basket, array $result): void
    {
        if ($result['status'] === 'completed') {
            $this->info("✅ Basket rebalanced successfully!");
            
            if (!empty($result['adjustments'])) {
                $this->table(
                    ['Asset', 'Old Weight', 'New Weight', 'Change'],
                    collect($result['adjustments'])->map(function ($adjustment) {
                        return [
                            $adjustment['asset_code'],
                            number_format($adjustment['old_weight'], 2) . '%',
                            number_format($adjustment['new_weight'], 2) . '%',
                            ($adjustment['adjustment'] > 0 ? '+' : '') . number_format($adjustment['adjustment'], 2) . '%',
                        ];
                    })->toArray()
                );
            } else {
                $this->info("No adjustments were made.");
            }
        } else {
            $this->error("❌ Rebalancing failed: " . ($result['message'] ?? 'Unknown error'));
        }
    }
}
