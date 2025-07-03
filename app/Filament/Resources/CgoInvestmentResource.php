<?php

namespace App\Filament\Resources;

use App\Domain\Cgo\Actions\RequestRefundAction;
use App\Filament\Resources\CgoInvestmentResource\Pages;
use App\Models\CgoInvestment;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CgoInvestmentResource extends Resource
{
    protected static ?string $model = CgoInvestment::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'CGO Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'CGO Investment';

    protected static ?string $pluralModelLabel = 'CGO Investments';

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', 'pending')->count();
        return $pending > 0 ? $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() > 0 ? 'warning' : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Investment Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Investor')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\Select::make('round_id')
                            ->label('Pricing Round')
                            ->relationship('round', 'round_number')
                            ->required()
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\TextInput::make('amount')
                            ->label('Investment Amount')
                            ->prefix('$')
                            ->numeric()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\Select::make('tier')
                            ->options([
                                'bronze' => 'Bronze ($1,000 - $9,999)',
                                'silver' => 'Silver ($10,000 - $49,999)',
                                'gold' => 'Gold ($50,000+)',
                            ])
                            ->required()
                            ->disabled(fn ($record) => $record !== null),
                        Forms\Components\TextInput::make('shares_purchased')
                            ->label('Shares Purchased')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('ownership_percentage')
                            ->label('Ownership %')
                            ->suffix('%')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'stripe' => 'Credit/Debit Card',
                                'bank_transfer' => 'Bank Transfer',
                                'crypto' => 'Cryptocurrency',
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ]),
                        Forms\Components\DateTimePicker::make('payment_completed_at')
                            ->label('Payment Completed'),
                        Forms\Components\TextInput::make('stripe_session_id')
                            ->label('Stripe Session ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('coinbase_charge_id')
                            ->label('Coinbase Charge ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('bank_transfer_reference')
                            ->label('Bank Transfer Reference')
                            ->disabled(),
                        Forms\Components\TextInput::make('crypto_tx_hash')
                            ->label('Crypto Transaction Hash')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('KYC/AML Information')
                    ->schema([
                        Forms\Components\Select::make('kyc_level')
                            ->label('KYC Level')
                            ->options([
                                'basic' => 'Basic (Up to $1,000)',
                                'enhanced' => 'Enhanced (Up to $10,000)',
                                'full' => 'Full ($50,000+)',
                            ])
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('kyc_verified_at')
                            ->label('KYC Verified'),
                        Forms\Components\TextInput::make('risk_assessment')
                            ->label('Risk Score')
                            ->numeric()
                            ->suffix('/100')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('aml_checked_at')
                            ->label('AML Checked'),
                        Forms\Components\KeyValue::make('aml_flags')
                            ->label('AML Flags')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Certificate & Agreement')
                    ->schema([
                        Forms\Components\TextInput::make('certificate_number')
                            ->label('Certificate Number')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('certificate_issued_at')
                            ->label('Certificate Issued'),
                        Forms\Components\TextInput::make('agreement_path')
                            ->label('Agreement Document')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('agreement_generated_at')
                            ->label('Agreement Generated'),
                        Forms\Components\DateTimePicker::make('agreement_signed_at')
                            ->label('Agreement Signed'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Data')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Investor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('tier')
                    ->colors([
                        'warning' => 'bronze',
                        'gray' => 'silver',
                        'warning' => 'gold',
                    ])
                    ->formatStateUsing(fn ($state) => Str::title($state)),
                Tables\Columns\TextColumn::make('shares_purchased')
                    ->label('Shares')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                        'secondary' => 'refunded',
                    ]),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'secondary' => 'refunded',
                    ]),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'stripe' => 'Card',
                        'bank_transfer' => 'Bank',
                        'crypto' => 'Crypto',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('kyc_verified_at')
                    ->label('KYC')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\SelectFilter::make('tier')
                    ->options([
                        'bronze' => 'Bronze',
                        'silver' => 'Silver',
                        'gold' => 'Gold',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'stripe' => 'Card',
                        'bank_transfer' => 'Bank',
                        'crypto' => 'Crypto',
                    ]),
                Tables\Filters\TernaryFilter::make('kyc_verified_at')
                    ->label('KYC Verified')
                    ->nullable(),
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
                Tables\Actions\Action::make('verify_payment')
                    ->label('Verify Payment')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (CgoInvestment $record) {
                        // Dispatch verification job
                        \App\Jobs\VerifyCgoPayment::dispatch($record);
                    })
                    ->visible(fn (CgoInvestment $record) => 
                        $record->payment_status === 'pending' && 
                        $record->payment_method !== 'bank_transfer'
                    ),
                Tables\Actions\Action::make('download_agreement')
                    ->label('Download Agreement')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->action(function (CgoInvestment $record) {
                        if ($record->agreement_path) {
                            return response()->download(storage_path('app/' . $record->agreement_path));
                        }
                    })
                    ->visible(fn (CgoInvestment $record) => !empty($record->agreement_path)),
                Tables\Actions\Action::make('download_certificate')
                    ->label('Download Certificate')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->url(fn (CgoInvestment $record) => route('cgo.certificate', $record->uuid))
                    ->openUrlInNewTab()
                    ->visible(fn (CgoInvestment $record) => !empty($record->certificate_number)),
                Tables\Actions\Action::make('request_refund')
                    ->label('Request Refund')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('reason')
                            ->label('Refund Reason')
                            ->options([
                                'customer_request' => 'Customer Request',
                                'duplicate_payment' => 'Duplicate Payment',
                                'payment_error' => 'Payment Error',
                                'system_error' => 'System Error',
                                'regulatory_requirement' => 'Regulatory Requirement',
                                'other' => 'Other',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('reason_details')
                            ->label('Additional Details')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (CgoInvestment $record, array $data) {
                        try {
                            app(RequestRefundAction::class)->execute(
                                investment: $record,
                                initiator: auth()->user(),
                                reason: $data['reason'],
                                reasonDetails: $data['reason_details']
                            );
                            
                            Notification::make()
                                ->title('Refund request initiated')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Refund request failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (CgoInvestment $record) => $record->canBeRefunded()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('verify_payments')
                        ->label('Verify Payments')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->payment_status === 'pending' && $record->payment_method !== 'bank_transfer') {
                                    \App\Jobs\VerifyCgoPayment::dispatch($record);
                                }
                            }
                        }),
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Export to CSV'),
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
            'index' => Pages\ListCgoInvestments::route('/'),
            'create' => Pages\CreateCgoInvestment::route('/create'),
            'view' => Pages\ViewCgoInvestment::route('/{record}'),
            'edit' => Pages\EditCgoInvestment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'round']);
    }

    public static function getWidgets(): array
    {
        return [
            CgoInvestmentResource\Widgets\CgoInvestmentStats::class,
        ];
    }
}