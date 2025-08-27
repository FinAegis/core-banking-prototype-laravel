<?php

namespace App\Filament\Admin\Resources\BasketAssetResource\Widgets;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class BasketStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalBaskets = BasketAsset::count();
        $activeBaskets = BasketAsset::where('is_active', true)->count();
        $dynamicBaskets = BasketAsset::where('type', 'dynamic')->count();
        $needsRebalancing = BasketAsset::where('type', 'dynamic')
            ->get()
            ->filter(fn ($basket) => $basket->needsRebalancing())
            ->count();

        // Get total value across all baskets
        $totalValue = BasketValue::query()
            ->whereIn('basket_code', BasketAsset::where('is_active', true)->pluck('code'))
            ->join(
                DB::raw('(SELECT basket_code, MAX(calculated_at) as latest FROM basket_values GROUP BY basket_code) as latest_values'),
                function ($join) {
                    $join->on('basket_values.basket_code', '=', 'latest_values.basket_code')
                        ->on('basket_values.calculated_at', '=', 'latest_values.latest');
                }
            )
            ->sum('value');

        return [
            Stat::make('Total Baskets', $totalBaskets)
                ->description($activeBaskets . ' active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart([7, 3, 4, 5, 6, 8, 5])
                ->color('primary'),

            Stat::make('Dynamic Baskets', $dynamicBaskets)
                ->description($needsRebalancing . ' need rebalancing')
                ->descriptionIcon($needsRebalancing > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($needsRebalancing > 0 ? 'warning' : 'success'),

            Stat::make('Total Value', '$' . Number::format($totalValue, 2))
                ->description('Across all active baskets')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}
