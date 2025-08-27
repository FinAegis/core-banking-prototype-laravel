<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketValueCalculationService;
use App\Filament\Admin\Resources\BasketAssetResource\Pages;
use App\Filament\Admin\Resources\BasketAssetResource\RelationManagers;
use Exception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BasketAssetResource extends Resource
{
    protected static ?string $model = BasketAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Asset Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Basket Information')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('code')
                                    ->label('Basket Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('STABLE_BASKET, CRYPTO_INDEX, etc.')
                                    ->helperText('Unique identifier for the basket asset'),

                                Forms\Components\TextInput::make('name')
                                    ->label('Basket Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Stable Currency Basket, Crypto Index, etc.')
                                    ->helperText('Display name of the basket'),

                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->helperText('Brief description of the basket\'s purpose and composition'),

                                Forms\Components\Select::make('type')
                                    ->label('Basket Type')
                                    ->required()
                                    ->options(
                                        [
                                            'fixed'   => 'Fixed Weights',
                                            'dynamic' => 'Dynamic Weights',
                                        ]
                                    )
                                    ->reactive()
                                    ->helperText('Fixed baskets maintain constant weights, dynamic baskets can be rebalanced'),

                                Forms\Components\Select::make('rebalance_frequency')
                                    ->label('Rebalance Frequency')
                                    ->required()
                                    ->options(
                                        [
                                            'never'     => 'Never',
                                            'daily'     => 'Daily',
                                            'weekly'    => 'Weekly',
                                            'monthly'   => 'Monthly',
                                            'quarterly' => 'Quarterly',
                                        ]
                                    )
                                    ->default('never')
                                    ->disabled(fn (Forms\Get $get) => $get('type') !== 'dynamic')
                                    ->helperText('How often the basket should be rebalanced (dynamic baskets only)'),
                            ]
                        ),

                    Forms\Components\Section::make('Components')
                        ->schema(
                            [
                                Forms\Components\Repeater::make('components')
                                    ->label('Basket Components')
                                    ->relationship()
                                    ->schema(
                                        [
                                            Forms\Components\Select::make('asset_code')
                                                ->label('Asset')
                                                ->required()
                                                ->options(
                                                    fn () => \App\Domain\Asset\Models\Asset::where('is_active', true)
                                                        ->pluck('name', 'code')
                                                )
                                                ->searchable()
                                                ->helperText('Select the asset to include in the basket'),

                                            Forms\Components\TextInput::make('weight')
                                                ->label('Weight (%)')
                                                ->required()
                                                ->numeric()
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->suffix('%')
                                                ->step(0.01)
                                                ->helperText('Percentage weight in the basket'),

                                            Forms\Components\Grid::make(2)
                                                ->schema(
                                                    [
                                                        Forms\Components\TextInput::make('min_weight')
                                                            ->label('Min Weight (%)')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue(100)
                                                            ->suffix('%')
                                                            ->step(0.01)
                                                            ->visible(fn (Forms\Get $get) => $get('../../type') === 'dynamic'),

                                                        Forms\Components\TextInput::make('max_weight')
                                                            ->label('Max Weight (%)')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue(100)
                                                            ->suffix('%')
                                                            ->step(0.01)
                                                            ->visible(fn (Forms\Get $get) => $get('../../type') === 'dynamic'),
                                                    ]
                                                ),

                                            Forms\Components\Toggle::make('is_active')
                                                ->label('Active')
                                                ->default(true)
                                                ->helperText('Whether this component is active in the basket'),
                                        ]
                                    )
                                    ->columns(1)
                                    ->minItems(2)
                                    ->maxItems(20)
                                    ->addActionLabel('Add Component')
                                    ->collapsible()
                                    ->cloneable()
                                    ->reorderable()
                                    ->itemLabel(
                                        fn (array $state): ?string => isset($state['asset_code'])
                                            ? "{$state['asset_code']} - {$state['weight']}%"
                                        : null
                                    )
                                    ->afterStateUpdated(
                                        function (Forms\Get $get, Forms\Set $set) {
                                            // Validate total weight
                                            $components = $get('components') ?? [];
                                            $totalWeight = collect($components)->sum('weight');
                                            if (abs($totalWeight - 100) > 0.01 && count($components) > 0) {
                                                \Filament\Notifications\Notification::make()
                                                    ->warning()
                                                    ->title('Weight Warning')
                                                    ->body("Total weight is {$totalWeight}%. Components must sum to 100%.")
                                                    ->send();
                                            }
                                        }
                                    ),
                            ]
                        ),

                    Forms\Components\Section::make('Status')
                        ->schema(
                            [
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Whether this basket is available for trading'),

                                Forms\Components\DateTimePicker::make('last_rebalanced_at')
                                    ->label('Last Rebalanced')
                                    ->disabled()
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'dynamic'),
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
                        ->color('primary'),

                    Tables\Columns\TextColumn::make('name')
                        ->label('Name')
                        ->searchable()
                        ->sortable(),

                    Tables\Columns\TextColumn::make('type')
                        ->label('Type')
                        ->badge()
                        ->color(
                            fn (string $state): string => match ($state) {
                                'fixed'   => 'info',
                                'dynamic' => 'warning',
                                default   => 'gray',
                            }
                        ),

                    Tables\Columns\TextColumn::make('components_count')
                        ->label('Components')
                        ->counts('components')
                        ->suffix(' assets')
                        ->alignCenter(),

                    Tables\Columns\TextColumn::make('latestValue.value')
                        ->label('Current Value')
                        ->numeric(decimalPlaces: 4)
                        ->prefix('$')
                        ->placeholder('—')
                        ->tooltip(
                            fn ($record) => $record->latestValue
                            ? 'As of ' . $record->latestValue->calculated_at->diffForHumans()
                            : null
                        ),

                    Tables\Columns\TextColumn::make('rebalance_frequency')
                        ->label('Rebalance')
                        ->badge()
                        ->color(
                            fn (string $state): string => match ($state) {
                                'never'     => 'gray',
                                'daily'     => 'danger',
                                'weekly'    => 'warning',
                                'monthly'   => 'info',
                                'quarterly' => 'success',
                                default     => 'gray',
                            }
                        )
                        ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                    Tables\Columns\IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean()
                        ->alignCenter(),

                    Tables\Columns\IconColumn::make('needs_rebalancing')
                        ->label('Needs Rebalance')
                        ->icon(
                            fn ($record): string => $record->needsRebalancing()
                            ? 'heroicon-o-exclamation-circle'
                            : 'heroicon-o-check-circle'
                        )
                        ->color(
                            fn ($record): string => $record->needsRebalancing()
                            ? 'warning'
                            : 'success'
                        )
                        ->tooltip(
                            fn ($record): string => $record->needsRebalancing()
                            ? 'Rebalancing needed'
                            : 'Balanced'
                        )
                        ->visible(fn ($record) => $record->type === 'dynamic'),

                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Created')
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
                                'fixed'   => 'Fixed Weights',
                                'dynamic' => 'Dynamic Weights',
                            ]
                        ),

                    Tables\Filters\SelectFilter::make('rebalance_frequency')
                        ->options(
                            [
                                'never'     => 'Never',
                                'daily'     => 'Daily',
                                'weekly'    => 'Weekly',
                                'monthly'   => 'Monthly',
                                'quarterly' => 'Quarterly',
                            ]
                        )
                        ->visible(fn (): bool => BasketAsset::where('type', 'dynamic')->exists()),

                    Tables\Filters\TernaryFilter::make('is_active')
                        ->label('Active Status')
                        ->placeholder('All baskets')
                        ->trueLabel('Active only')
                        ->falseLabel('Inactive only'),

                    Tables\Filters\Filter::make('needs_rebalancing')
                        ->label('Needs Rebalancing')
                        ->query(
                            fn (Builder $query): Builder => $query->where('type', 'dynamic')
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
                        ),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('calculate_value')
                        ->label('Calculate Value')
                        ->icon('heroicon-m-calculator')
                        ->color('info')
                        ->action(
                            function (BasketAsset $record) {
                                try {
                                    $service = app(BasketValueCalculationService::class);
                                    $value = $service->calculateValue($record, false);

                                    \Filament\Notifications\Notification::make()
                                        ->success()
                                        ->title('Value Calculated')
                                        ->body("Current value: \${$value->value}")
                                        ->send();
                                } catch (Exception $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Calculation Failed')
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            }
                        ),

                    Tables\Actions\Action::make('rebalance')
                        ->label('Rebalance')
                        ->icon('heroicon-m-scale')
                        ->color('warning')
                        ->visible(fn (BasketAsset $record) => $record->type === 'dynamic')
                        ->requiresConfirmation()
                        ->modalHeading('Rebalance Basket')
                        ->modalDescription('This will adjust the component weights to their target values.')
                        ->modalSubmitActionLabel('Rebalance')
                        ->action(
                            function (BasketAsset $record) {
                                try {
                                    $service = app(\App\Domain\Basket\Services\BasketRebalancingService::class);
                                    $result = $service->rebalance($record);

                                    \Filament\Notifications\Notification::make()
                                        ->success()
                                        ->title('Basket Rebalanced')
                                        ->body("Adjusted {$result['adjustments_count']} components")
                                        ->send();
                                } catch (Exception $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Rebalancing Failed')
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            }
                        ),
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

                            Tables\Actions\BulkAction::make('calculate_values')
                                ->label('Calculate Values')
                                ->icon('heroicon-m-calculator')
                                ->color('info')
                                ->action(
                                    function ($records) {
                                        $service = app(BasketValueCalculationService::class);
                                        $success = 0;
                                        $failed = 0;

                                        foreach ($records as $basket) {
                                            try {
                                                $service->calculateValue($basket, false);
                                                $success++;
                                            } catch (Exception $e) {
                                                $failed++;
                                            }
                                        }

                                        \Filament\Notifications\Notification::make()
                                            ->success()
                                            ->title('Values Calculated')
                                            ->body("Success: {$success}, Failed: {$failed}")
                                            ->send();
                                    }
                                )
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
                    Infolists\Components\Section::make('Basket Information')
                        ->schema(
                            [
                                Infolists\Components\TextEntry::make('code')
                                    ->label('Basket Code')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('name')
                                    ->label('Basket Name'),

                                Infolists\Components\TextEntry::make('description')
                                    ->label('Description')
                                    ->placeholder('—'),

                                Infolists\Components\TextEntry::make('type')
                                    ->label('Type')
                                    ->badge()
                                    ->color(
                                        fn (string $state): string => match ($state) {
                                            'fixed'   => 'info',
                                            'dynamic' => 'warning',
                                            default   => 'gray',
                                        }
                                    )
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state) . ' Weights'),

                                Infolists\Components\TextEntry::make('rebalance_frequency')
                                    ->label('Rebalance Frequency')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Active Status')
                                    ->boolean(),
                            ]
                        )
                        ->columns(2),

                    Infolists\Components\Section::make('Current Value')
                        ->schema(
                            [
                                Infolists\Components\TextEntry::make('latestValue.value')
                                    ->label('Value (USD)')
                                    ->numeric(decimalPlaces: 4)
                                    ->prefix('$')
                                    ->placeholder('Not calculated')
                                    ->size('lg')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('latestValue.calculated_at')
                                    ->label('Last Calculated')
                                    ->dateTime()
                                    ->placeholder('Never'),

                                Infolists\Components\TextEntry::make('last_rebalanced_at')
                                    ->label('Last Rebalanced')
                                    ->dateTime()
                                    ->placeholder('Never')
                                    ->visible(fn ($record) => $record->type === 'dynamic'),
                            ]
                        )
                        ->columns(3),

                    Infolists\Components\Section::make('Components')
                        ->schema(
                            [
                                Infolists\Components\RepeatableEntry::make('components')
                                    ->label('Basket Components')
                                    ->schema(
                                        [
                                            Infolists\Components\Grid::make(5)
                                                ->schema(
                                                    [
                                                        Infolists\Components\TextEntry::make('asset.name')
                                                            ->label('Asset')
                                                            ->weight('bold'),

                                                        Infolists\Components\TextEntry::make('weight')
                                                            ->label('Weight')
                                                            ->suffix('%')
                                                            ->badge()
                                                            ->color('primary'),

                                                        Infolists\Components\TextEntry::make('min_weight')
                                                            ->label('Min Weight')
                                                            ->suffix('%')
                                                            ->placeholder('—')
                                                            ->visible(fn ($record) => $record->basketAsset->type === 'dynamic'),

                                                        Infolists\Components\TextEntry::make('max_weight')
                                                            ->label('Max Weight')
                                                            ->suffix('%')
                                                            ->placeholder('—')
                                                            ->visible(fn ($record) => $record->basketAsset->type === 'dynamic'),

                                                        Infolists\Components\IconEntry::make('is_active')
                                                            ->label('Active')
                                                            ->boolean(),
                                                    ]
                                                ),
                                        ]
                                    )
                                    ->columns(1),
                            ]
                        ),

                    Infolists\Components\Section::make('Value History')
                        ->schema(
                            [
                                Infolists\Components\ViewEntry::make('value_chart')
                                    ->label('30-Day Value Chart')
                                    ->view('filament.resources.basket-asset.value-chart'),
                            ]
                        )
                        ->collapsible(),

                    Infolists\Components\Section::make('System Information')
                        ->schema(
                            [
                                Infolists\Components\TextEntry::make('created_by')
                                    ->label('Created By')
                                    ->placeholder('System'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Updated At')
                                    ->dateTime(),
                            ]
                        )
                        ->columns(3)
                        ->collapsible(),
                ]
            );
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ComponentsRelationManager::class,
            RelationManagers\ValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBasketAssets::route('/'),
            'create' => Pages\CreateBasketAsset::route('/create'),
            'view'   => Pages\ViewBasketAsset::route('/{record}'),
            'edit'   => Pages\EditBasketAsset::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return static::getModel()::where('is_active', true)->count() > 5 ? 'success' : 'primary';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('components')
            ->with(['latestValue', 'components.asset']);
    }
}
