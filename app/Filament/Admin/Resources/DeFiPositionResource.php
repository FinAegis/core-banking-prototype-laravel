<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\DeFi\Models\DeFiPosition;
use App\Filament\Admin\Resources\DeFiPositionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeFiPositionResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = DeFiPosition::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'DeFi';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'DeFi Positions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Position Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('id')
                                    ->label('Position ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('protocol')
                                    ->formatStateUsing(fn ($state) => $state->getDisplayName())
                                    ->disabled(),
                                Forms\Components\TextInput::make('type')
                                    ->formatStateUsing(fn ($state) => ucfirst($state->value))
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn ($state) => ucfirst($state->value))
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Asset Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('chain')
                                    ->formatStateUsing(fn ($state) => ucfirst($state->value))
                                    ->disabled(),
                                Forms\Components\TextInput::make('asset')
                                    ->disabled(),
                                Forms\Components\TextInput::make('amount')
                                    ->disabled(),
                                Forms\Components\TextInput::make('value_usd')
                                    ->label('Value (USD)')
                                    ->prefix('$')
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Performance')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('apy')
                                    ->label('APY (%)')
                                    ->suffix('%')
                                    ->disabled(),
                                Forms\Components\TextInput::make('health_factor')
                                    ->label('Health Factor')
                                    ->disabled(),
                                Forms\Components\TextInput::make('opened_at')
                                    ->label('Opened At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('closed_at')
                                    ->label('Closed At')
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
                    Tables\Columns\TextColumn::make('protocol')
                        ->formatStateUsing(fn ($state) => $state->getDisplayName())
                        ->sortable()
                        ->searchable(),
                    Tables\Columns\TextColumn::make('type')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst($state->value))
                        ->color(
                            fn ($state): string => match ($state->value) {
                                'swap'        => 'info',
                                'supply'      => 'success',
                                'borrow'      => 'warning',
                                'lp'          => 'primary',
                                'stake'       => 'success',
                                'yield_vault' => 'primary',
                                default       => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('chain')
                        ->formatStateUsing(fn ($state) => ucfirst($state->value))
                        ->sortable(),
                    Tables\Columns\TextColumn::make('asset')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('amount')
                        ->numeric(8)
                        ->sortable(),
                    Tables\Columns\TextColumn::make('value_usd')
                        ->label('Value')
                        ->money('USD')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('apy')
                        ->label('APY')
                        ->suffix('%')
                        ->numeric(2)
                        ->sortable(),
                    Tables\Columns\TextColumn::make('health_factor')
                        ->label('Health')
                        ->numeric(2)
                        ->color(
                            fn ($state): string => match (true) {
                                $state === null => 'gray',
                                $state < 1.1    => 'danger',
                                $state < 1.5    => 'warning',
                                default         => 'success',
                            }
                        )
                        ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst($state->value))
                        ->color(
                            fn ($state): string => match ($state->value) {
                                'active'     => 'success',
                                'closed'     => 'gray',
                                'liquidated' => 'danger',
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
                            collect(\App\Domain\DeFi\Enums\DeFiPositionStatus::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('protocol')
                        ->options(
                            collect(\App\Domain\DeFi\Enums\DeFiProtocol::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->getDisplayName()])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('type')
                        ->options(
                            collect(\App\Domain\DeFi\Enums\DeFiPositionType::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => ucfirst($case->value)])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('chain')
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
            'index' => Pages\ListDeFiPositions::route('/'),
            'view'  => Pages\ViewDeFiPosition::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
