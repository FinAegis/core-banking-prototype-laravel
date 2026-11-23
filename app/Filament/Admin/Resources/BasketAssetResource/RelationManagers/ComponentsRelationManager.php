<?php

namespace App\Filament\Admin\Resources\BasketAssetResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $recordTitleAttribute = 'asset_code';

    public function form(Form $form): Form
    {
        return $form
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
                        ->helperText('Choose an asset to include in the basket.'),

                    Forms\Components\TextInput::make('weight')
                        ->label('Weight (%)')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->step(0.01)
                        ->helperText('Enter the share of the basket that this asset makes up, as a percentage.'),

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
                                    ->visible(fn () => $this->getOwnerRecord()->type === 'dynamic'),

                                Forms\Components\TextInput::make('max_weight')
                                    ->label('Max Weight (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->step(0.01)
                                    ->visible(fn () => $this->getOwnerRecord()->type === 'dynamic'),
                            ]
                        ),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Turn this on to include this asset in the basket.'),
                ]
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('asset_code')
            ->columns(
                [
                    Tables\Columns\TextColumn::make('asset.name')
                        ->label('Asset')
                        ->searchable(),

                    Tables\Columns\TextColumn::make('asset_code')
                        ->label('Code')
                        ->badge()
                        ->color('primary'),

                    Tables\Columns\TextColumn::make('weight')
                        ->label('Weight')
                        ->suffix('%')
                        ->numeric(decimalPlaces: 2)
                        ->alignCenter()
                        ->color(fn ($state) => $state > 50 ? 'success' : ($state > 25 ? 'warning' : 'gray')),

                    Tables\Columns\TextColumn::make('min_weight')
                        ->label('Min')
                        ->suffix('%')
                        ->numeric(decimalPlaces: 2)
                        ->alignCenter()
                        ->placeholder('—')
                        ->visible(fn () => $this->getOwnerRecord()->type === 'dynamic'),

                    Tables\Columns\TextColumn::make('max_weight')
                        ->label('Max')
                        ->suffix('%')
                        ->numeric(decimalPlaces: 2)
                        ->alignCenter()
                        ->placeholder('—')
                        ->visible(fn () => $this->getOwnerRecord()->type === 'dynamic'),

                    Tables\Columns\IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean()
                        ->alignCenter(),

                    Tables\Columns\TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]
            )
            ->filters(
                [
                    Tables\Filters\TernaryFilter::make('is_active')
                        ->label('Active Status')
                        ->placeholder('All components')
                        ->trueLabel('Active only')
                        ->falseLabel('Inactive only'),
                ]
            )
            ->headerActions(
                [
                    Tables\Actions\CreateAction::make()
                        ->after(
                            function () {
                                // Recalculate value after adding component
                                app(\App\Domain\Basket\Services\BasketValueCalculationService::class)
                                    ->calculateValue($this->getOwnerRecord(), false);
                            }
                        ),
                ]
            )
            ->actions(
                [
                    Tables\Actions\EditAction::make()
                        ->after(
                            function () {
                                // Recalculate value after editing component
                                app(\App\Domain\Basket\Services\BasketValueCalculationService::class)
                                    ->calculateValue($this->getOwnerRecord(), false);
                            }
                        ),
                    Tables\Actions\DeleteAction::make()
                        ->after(
                            function () {
                                // Recalculate value after deleting component
                                app(\App\Domain\Basket\Services\BasketValueCalculationService::class)
                                    ->calculateValue($this->getOwnerRecord(), false);
                            }
                        ),
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
}
