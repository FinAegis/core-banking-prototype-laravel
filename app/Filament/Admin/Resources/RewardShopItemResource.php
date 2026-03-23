<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Rewards\Models\RewardShopItem;
use App\Filament\Admin\Resources\RewardShopItemResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RewardShopItemResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = RewardShopItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Banking';

    protected static ?string $navigationLabel = 'Reward Shop Items';

    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Item Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->required()
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('category')
                                    ->options(
                                        [
                                            'perks'    => 'Perks',
                                            'badges'   => 'Badges',
                                            'upgrades' => 'Upgrades',
                                        ]
                                    )
                                    ->required(),
                                Forms\Components\TextInput::make('icon')
                                    ->maxLength(255),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Pricing & Stock')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('points_cost')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1),
                                Forms\Components\TextInput::make('stock')
                                    ->numeric()
                                    ->nullable()
                                    ->minValue(0)
                                    ->helperText('Leave empty for unlimited stock'),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Settings')
                        ->schema(
                            [
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                                Forms\Components\KeyValue::make('metadata')
                                    ->columnSpanFull(),
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
                    Tables\Columns\TextColumn::make('slug')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('title')
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('category')
                        ->badge()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('points_cost')
                        ->label('Cost')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('stock')
                        ->formatStateUsing(fn ($state) => $state === null ? 'Unlimited' : $state)
                        ->sortable(),
                    Tables\Columns\IconColumn::make('is_active')
                        ->boolean(),
                    Tables\Columns\TextColumn::make('sort_order')
                        ->numeric()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('redemptions_count')
                        ->counts('redemptions')
                        ->label('Redemptions')
                        ->sortable(),
                ]
            )
            ->defaultSort('sort_order')
            ->filters(
                [
                    SelectFilter::make('category')
                        ->options(
                            [
                                'perks'    => 'Perks',
                                'badges'   => 'Badges',
                                'upgrades' => 'Upgrades',
                            ]
                        ),
                    TernaryFilter::make('is_active'),
                ]
            )
            ->actions(
                [
                    Tables\Actions\EditAction::make(),
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkActionGroup::make(
                        [
                            Tables\Actions\DeleteBulkAction::make(),
                        ]
                    ),
                ]
            );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRewardShopItems::route('/'),
            'create' => Pages\CreateRewardShopItem::route('/create'),
            'edit'   => Pages\EditRewardShopItem::route('/{record}/edit'),
        ];
    }
}
