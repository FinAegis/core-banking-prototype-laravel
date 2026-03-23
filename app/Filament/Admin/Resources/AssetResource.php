<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Asset\Models\Asset;
use App\Filament\Admin\Resources\AssetResource\Pages;
use App\Filament\Admin\Resources\AssetResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Asset Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Asset Information')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('code')
                                    ->label('Asset Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(10)
                                    ->placeholder('USD, EUR, BTC, etc.')
                                    ->helperText('Unique identifier for the asset (e.g., USD, EUR, BTC)'),

                                Forms\Components\TextInput::make('name')
                                    ->label('Asset Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('US Dollar, Euro, Bitcoin, etc.')
                                    ->helperText('Full name of the asset'),

                                Forms\Components\Select::make('type')
                                    ->label('Asset Type')
                                    ->required()
                                    ->options(
                                        [
                                            'fiat'      => 'Fiat Currency',
                                            'crypto'    => 'Cryptocurrency',
                                            'commodity' => 'Commodity',
                                        ]
                                    )
                                    ->reactive()
                                    ->helperText('Type of asset being added'),

                                Forms\Components\TextInput::make('symbol')
                                    ->label('Symbol')
                                    ->maxLength(10)
                                    ->placeholder('$, €, ₿, etc.')
                                    ->helperText('Display symbol for the asset'),

                                Forms\Components\TextInput::make('precision')
                                    ->label('Decimal Precision')
                                    ->required()
                                    ->numeric()
                                    ->default(
                                        fn (Forms\Get $get) => match ($get('type')) {
                                            'fiat'      => 2,
                                            'crypto'    => 8,
                                            'commodity' => 4,
                                            default     => 2,
                                        }
                                    )
                                    ->minValue(0)
                                    ->maxValue(18)
                                    ->helperText('Number of decimal places for this asset'),
                            ]
                        ),

                    Forms\Components\Section::make('Status & Configuration')
                        ->schema(
                            [
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Whether this asset is available for transactions'),

                                Forms\Components\KeyValue::make('metadata')
                                    ->label('Metadata')
                                    ->keyLabel('Property')
                                    ->valueLabel('Value')
                                    ->helperText('Additional properties and configuration for this asset')
                                    ->columnSpanFull(),
                            ]
                        ),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('code')
                        ->label('Code')
                        ->searchable()
                        ->sortable()
                        ->weight('bold')
                        ->badge()
                        ->color(
                            fn (string $state): string => match (true) {
                                in_array($state, ['USD', 'EUR', 'GBP']) => 'success',
                                in_array($state, ['BTC', 'ETH'])        => 'warning',
                                default                                 => 'primary',
                            }
                        ),

                    Tables\Columns\TextColumn::make('name')
                        ->label('Name')
                        ->searchable()
                        ->sortable(),

                    Tables\Columns\TextColumn::make('type')
                        ->label('Type')
                        ->sortable()
                        ->badge()
                        ->color(
                            fn (string $state): string => match ($state) {
                                'fiat'      => 'success',
                                'crypto'    => 'warning',
                                'commodity' => 'info',
                                default     => 'gray',
                            }
                        ),

                    Tables\Columns\TextColumn::make('symbol')
                        ->label('Symbol')
                        ->placeholder('—'),

                    Tables\Columns\TextColumn::make('precision')
                        ->label('Precision')
                        ->suffix(' decimals')
                        ->alignCenter(),

                    Tables\Columns\IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean()
                        ->alignCenter(),

                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),

                    Tables\Columns\TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]
            )
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('type')
                        ->options(
                            [
                                'fiat'      => 'Fiat Currency',
                                'crypto'    => 'Cryptocurrency',
                                'commodity' => 'Commodity',
                            ]
                        ),

                    Tables\Filters\TernaryFilter::make('is_active')
                        ->label('Active Status')
                        ->placeholder('All assets')
                        ->trueLabel('Active only')
                        ->falseLabel('Inactive only'),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkActionGroup::make(
                        [
                            Tables\Actions\DeleteBulkAction::make()
                                ->requiresConfirmation(),

                            Tables\Actions\BulkAction::make('activate')
                                ->label('Activate')
                                ->icon('heroicon-m-check-circle')
                                ->color('success')
                                ->action(fn ($records) => $records->each->update(['is_active' => true]))
                                ->deselectRecordsAfterCompletion(),

                            Tables\Actions\BulkAction::make('deactivate')
                                ->label('Deactivate')
                                ->icon('heroicon-m-x-circle')
                                ->color('danger')
                                ->action(fn ($records) => $records->each->update(['is_active' => false]))
                                ->requiresConfirmation()
                                ->deselectRecordsAfterCompletion(),
                        ]
                    ),
                ]
            )
            ->defaultSort('code');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(
                [
                    Infolists\Components\Section::make('Asset Information')
                        ->schema(
                            [
                                Infolists\Components\TextEntry::make('code')
                                    ->label('Asset Code')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('name')
                                    ->label('Asset Name'),

                                Infolists\Components\TextEntry::make('type')
                                    ->label('Asset Type')
                                    ->badge()
                                    ->color(
                                        fn (string $state): string => match ($state) {
                                            'fiat'      => 'success',
                                            'crypto'    => 'warning',
                                            'commodity' => 'info',
                                            default     => 'gray',
                                        }
                                    ),

                                Infolists\Components\TextEntry::make('symbol')
                                    ->label('Symbol')
                                    ->placeholder('—'),

                                Infolists\Components\TextEntry::make('precision')
                                    ->label('Decimal Precision')
                                    ->suffix(' decimals'),

                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Active Status')
                                    ->boolean(),
                            ]
                        )
                        ->columns(2),

                    Infolists\Components\Section::make('Metadata')
                        ->schema(
                            [
                                Infolists\Components\KeyValueEntry::make('metadata')
                                    ->label('Asset Properties')
                                    ->columnSpanFull(),
                            ]
                        )
                        ->collapsible(),

                    Infolists\Components\Section::make('System Information')
                        ->schema(
                            [
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Updated At')
                                    ->dateTime(),
                            ]
                        )
                        ->columns(2)
                        ->collapsible(),
                ]
            );
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AccountBalancesRelationManager::class,
            RelationManagers\ExchangeRatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'view'   => Pages\ViewAsset::route('/{record}'),
            'edit'   => Pages\EditAsset::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return static::getModel()::count() > 10 ? 'warning' : 'primary';
    }
}
