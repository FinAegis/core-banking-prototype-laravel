<?php

namespace App\Filament\Admin\Resources\WebhookResource\Pages;

use App\Filament\Admin\Resources\WebhookResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;

class ViewWebhook extends ViewRecord
{
    protected static string $resource = WebhookResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
    
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Webhook Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('url'),
                                TextEntry::make('is_active')
                                    ->label('Status')
                                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'success' : 'danger'),
                                TextEntry::make('events')
                                    ->badge()
                                    ->separator(','),
                            ]),
                    ]),
                Section::make('Configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('timeout_seconds')
                                    ->label('Timeout')
                                    ->formatStateUsing(fn ($state) => $state . ' seconds'),
                                TextEntry::make('retry_attempts')
                                    ->label('Retry Attempts')
                                    ->formatStateUsing(fn ($state) => $state . ' attempts'),
                                KeyValueEntry::make('headers')
                                    ->label('Headers'),
                            ]),
                    ]),
                Section::make('Delivery Status')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('last_triggered_at')
                                    ->dateTime(),
                                TextEntry::make('last_success_at')
                                    ->dateTime(),
                                TextEntry::make('consecutive_failures')
                                    ->badge()
                                    ->color(fn ($state) => $state > 0 ? ($state >= 5 ? 'danger' : 'warning') : 'gray'),
                            ]),
                        TextEntry::make('deliveries')
                            ->label('Last Delivery')
                            ->formatStateUsing(function ($record) {
                                $lastDelivery = $record->deliveries()->latest()->first();
                                if (!$lastDelivery) {
                                    return 'No deliveries yet';
                                }
                                return sprintf(
                                    '%s - Response: %d',
                                    ucfirst($lastDelivery->status),
                                    $lastDelivery->response_code
                                );
                            })
                            ->badge()
                            ->color(function ($record) {
                                $lastDelivery = $record->deliveries()->latest()->first();
                                if (!$lastDelivery) {
                                    return 'gray';
                                }
                                return $lastDelivery->status === 'success' ? 'success' : 'danger';
                            }),
                    ]),
            ]);
    }
}