<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\AssetResource\RelationManagers;

use App\Domain\Asset\Models\ExchangeRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExchangeRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'exchangeRatesFrom';

    protected static ?string $title = 'Exchange Rates (From This Asset)';

    protected static ?string $icon = 'heroicon-m-arrow-path';

    public function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Select::make('to_asset_code')
                        ->label('To Asset')
                        ->relationship('toAsset', 'code')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('rate')
                        ->label('Exchange Rate')
                        ->numeric()
                        ->step(0.0000000001)
                        ->required()
                        ->helperText('Enter the rate that converts one unit of the base asset into the target asset.'),

                    Forms\Components\Select::make('source')
                        ->label('Source')
                        ->options(
                            [
                                ExchangeRate::SOURCE_MANUAL => 'Manual',
                                ExchangeRate::SOURCE_API    => 'API',
                                ExchangeRate::SOURCE_ORACLE => 'Oracle',
                                ExchangeRate::SOURCE_MARKET => 'Market',
                            ]
                        )
                        ->default(ExchangeRate::SOURCE_MANUAL)
                        ->required(),

                    Forms\Components\DateTimePicker::make('valid_at')
                        ->label('Valid From')
                        ->default(now())
                        ->required(),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->after('valid_at')
                        ->helperText('Leave this empty if the rate does not expire.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\KeyValue::make('metadata')
                        ->label('Metadata')
                        ->keyLabel('Property')
                        ->valueLabel('Value')
                        ->columnSpanFull(),
                ]
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('to_asset_code')
            ->columns(
                [
                    Tables\Columns\TextColumn::make('to_asset_code')
                        ->label('To Asset')
                        ->badge()
                        ->color('primary')
                        ->searchable()
                        ->sortable(),

                    Tables\Columns\TextColumn::make('rate')
                        ->label('Rate')
                        ->numeric(decimalPlaces: 10)
                        ->sortable(),

                    Tables\Columns\TextColumn::make('source')
                        ->label('Source')
                        ->badge()
                        ->color(
                            fn (string $state): string => match ($state) {
                                ExchangeRate::SOURCE_MANUAL => 'gray',
                                ExchangeRate::SOURCE_API    => 'success',
                                ExchangeRate::SOURCE_ORACLE => 'warning',
                                ExchangeRate::SOURCE_MARKET => 'info',
                                default                     => 'gray',
                            }
                        ),

                    Tables\Columns\TextColumn::make('valid_at')
                        ->label('Valid From')
                        ->dateTime()
                        ->sortable(),

                    Tables\Columns\TextColumn::make('expires_at')
                        ->label('Expires')
                        ->dateTime()
                        ->placeholder('Never')
                        ->sortable(),

                    Tables\Columns\IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean()
                        ->alignCenter(),

                    Tables\Columns\TextColumn::make('age')
                        ->label('Age')
                        ->state(fn ($record) => $record->getAgeInMinutes() . ' min')
                        ->color(
                            fn ($record) => match (true) {
                                $record->getAgeInMinutes() < 60   => 'success',
                                $record->getAgeInMinutes() < 1440 => 'warning',
                                default                           => 'danger',
                            }
                        )
                        ->badge(),
                ]
            )
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('source')
                        ->options(
                            [
                                ExchangeRate::SOURCE_MANUAL => 'Manual',
                                ExchangeRate::SOURCE_API    => 'API',
                                ExchangeRate::SOURCE_ORACLE => 'Oracle',
                                ExchangeRate::SOURCE_MARKET => 'Market',
                            ]
                        ),

                    Tables\Filters\TernaryFilter::make('is_active')
                        ->label('Active Status'),

                    Tables\Filters\Filter::make('valid_now')
                        ->label('Valid Now')
                        ->query(fn (Builder $query): Builder => $query->valid()),

                    Tables\Filters\Filter::make('expired')
                        ->label('Expired')
                        ->query(function (Builder $query): Builder {
                            /** @var Builder<ExchangeRate> $query */
                            return $query->where('expires_at', '<=', now());
                        }),
                ]
            )
            ->headerActions(
                [
                    Tables\Actions\CreateAction::make()
                        ->label('Add Rate'),
                ]
            )
            ->actions(
                [
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->requiresConfirmation(),

                    Tables\Actions\Action::make('refresh')
                        ->label('Refresh Rate')
                        ->icon('heroicon-m-arrow-path')
                        ->color('warning')
                        ->action(
                            function ($record) {
                                // Here you would implement rate refreshing logic
                                // For now, just update the valid_at timestamp
                                $record->update(['valid_at' => now()]);
                            }
                        )
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->source !== ExchangeRate::SOURCE_MANUAL),
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
            ->defaultSort('valid_at', 'desc');
    }
}
