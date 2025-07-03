<?php

namespace App\Filament\Pages;

use App\Models\CgoInvestment;
use App\Services\Cgo\PaymentVerificationService;
use App\Services\Cgo\StripePaymentService;
use App\Services\Cgo\CoinbaseCommerceService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Queue;
use Livewire\Attributes\On;

class CgoPaymentVerificationDashboard extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'CGO Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Payment Verification Dashboard';
    protected static ?string $navigationLabel = 'Payment Verification';

    protected static string $view = 'filament.pages.cgo-payment-verification-dashboard';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CgoInvestment::query()
                    ->with(['user', 'round'])
                    ->whereIn('payment_status', ['pending', 'processing'])
                    ->orWhere(function ($query) {
                        $query->where('status', 'pending')
                            ->where('payment_method', 'bank_transfer');
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Investor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('tier')
                    ->colors([
                        'warning' => 'bronze',
                        'gray' => 'silver',
                        'warning' => 'gold',
                    ]),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'stripe' => 'Card',
                        'bank_transfer' => 'Bank',
                        'crypto' => 'Crypto',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'stripe',
                        'success' => 'bank_transfer',
                        'warning' => 'crypto',
                    ]),
                Tables\Columns\BadgeColumn::make('payment_status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Reference')
                    ->getStateUsing(fn ($record) => match($record->payment_method) {
                        'stripe' => $record->stripe_payment_intent_id ?? 'N/A',
                        'crypto' => $record->coinbase_charge_code ?? 'N/A',
                        'bank_transfer' => $record->bank_transfer_reference ?? 'N/A',
                        default => 'N/A',
                    })
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Initiated')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('time_pending')
                    ->label('Time Pending')
                    ->getStateUsing(fn ($record) => $record->created_at->diffForHumans(null, true))
                    ->color(fn ($record) => 
                        $record->created_at->diffInHours() > 24 ? 'danger' : 
                        ($record->created_at->diffInHours() > 12 ? 'warning' : 'gray')
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'stripe' => 'Card Payment',
                        'bank_transfer' => 'Bank Transfer',
                        'crypto' => 'Cryptocurrency',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                    ]),
                Tables\Filters\Filter::make('urgent')
                    ->label('Urgent (>24h)')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('created_at', '<=', now()->subDay())
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_payment')
                    ->label('Verify')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Verify Payment')
                    ->modalDescription('This will check the payment status with the payment processor.')
                    ->action(function (CgoInvestment $record) {
                        $this->verifyPayment($record);
                    })
                    ->visible(fn (CgoInvestment $record) => 
                        in_array($record->payment_method, ['stripe', 'crypto'])
                    ),
                    
                Tables\Actions\Action::make('manual_verify')
                    ->label('Manual Verify')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('reference')
                            ->label('Transaction Reference')
                            ->required()
                            ->placeholder('Enter bank transaction reference'),
                        Forms\Components\TextInput::make('amount_received')
                            ->label('Amount Received')
                            ->numeric()
                            ->prefix('$')
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Verification Notes')
                            ->rows(3),
                    ])
                    ->action(function (array $data, CgoInvestment $record) {
                        $this->manualVerifyPayment($record, $data);
                    })
                    ->visible(fn (CgoInvestment $record) => 
                        $record->payment_method === 'bank_transfer'
                    ),
                    
                Tables\Actions\Action::make('mark_failed')
                    ->label('Mark Failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Failure Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data, CgoInvestment $record) {
                        $this->markPaymentFailed($record, $data['reason']);
                    }),
                    
                Tables\Actions\Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalContent(fn (CgoInvestment $record) => view('filament.modals.cgo-payment-details', [
                        'investment' => $record
                    ]))
                    ->modalHeading('Payment Details')
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_verify')
                    ->label('Bulk Verify')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            if (in_array($record->payment_method, ['stripe', 'crypto'])) {
                                $this->verifyPayment($record);
                            }
                        }
                    }),
            ])
            ->poll('10s');
    }

    protected function verifyPayment(CgoInvestment $investment): void
    {
        try {
            Queue::push(new \App\Jobs\VerifyCgoPayment($investment));
            
            Notification::make()
                ->title('Payment Verification Queued')
                ->body("Verification for investment {$investment->uuid} has been queued.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Verification Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function manualVerifyPayment(CgoInvestment $investment, array $data): void
    {
        try {
            $investment->update([
                'payment_status' => 'completed',
                'status' => 'confirmed',
                'payment_completed_at' => now(),
                'bank_transfer_reference' => $data['reference'],
                'amount_paid' => $data['amount_received'] * 100, // Convert to cents
                'metadata' => array_merge($investment->metadata ?? [], [
                    'manual_verification' => [
                        'verified_by' => auth()->id(),
                        'verified_at' => now()->toIso8601String(),
                        'notes' => $data['notes'],
                    ]
                ])
            ]);

            // Update pricing round
            if ($investment->round) {
                $investment->round->increment('shares_sold', $investment->shares_purchased);
                $investment->round->increment('total_raised', $investment->amount);
            }

            Notification::make()
                ->title('Payment Verified')
                ->body("Investment {$investment->uuid} has been manually verified.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Verification Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function markPaymentFailed(CgoInvestment $investment, string $reason): void
    {
        try {
            $investment->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
                'payment_failed_at' => now(),
                'payment_failure_reason' => $reason,
            ]);

            Notification::make()
                ->title('Payment Marked as Failed')
                ->body("Investment {$investment->uuid} has been marked as failed.")
                ->warning()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Update Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    #[On('payment-verified')]
    public function refreshTable(): void
    {
        $this->resetTable();
    }

    public function getWidgets(): array
    {
        return [
            PaymentVerificationStats::class,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentVerificationStats::class,
        ];
    }
}