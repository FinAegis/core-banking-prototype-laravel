<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExchangeRateResource\Widgets;

use App\Domain\Asset\Models\ExchangeRate;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExchangeRateStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRates = ExchangeRate::count();
        $validRates = ExchangeRate::valid()->count();
        $staleRates = ExchangeRate::where('valid_at', '<=', now()->subDay())->count();
        $expiredRates = ExchangeRate::where('expires_at', '<=', now())->count();

        $validPercentage = $totalRates > 0 ? round(($validRates / $totalRates) * 100, 1) : 0;
        $stalePercentage = $totalRates > 0 ? round(($staleRates / $totalRates) * 100, 1) : 0;

        return [
            Stat::make('Total Rates', $totalRates)
                ->description('All exchange rates')
                ->icon('heroicon-m-arrow-path')
                ->color('primary'),

            Stat::make('Valid Rates', $validRates)
                ->description("{$validPercentage}% of total")
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->descriptionIcon('heroicon-m-arrow-trending-up'),

            Stat::make('Stale Rates', $staleRates)
                ->description("{$stalePercentage}% older than 24 hours")
                ->icon('heroicon-m-clock')
                ->color($staleRates > 0 ? 'warning' : 'success')
                ->descriptionIcon($staleRates > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check'),

            Stat::make('Expired Rates', $expiredRates)
                ->description('Need renewal')
                ->icon('heroicon-m-x-circle')
                ->color($expiredRates > 0 ? 'danger' : 'success')
                ->descriptionIcon($expiredRates > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check'),
        ];
    }
}
