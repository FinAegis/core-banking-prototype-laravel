<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FinancialInstitutionApplicationResource\Pages;
use App\Filament\Admin\Resources\FinancialInstitutionApplicationResource\RelationManagers;
use App\Models\FinancialInstitutionApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;

class FinancialInstitutionApplicationResource extends Resource
{
    protected static ?string $model = FinancialInstitutionApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $navigationGroup = 'Partnerships';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $modelLabel = 'Financial Institution Application';
    
    protected static ?string $pluralModelLabel = 'Financial Institution Applications';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Institution Details')
                    ->schema([
                        Forms\Components\TextInput::make('institution_name')
                            ->label('Institution Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('registration_number')
                            ->label('Registration Number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->required()
                            ->searchable()
                            ->options([
                                'US' => 'United States',
                                'GB' => 'United Kingdom',
                                'DE' => 'Germany',
                                'FR' => 'France',
                                'CH' => 'Switzerland',
                                'NL' => 'Netherlands',
                                'ES' => 'Spain',
                                'IT' => 'Italy',
                                'SE' => 'Sweden',
                                'NO' => 'Norway',
                                'DK' => 'Denmark',
                                'FI' => 'Finland',
                                'LT' => 'Lithuania',
                                'LU' => 'Luxembourg',
                                'IE' => 'Ireland',
                                'PT' => 'Portugal',
                                'AT' => 'Austria',
                                'BE' => 'Belgium',
                                'CZ' => 'Czech Republic',
                                'PL' => 'Poland',
                            ]),
                        Forms\Components\TextInput::make('assets_under_management')
                            ->label('Assets Under Management (USD)')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('contact_name')
                            ->label('Contact Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->required()
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Contact Phone')
                            ->required()
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_position')
                            ->label('Contact Position')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Integration Details')
                    ->schema([
                        Forms\Components\Select::make('integration_type')
                            ->label('Integration Type')
                            ->required()
                            ->options([
                                'gcu_partner' => 'GCU Banking Partner',
                                'exchange_liquidity' => 'Exchange Liquidity Provider',
                                'lending_partner' => 'Lending Partner',
                                'treasury_services' => 'Treasury Services',
                                'correspondent_banking' => 'Correspondent Banking',
                                'custodian_services' => 'Custodian Services',
                                'payment_processor' => 'Payment Processing',
                                'regulatory_reporting' => 'Regulatory Reporting',
                            ])
                            ->multiple(),
                        Forms\Components\TagsInput::make('supported_currencies')
                            ->label('Supported Currencies')
                            ->placeholder('USD, EUR, GBP...')
                            ->required(),
                        Forms\Components\Textarea::make('integration_experience')
                            ->label('API Integration Experience')
                            ->required()
                            ->maxLength(5000)
                            ->rows(4),
                        Forms\Components\Textarea::make('compliance_certifications')
                            ->label('Compliance Certifications')
                            ->required()
                            ->maxLength(5000)
                            ->rows(4),
                    ]),
                    
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('partnership_goals')
                            ->label('Partnership Goals')
                            ->required()
                            ->maxLength(5000)
                            ->rows(4),
                        Forms\Components\Toggle::make('terms_accepted')
                            ->label('Terms & Conditions Accepted')
                            ->required()
                            ->disabled(fn (?FinancialInstitutionApplication $record) => $record !== null),
                    ]),
                    
                Forms\Components\Section::make('Application Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->required()
                            ->default('pending')
                            ->options([
                                'pending' => 'Pending Review',
                                'under_review' => 'Under Review',
                                'compliance_check' => 'Compliance Check',
                                'technical_review' => 'Technical Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'on_hold' => 'On Hold',
                            ])
                            ->live(),
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Internal Notes')
                            ->maxLength(5000)
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('reviewed_at')
                            ->label('Reviewed At')
                            ->visible(fn (Forms\Get $get) => in_array($get('status'), ['approved', 'rejected'])),
                        Forms\Components\TextInput::make('reviewed_by')
                            ->label('Reviewed By')
                            ->visible(fn (Forms\Get $get) => in_array($get('status'), ['approved', 'rejected'])),
                    ])
                    ->visible(fn (?FinancialInstitutionApplication $record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('institution_name')
                    ->label('Institution')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('integration_type')
                    ->label('Type')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->limit(30),
                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Contact')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'rejected',
                        'warning' => fn ($state) => in_array($state, ['pending', 'on_hold']),
                        'info' => fn ($state) => in_array($state, ['under_review', 'compliance_check', 'technical_review']),
                        'success' => 'approved',
                    ])
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime('M j, Y')
                    ->placeholder('Not reviewed'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Review',
                        'under_review' => 'Under Review',
                        'compliance_check' => 'Compliance Check',
                        'technical_review' => 'Technical Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'on_hold' => 'On Hold',
                    ]),
                Tables\Filters\SelectFilter::make('country'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FinancialInstitutionApplication $record) => !in_array($record->status, ['approved', 'rejected']))
                    ->action(fn (FinancialInstitutionApplication $record) => $record->update([
                        'status' => 'approved',
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->user()->name,
                    ])),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (FinancialInstitutionApplication $record) => !in_array($record->status, ['approved', 'rejected']))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(fn (FinancialInstitutionApplication $record, array $data) => $record->update([
                        'status' => 'rejected',
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->user()->name,
                        'internal_notes' => $record->internal_notes . "\n\n[Rejection Reason]: " . $data['rejection_reason'],
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListFinancialInstitutionApplications::route('/'),
            'create' => Pages\CreateFinancialInstitutionApplication::route('/create'),
            'edit' => Pages\EditFinancialInstitutionApplication::route('/{record}/edit'),
        ];
    }
}
