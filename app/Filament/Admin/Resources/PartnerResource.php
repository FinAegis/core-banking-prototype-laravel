<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Filament\Admin\Resources\PartnerResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = FinancialInstitutionPartner::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'BaaS Partners';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Partners';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Partner Info')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('partner_code')
                                    ->label('Partner Code')
                                    ->disabled(),
                                Forms\Components\TextInput::make('institution_name')
                                    ->label('Institution Name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('institution_type')
                                    ->label('Institution Type')
                                    ->disabled(),
                                Forms\Components\TextInput::make('country')
                                    ->disabled(),
                                Forms\Components\TextInput::make('tier')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Access')
                        ->schema(
                            [
                                Forms\Components\Toggle::make('sandbox_enabled')
                                    ->label('Sandbox Enabled')
                                    ->disabled(),
                                Forms\Components\Toggle::make('production_enabled')
                                    ->label('Production Enabled')
                                    ->disabled(),
                                Forms\Components\TextInput::make('rate_limit_per_minute')
                                    ->label('Rate Limit / Minute')
                                    ->disabled(),
                                Forms\Components\TextInput::make('rate_limit_per_day')
                                    ->label('Rate Limit / Day')
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Risk')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('risk_rating')
                                    ->label('Risk Rating')
                                    ->disabled(),
                                Forms\Components\TextInput::make('risk_score')
                                    ->label('Risk Score')
                                    ->disabled(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Usage')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('total_transactions')
                                    ->label('Total Transactions')
                                    ->disabled(),
                                Forms\Components\TextInput::make('total_volume')
                                    ->label('Total Volume')
                                    ->prefix('$')
                                    ->disabled(),
                                Forms\Components\TextInput::make('last_activity_at')
                                    ->label('Last Activity At')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Key Dates')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('activated_at')
                                    ->label('Activated At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('suspended_at')
                                    ->label('Suspended At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('suspension_reason')
                                    ->label('Suspension Reason')
                                    ->disabled(),
                                Forms\Components\TextInput::make('terminated_at')
                                    ->label('Terminated At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('termination_reason')
                                    ->label('Termination Reason')
                                    ->disabled(),
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
                    Tables\Columns\TextColumn::make('institution_name')
                        ->label('Institution')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('partner_code')
                        ->label('Code')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('country'),
                    Tables\Columns\TextColumn::make('tier')
                        ->badge(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->color(
                            fn (string $state): string => match ($state) {
                                'active'     => 'success',
                                'suspended'  => 'warning',
                                'terminated' => 'danger',
                                default      => 'gray',
                            }
                        ),
                    Tables\Columns\IconColumn::make('sandbox_enabled')
                        ->label('Sandbox')
                        ->boolean(),
                    Tables\Columns\IconColumn::make('production_enabled')
                        ->label('Prod')
                        ->boolean(),
                    Tables\Columns\TextColumn::make('total_transactions')
                        ->label('Transactions')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('total_volume')
                        ->label('Volume')
                        ->money('USD')
                        ->sortable(),
                    Tables\Columns\TextColumn::make('risk_rating')
                        ->label('Risk')
                        ->badge()
                        ->color(
                            fn (string $state): string => match ($state) {
                                'low'      => 'success',
                                'medium'   => 'warning',
                                'high'     => 'danger',
                                'critical' => 'danger',
                                default    => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('last_activity_at')
                        ->label('Last Activity')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(),
                ]
            )
            ->defaultSort('institution_name', 'asc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            [
                                'active'     => 'Active',
                                'suspended'  => 'Suspended',
                                'terminated' => 'Terminated',
                            ]
                        ),
                    Tables\Filters\SelectFilter::make('tier')
                        ->options(
                            fn (): array => FinancialInstitutionPartner::query()
                                ->distinct()
                                ->whereNotNull('tier')
                                ->pluck('tier', 'tier')
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('risk_rating')
                        ->label('Risk Rating')
                        ->options(
                            [
                                'low'      => 'Low',
                                'medium'   => 'Medium',
                                'high'     => 'High',
                                'critical' => 'Critical',
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
            'index' => Pages\ListPartners::route('/'),
            'view'  => Pages\ViewPartner::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
