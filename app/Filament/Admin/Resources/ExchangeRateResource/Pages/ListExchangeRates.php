<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExchangeRateResource\Pages;

use App\Domain\Asset\Models\ExchangeRate;
use App\Filament\Admin\Resources\ExchangeRateResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Exchange Rate')
                ->icon('heroicon-m-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExchangeRateResource\Widgets\ExchangeRateStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Rates')
                ->icon('heroicon-m-squares-2x2'),

            'valid' => Tab::make('Valid Now')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->valid())
                ->badge(fn () => ExchangeRate::valid()->count()),

            'expired' => Tab::make('Expired')
                ->icon('heroicon-m-x-circle')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    /** @var Builder<ExchangeRate> $query */
                    return $query->where('expires_at', '<=', now());
                })
                ->badge(fn () => ExchangeRate::where('expires_at', '<=', now())->count()),

            'stale' => Tab::make('Stale (>24h)')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    /** @var Builder<ExchangeRate> $query */
                    return $query->where('valid_at', '<=', now()->subDay());
                })
                ->badge(fn () => ExchangeRate::where('valid_at', '<=', now()->subDay())->count()),

            'manual' => Tab::make('Manual')
                ->icon('heroicon-m-pencil-square')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    /** @var Builder<ExchangeRate> $query */
                    return $query->where('source', 'manual');
                })
                ->badge(fn () => ExchangeRate::where('source', 'manual')->count()),

            'api' => Tab::make('API')
                ->icon('heroicon-m-cloud')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    /** @var Builder<ExchangeRate> $query */
                    return $query->where('source', 'api');
                })
                ->badge(fn () => ExchangeRate::where('source', 'api')->count()),
        ];
    }
}
