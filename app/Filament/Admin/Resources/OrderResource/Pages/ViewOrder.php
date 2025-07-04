<?php

namespace App\Filament\Admin\Resources\OrderResource\Pages;

use App\Filament\Admin\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canBeCancelled())
                ->action(function () {
                    app(\App\Domain\Exchange\Services\ExchangeService::class)->cancelOrder($this->record->order_id);
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}