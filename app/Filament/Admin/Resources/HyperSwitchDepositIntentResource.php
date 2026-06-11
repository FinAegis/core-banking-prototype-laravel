<?php

/**
 * HyperSwitchDepositIntentResource — the completion_failed reconciliation
 * worklist (plus general intent forensics).
 *
 * The table filter DEFAULTS to `completion_failed`: payment succeeded at
 * HyperSwitch but the tenant-side credit/completion threw after the webhook
 * claim, so these rows need a human (see HyperSwitchWebhookController).
 *
 * Why there is NO "Retry credit" action: the webhook credits FIRST
 * (AccountCreditService::credit — a plain, non-idempotent balance increment)
 * and completes the deposit aggregate SECOND. A `completion_failed` row
 * cannot tell us which step threw — if the credit landed and only the
 * aggregate persist failed, re-running the credit would double-pay the user.
 * There is no idempotency key on the credit itself (the
 * processed_webhook_events dedupe row was already consumed by the original
 * claim). Until a service-level idempotent re-credit exists, the safe
 * operator path is: verify the balance/aggregate by hand, fix via the normal
 * ledger tools, then "Mark reconciled" here with a note.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use App\Filament\Admin\Resources\HyperSwitchDepositIntentResource\Pages;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class HyperSwitchDepositIntentResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = HyperSwitchDepositIntent::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?string $navigationLabel = 'HyperSwitch Intents';

    protected static ?string $modelLabel = 'HyperSwitch Deposit Intent';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        // Read-only resource — no form schema.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('hyperswitch_payment_id')
                    ->label('Payment')
                    ->limit(24)
                    ->copyable()
                    ->tooltip(fn (HyperSwitchDepositIntent $record): string => $record->hyperswitch_payment_id)
                    ->searchable(),
                Tables\Columns\TextColumn::make('deposit_uuid')
                    ->label('Deposit')
                    ->limit(8)
                    ->copyable()
                    ->tooltip(fn (HyperSwitchDepositIntent $record): string => $record->deposit_uuid),
                Tables\Columns\TextColumn::make('account_uuid')
                    ->label('Account')
                    ->limit(8)
                    ->copyable()
                    ->tooltip(fn (HyperSwitchDepositIntent $record): string => $record->account_uuid)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount_cents')
                    ->label('Amount')
                    // Minor → major units via bcmath; money is never floated.
                    ->formatStateUsing(fn (HyperSwitchDepositIntent $record): string => bcdiv((string) $record->amount_cents, '100', 2) . ' ' . $record->currency)
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        HyperSwitchDepositIntent::STATUS_COMPLETED  => 'success',
                        HyperSwitchDepositIntent::STATUS_PROCESSING => 'info',
                        HyperSwitchDepositIntent::STATUS_PENDING    => 'warning',
                        HyperSwitchDepositIntent::STATUS_FAILED,
                        HyperSwitchDepositIntent::STATUS_COMPLETION_FAILED => 'danger',
                        HyperSwitchDepositIntent::STATUS_RECONCILED        => 'gray',
                        default                                            => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('reconciliation_note')
                    ->label('Reconciliation')
                    ->limit(40)
                    ->placeholder('—')
                    ->tooltip(fn (HyperSwitchDepositIntent $record): ?string => $record->reconciliation_note)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reconciled_by')
                    ->label('Reconciled By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reconciled_at')
                    ->label('Reconciled At')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        HyperSwitchDepositIntent::STATUS_PENDING           => 'Pending',
                        HyperSwitchDepositIntent::STATUS_PROCESSING        => 'Processing',
                        HyperSwitchDepositIntent::STATUS_COMPLETED         => 'Completed',
                        HyperSwitchDepositIntent::STATUS_FAILED            => 'Failed',
                        HyperSwitchDepositIntent::STATUS_COMPLETION_FAILED => 'Completion failed',
                        HyperSwitchDepositIntent::STATUS_RECONCILED        => 'Reconciled',
                    ])
                    // The operator worklist: open the page, see what needs fixing.
                    ->default(HyperSwitchDepositIntent::STATUS_COMPLETION_FAILED),
            ])
            ->actions([
                Action::make('markReconciled')
                    ->label('Mark reconciled')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (HyperSwitchDepositIntent $record): bool => $record->status === HyperSwitchDepositIntent::STATUS_COMPLETION_FAILED)
                    ->requiresConfirmation()
                    ->modalHeading('Mark this intent as manually reconciled?')
                    ->modalDescription('Use ONLY after verifying the account balance and deposit aggregate by hand. This does NOT move money — it records that an operator settled the case and removes the row from the completion_failed worklist.')
                    ->form([
                        Textarea::make('reconciliation_note')
                            ->label('Reconciliation note')
                            ->helperText('How was this settled? Reference the manual credit / refund / ledger adjustment.')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data, HyperSwitchDepositIntent $record): void {
                        $operator = auth()->user();

                        $record->forceFill([
                            'status'              => HyperSwitchDepositIntent::STATUS_RECONCILED,
                            'reconciliation_note' => (string) $data['reconciliation_note'],
                            'reconciled_by'       => $operator instanceof \App\Models\User ? $operator->email : null,
                            'reconciled_at'       => now(),
                        ])->save();

                        Log::info('admin.hyperswitch.intent_reconciled', [
                            'operator_id' => auth()->id(),
                            'payment_id'  => $record->hyperswitch_payment_id,
                        ]);

                        Notification::make()
                            ->title('Intent marked reconciled')
                            ->body('Payment ' . $record->hyperswitch_payment_id . ' moved out of the completion_failed worklist.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No intents match the current filter')
            ->emptyStateDescription('The status filter defaults to completion_failed — clear it to see all deposit intents.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHyperSwitchDepositIntents::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
