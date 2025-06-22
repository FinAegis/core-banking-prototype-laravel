<?php

namespace App\Filament\Admin\Widgets;

use App\Domain\Basket\Services\BasketPerformanceService;
use App\Models\BasketAsset;
use App\Models\BasketPerformance;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class BasketPerformanceStats extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $gcuBasket = Cache::remember('gcu_basket_stats', 300, function () {
            return BasketAsset::where('code', 'GCU')->first();
        });

        if (!$gcuBasket) {
            return [];
        }

        $performanceService = app(BasketPerformanceService::class);
        $summary = Cache::remember('gcu_performance_summary', 300, function () use ($gcuBasket, $performanceService) {
            return $performanceService->getPerformanceSummary($gcuBasket);
        });

        $dayPerf = $summary['performances']['day'] ?? null;
        $weekPerf = $summary['performances']['week'] ?? null;
        $monthPerf = $summary['performances']['month'] ?? null;
        $yearPerf = $summary['performances']['year'] ?? null;

        $stats = [];

        // Current Value
        $stats[] = Stat::make('GCU Current Value', '$' . number_format($summary['current_value'], 4))
            ->description('Per unit value in USD')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color('primary');

        // Daily Performance
        if ($dayPerf) {
            $stats[] = Stat::make('24h Performance', $dayPerf['formatted_return'])
                ->description('Risk: ' . str_replace('_', ' ', $dayPerf['risk_rating']))
                ->descriptionIcon($dayPerf['return_percentage'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($dayPerf['return_percentage'] >= 0 ? 'success' : 'danger')
                ->chart($this->getSparklineData('day', 7));
        }

        // Weekly Performance
        if ($weekPerf) {
            $stats[] = Stat::make('7d Performance', $weekPerf['formatted_return'])
                ->description('Volatility: ' . number_format($weekPerf['volatility'], 2) . '%')
                ->descriptionIcon($weekPerf['return_percentage'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weekPerf['return_percentage'] >= 0 ? 'success' : 'danger');
        }

        // Monthly Performance
        if ($monthPerf) {
            $stats[] = Stat::make('30d Performance', $monthPerf['formatted_return'])
                ->description('Sharpe Ratio: ' . number_format($monthPerf['sharpe_ratio'] ?? 0, 2))
                ->descriptionIcon($monthPerf['return_percentage'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthPerf['return_percentage'] >= 0 ? 'success' : 'danger')
                ->chart($this->getSparklineData('month', 6));
        }

        // Yearly Performance
        if ($yearPerf) {
            $stats[] = Stat::make('YTD Performance', $yearPerf['formatted_return'])
                ->description('Rating: ' . str_replace('_', ' ', $yearPerf['performance_rating']))
                ->descriptionIcon($yearPerf['return_percentage'] >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($yearPerf['return_percentage'] >= 0 ? 'success' : 'danger');
        }

        // Top Performer
        $topPerformer = Cache::remember('gcu_top_performer', 300, function () use ($gcuBasket, $performanceService) {
            return $performanceService->getTopPerformers($gcuBasket, 'month', 1)->first();
        });

        if ($topPerformer) {
            $stats[] = Stat::make('Top Performer', $topPerformer->asset_code)
                ->description('Contribution: ' . $topPerformer->formatted_contribution)
                ->descriptionIcon('heroicon-m-star')
                ->color('warning');
        }

        return $stats;
    }

    protected function getSparklineData(string $periodType, int $points): array
    {
        $gcuBasket = BasketAsset::where('code', 'GCU')->first();
        
        if (!$gcuBasket) {
            return [];
        }

        $performances = $gcuBasket->performances()
            ->where('period_type', $periodType)
            ->orderBy('period_end', 'desc')
            ->limit($points)
            ->pluck('return_percentage')
            ->reverse()
            ->values()
            ->toArray();

        return $performances;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('view_basket_performance') ?? true;
    }
}