<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\RegTech\Models\FilingSchedule;
use App\Filament\Admin\Resources\FilingScheduleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FilingScheduleResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = FilingSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'RegTech';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Filing Schedules';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Schedule Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('report_type')
                                    ->label('Report Type')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('jurisdiction')
                                    ->label('Jurisdiction')
                                    ->formatStateUsing(fn ($state) => $state->value)
                                    ->disabled(),
                                Forms\Components\TextInput::make('regulator')
                                    ->maxLength(255),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Scheduling')
                        ->schema(
                            [
                                Forms\Components\Select::make('frequency')
                                    ->options(
                                        [
                                            'daily'       => 'Daily',
                                            'weekly'      => 'Weekly',
                                            'monthly'     => 'Monthly',
                                            'quarterly'   => 'Quarterly',
                                            'annually'    => 'Annually',
                                            'transaction' => 'Per Transaction',
                                            'event'       => 'Event-Driven',
                                        ]
                                    )
                                    ->required(),
                                Forms\Components\TextInput::make('deadline_days')
                                    ->label('Deadline (days)')
                                    ->numeric()
                                    ->default(30),
                                Forms\Components\DateTimePicker::make('next_due_date')
                                    ->label('Next Due Date'),
                                Forms\Components\DateTimePicker::make('last_filed_at')
                                    ->label('Last Filed')
                                    ->disabled(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Settings')
                        ->schema(
                            [
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                Forms\Components\Toggle::make('auto_generate')
                                    ->label('Auto-Generate Reports')
                                    ->default(false),
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
                    Tables\Columns\TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('report_type')
                        ->label('Type')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('jurisdiction')
                        ->formatStateUsing(fn ($state) => strtoupper($state->value))
                        ->sortable(),
                    Tables\Columns\TextColumn::make('frequency')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ucfirst($state))
                        ->color(
                            fn (string $state): string => match ($state) {
                                'daily'       => 'danger',
                                'weekly'      => 'warning',
                                'monthly'     => 'info',
                                'quarterly'   => 'primary',
                                'annually'    => 'success',
                                'transaction' => 'warning',
                                'event'       => 'gray',
                                default       => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('next_due_date')
                        ->label('Next Due')
                        ->dateTime()
                        ->sortable()
                        ->color(
                            fn ($state): string => $state && $state->isPast() ? 'danger' : 'success'
                        ),
                    Tables\Columns\TextColumn::make('last_filed_at')
                        ->label('Last Filed')
                        ->dateTime()
                        ->sortable()
                        ->placeholder('Never'),
                    Tables\Columns\IconColumn::make('is_active')
                        ->label('Active')
                        ->boolean()
                        ->sortable(),
                    Tables\Columns\IconColumn::make('auto_generate')
                        ->label('Auto')
                        ->boolean()
                        ->toggleable(),
                ]
            )
            ->defaultSort('next_due_date', 'asc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('frequency')
                        ->options(
                            [
                                'daily'       => 'Daily',
                                'weekly'      => 'Weekly',
                                'monthly'     => 'Monthly',
                                'quarterly'   => 'Quarterly',
                                'annually'    => 'Annually',
                                'transaction' => 'Per Transaction',
                                'event'       => 'Event-Driven',
                            ]
                        ),
                    Tables\Filters\TernaryFilter::make('is_active')
                        ->label('Active'),
                    Tables\Filters\TernaryFilter::make('auto_generate')
                        ->label('Auto-Generate'),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
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
            'index'  => Pages\ListFilingSchedules::route('/'),
            'create' => Pages\CreateFilingSchedule::route('/create'),
            'view'   => Pages\ViewFilingSchedule::route('/{record}'),
            'edit'   => Pages\EditFilingSchedule::route('/{record}/edit'),
        ];
    }
}
