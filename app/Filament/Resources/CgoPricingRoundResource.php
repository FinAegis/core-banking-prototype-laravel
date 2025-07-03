<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CgoPricingRoundResource\Pages;
use App\Models\CgoPricingRound;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CgoPricingRoundResource extends Resource
{
    protected static ?string $model = CgoPricingRound::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'CGO Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Pricing Round';

    protected static ?string $pluralModelLabel = 'Pricing Rounds';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Round Information')
                    ->schema([
                        Forms\Components\TextInput::make('round_number')
                            ->label('Round Number')
                            ->numeric()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->minValue(1),
                        Forms\Components\TextInput::make('share_price')
                            ->label('Share Price')
                            ->prefix('$')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.0001),
                        Forms\Components\TextInput::make('max_shares_available')
                            ->label('Maximum Shares Available')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->step(0.0001),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Round')
                            ->helperText('Only one round can be active at a time')
                            ->reactive()
                            ->afterStateUpdated(function ($state, $record) {
                                if ($state && $record) {
                                    // Deactivate other rounds
                                    CgoPricingRound::where('id', '!=', $record->id)
                                        ->where('is_active', true)
                                        ->update(['is_active' => false]);
                                }
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Progress')
                    ->schema([
                        Forms\Components\TextInput::make('shares_sold')
                            ->label('Shares Sold')
                            ->numeric()
                            ->disabled()
                            ->step(0.0001),
                        Forms\Components\TextInput::make('total_raised')
                            ->label('Total Raised')
                            ->prefix('$')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\Placeholder::make('remaining_shares')
                            ->label('Remaining Shares')
                            ->content(fn ($record) => $record ? number_format($record->remaining_shares, 4) : 'N/A'),
                        Forms\Components\Placeholder::make('progress_percentage')
                            ->label('Progress')
                            ->content(fn ($record) => $record ? number_format($record->progress_percentage, 2) . '%' : 'N/A'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Timeline')
                    ->schema([
                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('Start Date')
                            ->required(),
                        Forms\Components\DateTimePicker::make('ended_at')
                            ->label('End Date')
                            ->after('started_at'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('round_number')
                    ->label('Round')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('share_price')
                    ->label('Share Price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shares_sold')
                    ->label('Sold')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_shares_available')
                    ->label('Total Available')
                    ->numeric(decimalPlaces: 4),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_raised')
                    ->label('Total Raised')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ended_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('investments_count')
                    ->label('Investments')
                    ->counts('investments')
                    ->sortable(),
            ])
            ->defaultSort('round_number', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('started_from'),
                        Forms\Components\DatePicker::make('started_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['started_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('started_at', '>=', $date),
                            )
                            ->when(
                                $data['started_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('started_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (CgoPricingRound $record) {
                        // Deactivate all other rounds
                        CgoPricingRound::where('id', '!=', $record->id)
                            ->update(['is_active' => false]);
                        
                        // Activate this round
                        $record->update(['is_active' => true]);
                    })
                    ->visible(fn (CgoPricingRound $record) => !$record->is_active),
                Tables\Actions\Action::make('close')
                    ->label('Close Round')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (CgoPricingRound $record) {
                        $record->update([
                            'is_active' => false,
                            'ended_at' => now(),
                        ]);
                    })
                    ->visible(fn (CgoPricingRound $record) => $record->is_active),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCgoPricingRounds::route('/'),
            'create' => Pages\CreateCgoPricingRound::route('/create'),
            'view' => Pages\ViewCgoPricingRound::route('/{record}'),
            'edit' => Pages\EditCgoPricingRound::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('investments');
    }
}