<?php

namespace App\Filament\Admin\Resources\BasketAssetResource\Pages;

use App\Domain\Basket\Models\BasketAsset;
use App\Filament\Admin\Resources\BasketAssetResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBasketAssets extends ListRecords
{
    protected static string $resource = BasketAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BasketAssetResource\Widgets\BasketStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('All Baskets'),

            'active' => Tab::make()
                ->label('Active')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    /** @var Builder<BasketAsset> $query */
                    return $query->where('is_active', true);
                })
                ->badge(BasketAssetResource::getModel()::where('is_active', true)->count())
                ->badgeColor('success'),

            'fixed' => Tab::make()
                ->label('Fixed Weight')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'fixed'))
                ->badge(BasketAssetResource::getModel()::where('type', 'fixed')->count()),

            'dynamic' => Tab::make()
                ->label('Dynamic Weight')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'dynamic'))
                ->badge(BasketAssetResource::getModel()::where('type', 'dynamic')->count())
                ->badgeColor('warning'),

            'needs_rebalancing' => Tab::make()
                ->label('Needs Rebalancing')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('type', 'dynamic')
                        ->where(
                            function ($q) {
                                $q->whereNull('last_rebalanced_at')
                                    ->orWhere(
                                        function ($q2) {
                                            $q2->where('rebalance_frequency', 'daily')
                                                ->where('last_rebalanced_at', '<', now()->subDay());
                                        }
                                    )
                                    ->orWhere(
                                        function ($q2) {
                                            $q2->where('rebalance_frequency', 'weekly')
                                                ->where('last_rebalanced_at', '<', now()->subWeek());
                                        }
                                    )
                                    ->orWhere(
                                        function ($q2) {
                                            $q2->where('rebalance_frequency', 'monthly')
                                                ->where('last_rebalanced_at', '<', now()->subMonth());
                                        }
                                    )
                                    ->orWhere(
                                        function ($q2) {
                                            $q2->where('rebalance_frequency', 'quarterly')
                                                ->where('last_rebalanced_at', '<', now()->subQuarter());
                                        }
                                    );
                            }
                        )
                )
                ->badge(
                    fn () => BasketAssetResource::getModel()::query()
                        ->where('type', 'dynamic')
                        ->where(
                            function ($q) {
                                $q->whereNull('last_rebalanced_at')
                                    ->orWhere(
                                        function ($q2) {
                                            $q2->where('rebalance_frequency', 'daily')
                                                ->where('last_rebalanced_at', '<', now()->subDay());
                                        }
                                    )
                                    ->orWhere(
                                        function ($q2) {
                                            $q2->where('rebalance_frequency', 'weekly')
                                                ->where('last_rebalanced_at', '<', now()->subWeek());
                                        }
                                    )
                                    ->orWhere(
                                        function ($q2) {
                                            $q2->where('rebalance_frequency', 'monthly')
                                                ->where('last_rebalanced_at', '<', now()->subMonth());
                                        }
                                    )
                                    ->orWhere(
                                        function ($q2) {
                                            $q2->where('rebalance_frequency', 'quarterly')
                                                ->where('last_rebalanced_at', '<', now()->subQuarter());
                                        }
                                    );
                            }
                        )
                        ->count()
                )
                ->badgeColor('danger'),
        ];
    }
}
