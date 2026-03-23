<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Rewards\Models\RewardQuest;
use App\Filament\Admin\Resources\RewardQuestResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RewardQuestResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = RewardQuest::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'Banking';

    protected static ?string $navigationLabel = 'Reward Quests';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Quest Details')
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
                                            'onboarding'  => 'Onboarding',
                                            'daily'       => 'Daily',
                                            'achievement' => 'Achievement',
                                            'special'     => 'Special',
                                        ]
                                    )
                                    ->required(),
                                Forms\Components\TextInput::make('icon')
                                    ->maxLength(255),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Rewards')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('xp_reward')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0),
                                Forms\Components\TextInput::make('points_reward')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Settings')
                        ->schema(
                            [
                                Forms\Components\Toggle::make('is_repeatable')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                                Forms\Components\TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                                Forms\Components\KeyValue::make('criteria')
                                    ->columnSpanFull(),
                            ]
                        )->columns(3),
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
                    Tables\Columns\TextColumn::make('xp_reward')
                        ->label('XP')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('points_reward')
                        ->label('Points')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\IconColumn::make('is_repeatable')
                        ->boolean(),
                    Tables\Columns\IconColumn::make('is_active')
                        ->boolean(),
                    Tables\Columns\TextColumn::make('sort_order')
                        ->numeric()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('completions_count')
                        ->counts('completions')
                        ->label('Completions')
                        ->sortable(),
                ]
            )
            ->defaultSort('sort_order')
            ->filters(
                [
                    SelectFilter::make('category')
                        ->options(
                            [
                                'onboarding'  => 'Onboarding',
                                'daily'       => 'Daily',
                                'achievement' => 'Achievement',
                                'special'     => 'Special',
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
            'index'  => Pages\ListRewardQuests::route('/'),
            'create' => Pages\CreateRewardQuest::route('/create'),
            'edit'   => Pages\EditRewardQuest::route('/{record}/edit'),
        ];
    }
}
