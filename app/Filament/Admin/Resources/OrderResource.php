<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Filament\Admin\Resources\OrderResource\RelationManagers;
use App\Domain\Exchange\Projections\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';
    
    protected static ?string $navigationGroup = 'Exchange';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Details')
                    ->schema([
                        Forms\Components\TextInput::make('order_id')
                            ->label('Order ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('account_id')
                            ->label('Account ID')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'open' => 'Open',
                                'partially_filled' => 'Partially Filled',
                                'filled' => 'Filled',
                                'cancelled' => 'Cancelled',
                            ])
                            ->disabled(),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Trading Details')
                    ->schema([
                        Forms\Components\TextInput::make('base_currency')
                            ->disabled(),
                        Forms\Components\TextInput::make('quote_currency')
                            ->disabled(),
                        Forms\Components\TextInput::make('type')
                            ->disabled(),
                        Forms\Components\TextInput::make('order_type')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->disabled(),
                        Forms\Components\TextInput::make('price')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pair')
                    ->label('Pair')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'buy',
                        'danger' => 'sell',
                    ]),
                Tables\Columns\TextColumn::make('order_type')
                    ->label('Order Type')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric(8),
                Tables\Columns\TextColumn::make('price')
                    ->numeric(2)
                    ->placeholder('Market'),
                Tables\Columns\TextColumn::make('filled_percentage')
                    ->label('Filled')
                    ->suffix('%')
                    ->numeric(1),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'open',
                        'primary' => 'partially_filled',
                        'success' => 'filled',
                        'secondary' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('account.user.name')
                    ->label('User')
                    ->searchable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'open' => 'Open',
                        'partially_filled' => 'Partially Filled',
                        'filled' => 'Filled',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'buy' => 'Buy',
                        'sell' => 'Sell',
                    ]),
                Tables\Filters\SelectFilter::make('base_currency')
                    ->options(fn () => \App\Models\Asset::where('is_tradeable', true)->pluck('code', 'code')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->canBeCancelled())
                    ->action(fn (Order $record) => app(\App\Domain\Exchange\Services\ExchangeService::class)->cancelOrder($record->order_id)),
            ])
            ->bulkActions([
                // No bulk actions for orders
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return false; // Orders are created through the exchange interface
    }
}
