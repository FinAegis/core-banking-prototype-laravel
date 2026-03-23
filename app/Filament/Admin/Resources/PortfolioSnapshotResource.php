<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Treasury\Models\PortfolioSnapshot;
use App\Filament\Admin\Resources\PortfolioSnapshotResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PortfolioSnapshotResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = PortfolioSnapshot::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Treasury';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Portfolio Snapshots';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Snapshot Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('aggregate_uuid')
                                    ->label('Portfolio UUID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('aggregate_version')
                                    ->label('Aggregate Version')
                                    ->disabled(),
                                Forms\Components\TextInput::make('snapshot_version')
                                    ->label('Snapshot Version')
                                    ->disabled(),
                                Forms\Components\TextInput::make('created_at')
                                    ->label('Created At')
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Portfolio State')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('state.name')
                                    ->label('Portfolio Name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('state.totalValue')
                                    ->label('Total Value')
                                    ->prefix('$')
                                    ->disabled(),
                                Forms\Components\TextInput::make('state.status')
                                    ->label('Status')
                                    ->disabled(),
                                Forms\Components\TextInput::make('state.portfolioId')
                                    ->label('Portfolio ID')
                                    ->disabled(),
                            ]
                        )->columns(2),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('aggregate_uuid')
                        ->label('Portfolio UUID')
                        ->searchable()
                        ->limit(12)
                        ->tooltip(fn ($state) => $state)
                        ->sortable(),
                    Tables\Columns\TextColumn::make('state.name')
                        ->label('Name')
                        ->searchable(query: function ($query, string $search) {
                            $query->where('state', 'like', "%\"name\":\"%{$search}%\"");
                        })
                        ->placeholder('—'),
                    Tables\Columns\TextColumn::make('state.totalValue')
                        ->label('Total Value')
                        ->money('USD')
                        ->sortable(query: function ($query, string $direction) {
                            $query->orderByRaw("JSON_EXTRACT(state, '$.totalValue') {$direction}");
                        })
                        ->placeholder('—'),
                    Tables\Columns\TextColumn::make('state.status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst((string) $state))
                        ->color(
                            fn ($state): string => match ((string) $state) {
                                'active'   => 'success',
                                'inactive' => 'gray',
                                default    => 'gray',
                            }
                        )
                        ->placeholder('—'),
                    Tables\Columns\TextColumn::make('aggregate_version')
                        ->label('Version')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Snapshot Date')
                        ->dateTime()
                        ->sortable(),
                ]
            )
            ->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListPortfolioSnapshots::route('/'),
            'view'  => Pages\ViewPortfolioSnapshot::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
