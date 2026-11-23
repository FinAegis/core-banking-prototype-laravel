<?php

namespace App\Filament\Admin\Resources\BasketAssetResource\RelationManagers;

use App\Domain\Basket\Models\BasketValue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $recordTitleAttribute = 'value';

    protected static ?string $title = 'Value History';

    public function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\TextInput::make('value')
                        ->required()
                        ->numeric()
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('calculated_at')
                        ->required()
                        ->disabled(),
                ]
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->columns(
                [
                    Tables\Columns\TextColumn::make('value')
                        ->label('Value (USD)')
                        ->numeric(decimalPlaces: 4)
                        ->prefix('$')
                        ->weight('bold')
                        ->color('primary'),

                    Tables\Columns\TextColumn::make('calculated_at')
                        ->label('Calculated At')
                        ->dateTime()
                        ->sortable(),

                    Tables\Columns\TextColumn::make('calculated_at')
                        ->label('Age')
                        ->since()
                        ->color(
                            fn ($state) => match (true) {
                                $state->diffInMinutes() < 5 => 'success',
                                $state->diffInHours() < 1   => 'warning',
                                default                     => 'gray',
                            }
                        ),

                    Tables\Columns\ViewColumn::make('component_values')
                        ->label('Components')
                        ->view('filament.tables.columns.basket-component-values'),
                ]
            )
            ->filters(
                [
                    Tables\Filters\Filter::make('last_24_hours')
                        ->query(function (Builder $query): Builder {
                            /** @var Builder<BasketValue> $query */
                            return $query->where('calculated_at', '>=', now()->subDay());
                        }),

                    Tables\Filters\Filter::make('last_7_days')
                        ->query(function (Builder $query): Builder {
                            /** @var Builder<BasketValue> $query */
                            return $query->where('calculated_at', '>=', now()->subWeek());
                        }),

                    Tables\Filters\Filter::make('last_30_days')
                        ->query(function (Builder $query): Builder {
                            /** @var Builder<BasketValue> $query */
                            return $query->where('calculated_at', '>=', now()->subMonth());
                        }),
                ]
            )
            ->defaultSort('calculated_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->actions(
                [
                    // Values are read-only
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkActionGroup::make(
                        [
                            Tables\Actions\DeleteBulkAction::make()
                                ->label('Delete Old Values')
                                ->requiresConfirmation()
                                ->modalDescription('This deletes the selected past values. Keep the recent values, because the analytics use them.'),
                        ]
                    ),
                ]
            );
    }
}
