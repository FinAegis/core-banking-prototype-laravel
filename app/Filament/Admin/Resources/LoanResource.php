<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Lending\Models\Loan;
use App\Filament\Admin\Resources\LoanResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoanResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = Loan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Lending';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Loan Overview')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('id')
                                    ->label('Loan ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('application_id')
                                    ->label('Application ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Financial Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('principal')
                                    ->prefix('$')
                                    ->disabled(),
                                Forms\Components\TextInput::make('interest_rate')
                                    ->suffix('%')
                                    ->disabled(),
                                Forms\Components\TextInput::make('term_months')
                                    ->label('Term (months)')
                                    ->disabled(),
                                Forms\Components\TextInput::make('funded_amount')
                                    ->label('Funded')
                                    ->prefix('$')
                                    ->disabled(),
                                Forms\Components\TextInput::make('disbursed_amount')
                                    ->label('Disbursed')
                                    ->prefix('$')
                                    ->disabled(),
                                Forms\Components\TextInput::make('missed_payments')
                                    ->label('Missed Payments')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Repayment Progress')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('total_principal_paid')
                                    ->label('Principal Paid')
                                    ->prefix('$')
                                    ->disabled(),
                                Forms\Components\TextInput::make('total_interest_paid')
                                    ->label('Interest Paid')
                                    ->prefix('$')
                                    ->disabled(),
                                Forms\Components\TextInput::make('last_payment_date')
                                    ->label('Last Payment')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Key Dates')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('funded_at')
                                    ->label('Funded At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('disbursed_at')
                                    ->label('Disbursed At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('completed_at')
                                    ->label('Completed At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('defaulted_at')
                                    ->label('Defaulted At')
                                    ->disabled(),
                            ]
                        )->columns(4),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Date')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('principal')
                        ->money('USD')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('interest_rate')
                        ->label('Rate')
                        ->suffix('%')
                        ->numeric(2)
                        ->sortable(),
                    Tables\Columns\TextColumn::make('term_months')
                        ->label('Term')
                        ->suffix(' mo')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('funded_amount')
                        ->label('Funded')
                        ->money('USD')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('missed_payments')
                        ->label('Missed')
                        ->numeric()
                        ->color(
                            fn ($state): string => match (true) {
                                $state >= 3 => 'danger',
                                $state >= 1 => 'warning',
                                default     => 'success',
                            }
                        )
                        ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ucfirst($state))
                        ->color(
                            fn (string $state): string => match ($state) {
                                'active'     => 'success',
                                'funded'     => 'info',
                                'delinquent' => 'warning',
                                'defaulted'  => 'danger',
                                default      => 'gray',
                            }
                        )
                        ->sortable(),
                    Tables\Columns\TextColumn::make('borrower.name')
                        ->label('Borrower')
                        ->searchable()
                        ->toggleable(),
                ]
            )
            ->defaultSort('created_at', 'desc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            [
                                'active'     => 'Active',
                                'funded'     => 'Funded',
                                'delinquent' => 'Delinquent',
                                'defaulted'  => 'Defaulted',
                            ]
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'view'  => Pages\ViewLoan::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
