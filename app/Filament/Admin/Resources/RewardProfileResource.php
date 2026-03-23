<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Rewards\Models\RewardProfile;
use App\Filament\Admin\Resources\RewardProfileResource\Pages;
use App\Filament\Admin\Resources\RewardProfileResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RewardProfileResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = RewardProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Banking';

    protected static ?string $navigationLabel = 'Reward Profiles';

    protected static ?int $navigationSort = 22;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('User')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('user.name')
                                    ->label('Name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('user.email')
                                    ->label('Email')
                                    ->disabled(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Progress')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('xp')
                                    ->label('XP')
                                    ->disabled(),
                                Forms\Components\TextInput::make('level')
                                    ->disabled(),
                                Forms\Components\TextInput::make('points_balance')
                                    ->label('Points')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Streaks')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('current_streak')
                                    ->disabled(),
                                Forms\Components\TextInput::make('longest_streak')
                                    ->disabled(),
                                Forms\Components\DatePicker::make('last_activity_date')
                                    ->disabled(),
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
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('User')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('user.email')
                        ->label('Email')
                        ->searchable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('xp')
                        ->label('XP')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('level')
                        ->numeric()
                        ->sortable()
                        ->badge(),
                    Tables\Columns\TextColumn::make('current_streak')
                        ->label('Streak')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('longest_streak')
                        ->label('Best Streak')
                        ->numeric()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('points_balance')
                        ->label('Points')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('last_activity_date')
                        ->label('Last Active')
                        ->date()
                        ->sortable()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Joined')
                        ->dateTime('M j, Y')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]
            )
            ->defaultSort('level', 'desc')
            ->filters(
                [
                    Filter::make('level')
                        ->form(
                            [
                                Forms\Components\TextInput::make('min_level')
                                    ->label('Min Level')
                                    ->numeric()
                                    ->minValue(1),
                                Forms\Components\TextInput::make('max_level')
                                    ->label('Max Level')
                                    ->numeric()
                                    ->minValue(1),
                            ]
                        )
                        ->query(
                            function (Builder $query, array $data): Builder {
                                return $query
                                    ->when(
                                        $data['min_level'] ?? null,
                                        fn (Builder $query, $value): Builder => $query->where('level', '>=', $value), // @phpstan-ignore argument.type
                                    )
                                    ->when(
                                        $data['max_level'] ?? null,
                                        fn (Builder $query, $value): Builder => $query->where('level', '<=', $value), // @phpstan-ignore argument.type
                                    );
                            }
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
        return [
            RelationManagers\QuestCompletionsRelationManager::class,
            RelationManagers\RedemptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRewardProfiles::route('/'),
            'view'  => Pages\ViewRewardProfile::route('/{record}'),
        ];
    }
}
