<?php

namespace App\Console\Commands;

use App\Domain\Basket\Services\BasketValueCalculationService;
use App\Models\BasketAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ShowBasketPerformanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baskets:performance 
                            {basket? : Basket code (defaults to GCU)}
                            {--period=30d : Performance period (1d, 7d, 30d, 90d, 1y)}
                            {--detailed : Show component breakdown}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display basket performance analytics and value history';

    /**
     * Execute the console command.
     */
    public function handle(BasketValueCalculationService $valueService): int
    {
        $basketCode = $this->argument('basket') ?? config('baskets.primary_code', 'GCU');
        $period = $this->option('period');
        $detailed = $this->option('detailed');
        
        // Find the basket
        $basket = BasketAsset::where('code', $basketCode)->first();
        
        if (!$basket) {
            $this->error("Basket '{$basketCode}' not found.");
            return Command::FAILURE;
        }
        
        $this->info("🧺 Basket Performance: {$basket->name} ({$basket->code})");
        $this->newLine();
        
        // Calculate current value
        $currentValue = $valueService->calculateValue($basket);
        $this->displayCurrentValue($basket, $currentValue);
        
        // Calculate performance
        $this->displayPerformance($basket, $valueService, $period);
        
        // Show component breakdown if requested
        if ($detailed) {
            $this->displayComponentBreakdown($basket, $currentValue);
        }
        
        // Show recent value history
        $this->displayRecentHistory($basket, $valueService);
        
        // Show rebalancing info
        $this->displayRebalancingInfo($basket);
        
        return Command::SUCCESS;
    }
    
    /**
     * Display current basket value
     */
    private function displayCurrentValue(BasketAsset $basket, $value): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Current Value (USD)', '$' . number_format($value->value, 2)],
                ['Last Calculated', $value->calculated_at->format('Y-m-d H:i:s')],
                ['Components', $basket->components()->where('is_active', true)->count()],
                ['Type', ucfirst($basket->type)],
            ]
        );
    }
    
    /**
     * Display performance metrics
     */
    private function displayPerformance(BasketAsset $basket, BasketValueCalculationService $valueService, string $period): void
    {
        $this->info("📊 Performance Metrics ({$period}):");
        
        // Parse period
        $startDate = match($period) {
            '1d' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30),
        };
        
        $performance = $valueService->calculatePerformance($basket, $startDate, now());
        
        if (!empty($performance)) {
            $changeClass = $performance['percentage_change'] >= 0 ? 'info' : 'error';
            $changeSymbol = $performance['percentage_change'] >= 0 ? '+' : '';
            
            $this->table(
                ['Period', 'Start Value', 'End Value', 'Change ($)', 'Change (%)'],
                [[
                    $period,
                    '$' . number_format($performance['start_value'], 2),
                    '$' . number_format($performance['end_value'], 2),
                    $changeSymbol . '$' . number_format($performance['absolute_change'], 2),
                    $changeSymbol . number_format($performance['percentage_change'], 2) . '%',
                ]]
            );
        } else {
            $this->warn("No performance data available for the selected period.");
        }
        
        $this->newLine();
    }
    
    /**
     * Display component breakdown
     */
    private function displayComponentBreakdown(BasketAsset $basket, $value): void
    {
        $this->info("🔍 Component Breakdown:");
        
        $components = $basket->components()
            ->where('is_active', true)
            ->with('asset')
            ->get();
        
        $componentData = [];
        $componentValues = $value->component_values ?? [];
        
        foreach ($components as $component) {
            $componentValue = $componentValues[$component->asset_code] ?? null;
            
            $componentData[] = [
                $component->asset_code,
                number_format($component->weight, 2) . '%',
                $componentValue ? '$' . number_format($componentValue['weighted_value'], 2) : 'N/A',
                $componentValue ? '$' . number_format($componentValue['rate'], 4) : 'N/A',
            ];
        }
        
        $this->table(
            ['Asset', 'Target Weight', 'Value Contribution', 'Exchange Rate'],
            $componentData
        );
        
        $this->newLine();
    }
    
    /**
     * Display recent value history
     */
    private function displayRecentHistory(BasketAsset $basket, BasketValueCalculationService $valueService): void
    {
        $this->info("📈 Recent Value History (Last 7 Days):");
        
        $history = $valueService->getHistoricalValues($basket, now()->subDays(7), now());
        
        if ($history->isEmpty()) {
            $this->warn("No historical data available.");
            return;
        }
        
        $historyData = [];
        $previousValue = null;
        
        foreach ($history as $value) {
            $change = '';
            if ($previousValue) {
                $diff = $value->value - $previousValue;
                $change = ($diff >= 0 ? '+' : '') . number_format($diff, 2);
            }
            
            $historyData[] = [
                $value->calculated_at->format('Y-m-d H:i'),
                '$' . number_format($value->value, 2),
                $change,
            ];
            
            $previousValue = $value->value;
        }
        
        $this->table(
            ['Date', 'Value', 'Change'],
            array_slice($historyData, -10) // Show last 10 entries
        );
        
        $this->newLine();
    }
    
    /**
     * Display rebalancing information
     */
    private function displayRebalancingInfo(BasketAsset $basket): void
    {
        if ($basket->type !== 'dynamic') {
            return;
        }
        
        $this->info("⚖️ Rebalancing Information:");
        
        $nextRebalance = $basket->metadata['next_rebalance'] ?? null;
        $lastRebalanced = $basket->last_rebalanced_at;
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Rebalance Frequency', ucfirst($basket->rebalance_frequency)],
                ['Last Rebalanced', $lastRebalanced ? $lastRebalanced->format('Y-m-d H:i:s') : 'Never'],
                ['Next Rebalance', $nextRebalance ?? 'Not scheduled'],
                ['Voting Enabled', ($basket->metadata['voting_enabled'] ?? false) ? 'Yes' : 'No'],
            ]
        );
    }
}
