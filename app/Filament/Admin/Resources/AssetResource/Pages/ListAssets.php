<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AssetResource\Pages;

use App\Domain\Asset\Models\Asset;
use App\Filament\Admin\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Asset')
                ->icon('heroicon-m-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Assets')
                ->icon('heroicon-m-squares-2x2'),

            'fiat' => Tab::make('Fiat Currencies')
                ->icon('heroicon-m-banknotes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'fiat'))
                ->badge(fn () => Asset::where('type', 'fiat')->count()),

            'crypto' => Tab::make('Cryptocurrencies')
                ->icon('heroicon-m-cpu-chip')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'crypto'))
                ->badge(fn () => Asset::where('type', 'crypto')->count()),

            'commodity' => Tab::make('Commodities')
                ->icon('heroicon-m-cube')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'commodity'))
                ->badge(fn () => Asset::where('type', 'commodity')->count()),

            'active' => Tab::make('Active')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    /** @var Builder<Asset> $query */
                    return $query->where('is_active', true);
                })
                ->badge(fn () => Asset::where('is_active', true)->count()),
        ];
    }
}
