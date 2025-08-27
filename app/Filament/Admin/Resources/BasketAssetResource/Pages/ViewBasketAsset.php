<?php

namespace App\Filament\Admin\Resources\BasketAssetResource\Pages;

use App\Filament\Admin\Resources\BasketAssetResource;
use Exception;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBasketAsset extends ViewRecord
{
    protected static string $resource = BasketAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('calculate_value')
                ->label('Calculate Value')
                ->icon('heroicon-m-calculator')
                ->color('info')
                ->action(
                    function () {
                        try {
                            $service = app(\App\Domain\Basket\Services\BasketValueCalculationService::class);
                            $value = $service->calculateValue($this->getRecord(), false);

                            $this->notify('success', "Current value: \${$value->value}");
                        } catch (Exception $e) {
                            $this->notify('danger', $e->getMessage());
                        }
                    }
                ),

            Actions\Action::make('rebalance')
                ->label('Rebalance')
                ->icon('heroicon-m-scale')
                ->color('warning')
                ->visible(fn () => $this->getRecord()->type === 'dynamic')
                ->requiresConfirmation()
                ->modalHeading('Rebalance Basket')
                ->modalDescription('This will adjust the component weights to their target values.')
                ->modalSubmitActionLabel('Rebalance')
                ->action(
                    function () {
                        try {
                            $service = app(\App\Domain\Basket\Services\BasketRebalancingService::class);
                            $result = $service->rebalance($this->getRecord());

                            $this->notify('success', "Basket rebalanced: {$result['adjustments_count']} components adjusted");
                        } catch (Exception $e) {
                            $this->notify('danger', $e->getMessage());
                        }
                    }
                ),

            Actions\Action::make('calculate_performance')
                ->label('Calculate Performance')
                ->icon('heroicon-m-chart-bar')
                ->color('success')
                ->action(
                    function () {
                        try {
                            $service = app(\App\Domain\Basket\Services\BasketPerformanceService::class);
                            $performances = $service->calculateAllPeriods($this->getRecord());

                            $this->notify('success', "Calculated performance for {$performances->count()} periods");
                        } catch (Exception $e) {
                            $this->notify('danger', $e->getMessage());
                        }
                    }
                ),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BasketAssetResource\Widgets\BasketValueChart::class,
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            // Existing relation managers
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            BasketAssetResource\Widgets\BasketPerformanceWidget::class,
        ];
    }
}
