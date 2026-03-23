<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\CrossChain\Models\BridgeTransaction;
use App\Filament\Admin\Resources\BridgeTransactionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BridgeTransactionResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = BridgeTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Cross-Chain';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Bridge Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('id')
                                    ->label('Transaction ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('provider')
                                    ->label('Bridge Provider')
                                    ->formatStateUsing(fn ($state) => $state->getDisplayName())
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn ($state) => ucfirst($state->value))
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Chain Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('source_chain')
                                    ->label('Source Chain')
                                    ->formatStateUsing(fn ($state) => ucfirst($state->value))
                                    ->disabled(),
                                Forms\Components\TextInput::make('dest_chain')
                                    ->label('Destination Chain')
                                    ->formatStateUsing(fn ($state) => ucfirst($state->value))
                                    ->disabled(),
                                Forms\Components\TextInput::make('token')
                                    ->disabled(),
                                Forms\Components\TextInput::make('amount')
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Addresses & Hashes')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('sender_address')
                                    ->label('Sender')
                                    ->disabled(),
                                Forms\Components\TextInput::make('recipient_address')
                                    ->label('Recipient')
                                    ->disabled(),
                                Forms\Components\TextInput::make('source_tx_hash')
                                    ->label('Source TX Hash')
                                    ->disabled(),
                                Forms\Components\TextInput::make('dest_tx_hash')
                                    ->label('Destination TX Hash')
                                    ->disabled(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Fees & Timing')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('fee_amount')
                                    ->label('Fee Amount')
                                    ->disabled(),
                                Forms\Components\TextInput::make('fee_currency')
                                    ->label('Fee Currency')
                                    ->disabled(),
                                Forms\Components\TextInput::make('created_at')
                                    ->label('Initiated At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('completed_at')
                                    ->label('Completed At')
                                    ->disabled(),
                            ]
                        )->columns(4),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Date')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('provider')
                        ->label('Provider')
                        ->formatStateUsing(fn ($state) => $state->getDisplayName())
                        ->sortable(),
                    Tables\Columns\TextColumn::make('source_chain')
                        ->label('From')
                        ->formatStateUsing(fn ($state) => ucfirst($state->value))
                        ->sortable(),
                    Tables\Columns\TextColumn::make('dest_chain')
                        ->label('To')
                        ->formatStateUsing(fn ($state) => ucfirst($state->value))
                        ->sortable(),
                    Tables\Columns\TextColumn::make('token')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('amount')
                        ->numeric(8)
                        ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst($state->value))
                        ->color(
                            fn ($state): string => match ($state->value) {
                                'initiated'  => 'gray',
                                'bridging'   => 'info',
                                'confirming' => 'warning',
                                'completed'  => 'success',
                                'failed'     => 'danger',
                                'refunded'   => 'gray',
                                default      => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('User')
                        ->searchable()
                        ->toggleable(),
                ]
            )
            ->defaultSort('created_at', 'desc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            collect(\App\Domain\CrossChain\Enums\BridgeStatus::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('provider')
                        ->options(
                            collect(\App\Domain\CrossChain\Enums\BridgeProvider::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->getDisplayName()])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('source_chain')
                        ->label('Source Chain')
                        ->options(
                            collect(\App\Domain\CrossChain\Enums\CrossChainNetwork::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
                                ->all()
                        ),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                ]
            )
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBridgeTransactions::route('/'),
            'view'  => Pages\ViewBridgeTransaction::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
