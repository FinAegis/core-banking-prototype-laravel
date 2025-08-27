<?php

namespace App\Domain\Basket\Console\Commands;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketPerformanceService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CalculateBasketPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'basket:calculate-performance 
                            {--basket= : Calculate for specific basket code}
                            {--period= : Calculate for specific period (hour, day, week, month, quarter, year, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate performance metrics for basket assets';

    /**
     * Execute the console command.
     */
    public function handle(BasketPerformanceService $performanceService): int
    {
        $basketCode = $this->option('basket');
        $period = $this->option('period');

        // Get baskets to process
        $query = BasketAsset::active();
        if ($basketCode) {
            $query->where('code', $basketCode);
        }

        $baskets = $query->get();

        if ($baskets->isEmpty()) {
            $this->error('No active baskets found to process.');

            return 1;
        }

        $this->info("Processing performance for {$baskets->count()} basket(s)...");

        foreach ($baskets as $basket) {
            $this->info("Calculating performance for basket: {$basket->code} - {$basket->name}");

            try {
                if ($period && $period !== 'all') {
                    // Calculate specific period
                    $now = now();
                    [$periodStart, $periodEnd] = match ($period) {
                        'hour'    => [$now->copy()->subHour(), $now],
                        'day'     => [$now->copy()->subDay(), $now],
                        'week'    => [$now->copy()->subWeek(), $now],
                        'month'   => [$now->copy()->subMonth(), $now],
                        'quarter' => [$now->copy()->subQuarter(), $now],
                        'year'    => [$now->copy()->subYear(), $now],
                        default   => throw new InvalidArgumentException("Invalid period: {$period}")
                    };

                    $performance = $performanceService->calculatePerformance(
                        $basket,
                        $period,
                        $periodStart,
                        $periodEnd
                    );

                    if ($performance) {
                        $this->info("  - {$period}: {$performance->formatted_return} (volatility: {$performance->volatility}%)");
                    } else {
                        $this->warn("  - {$period}: Insufficient data");
                    }
                } else {
                    // Calculate all periods
                    $performances = $performanceService->calculateAllPeriods($basket);

                    foreach ($performances as $performance) {
                        $this->info("  - {$performance->period_type}: {$performance->formatted_return} (volatility: {$performance->volatility}%)");
                    }

                    if ($performances->isEmpty()) {
                        $this->warn('  - No performance data could be calculated');
                    }
                }

                // Show top performers
                $topPerformers = $performanceService->getTopPerformers($basket, 'month', 3);
                if ($topPerformers->isNotEmpty()) {
                    $this->info('  Top performers (monthly):');
                    foreach ($topPerformers as $performer) {
                        $this->info("    - {$performer->asset_code}: {$performer->formatted_contribution}");
                    }
                }

                // Show worst performers
                $worstPerformers = $performanceService->getWorstPerformers($basket, 'month', 3);
                if ($worstPerformers->isNotEmpty()) {
                    $this->info('  Worst performers (monthly):');
                    foreach ($worstPerformers as $performer) {
                        $this->info("    - {$performer->asset_code}: {$performer->formatted_contribution}");
                    }
                }
            } catch (Exception $e) {
                $this->error("  Error calculating performance: {$e->getMessage()}");
                Log::error(
                    'Basket performance calculation failed',
                    [
                    'basket' => $basket->code,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString(),
                    ]
                );
            }
        }

        $this->info('Performance calculation completed.');

        return 0;
    }
}
