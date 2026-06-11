<?php

/**
 * BridgeCustomerResource — operator view of Bridge.xyz customer records.
 *
 * Read-only listing of bridge_customers rows (KYC state, dev fee, virtual
 * account presence) so support can answer "why is this user's ramp stuck"
 * without SSH. Two targeted actions:
 *
 *   - "Sync dev fee": wraps BridgeDeveloperFeeSync::syncForUser — the same
 *     service behind `php artisan bridge:sync-dev-fee --email=...`.
 *   - "Retry VA provisioning": wraps BridgePostKycHandler::
 *     tryProvisionVirtualAccount for approved customers missing a virtual
 *     account (the stuck-VA case in docs/operations/bridge-ramp.md).
 *
 * Bridge KYC state here is bridge_customers.kyc_status — NEVER conflate it
 * with users.kyc_status (Ondato/TrustCert), per CLAUDE.md.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Compliance\Kyc\Services\BridgeDeveloperFeeSync;
use App\Domain\Compliance\Kyc\Services\BridgePostKycHandler;
use App\Filament\Admin\Resources\BridgeCustomerResource\Pages;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class BridgeCustomerResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = BridgeCustomer::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Ramp';

    protected static ?string $navigationLabel = 'Bridge Customers';

    protected static ?string $modelLabel = 'Bridge Customer';

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
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bridge_customer_id')
                    ->label('Bridge ID')
                    ->limit(20)
                    ->copyable()
                    ->tooltip(fn (BridgeCustomer $record): string => $record->bridge_customer_id)
                    ->searchable(),
                Tables\Columns\TextColumn::make('kyc_status')
                    ->label('Bridge KYC')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        BridgeCustomer::KYC_APPROVED => 'success',
                        BridgeCustomer::KYC_PENDING  => 'warning',
                        BridgeCustomer::KYC_REJECTED => 'danger',
                        default                      => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('developer_fee_bps')
                    ->label('Dev fee (bps)')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\IconColumn::make('virtual_account_id')
                    ->label('VA')
                    ->boolean()
                    ->getStateUsing(fn (BridgeCustomer $record): bool => $record->hasVirtualAccount())
                    ->tooltip(fn (BridgeCustomer $record): string => $record->virtual_account_id ?? 'No virtual account provisioned'),
                Tables\Columns\TextColumn::make('kyc_link_expires_at')
                    ->label('KYC Link Expires')
                    ->dateTime('Y-m-d H:i')
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
                SelectFilter::make('kyc_status')
                    ->label('Bridge KYC')
                    ->options([
                        BridgeCustomer::KYC_NOT_STARTED => 'Not started',
                        BridgeCustomer::KYC_PENDING     => 'Pending',
                        BridgeCustomer::KYC_APPROVED    => 'Approved',
                        BridgeCustomer::KYC_REJECTED    => 'Rejected',
                    ]),
                Filter::make('missing_va')
                    ->label('Approved but missing VA')
                    ->query(function (Builder $query): Builder {
                        /** @var Builder<BridgeCustomer> $query */
                        return $query
                            ->where('kyc_status', BridgeCustomer::KYC_APPROVED)
                            ->whereNull('virtual_account_id');
                    }),
            ])
            ->actions([
                Action::make('syncDevFee')
                    ->label('Sync dev fee')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Sync developer fee with subscription tier?')
                    ->modalDescription('PATCHes the Bridge customer so developer_fee_bps matches the user\'s current tier (Free=75, Pro=0). Same logic as `bridge:sync-dev-fee`.')
                    ->action(function (BridgeCustomer $record): void {
                        static::runSyncDevFee($record);
                    }),
                Action::make('retryVaProvisioning')
                    ->label('Retry VA provisioning')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn (BridgeCustomer $record): bool => $record->isKycApproved() && ! $record->hasVirtualAccount())
                    ->requiresConfirmation()
                    ->modalHeading('Retry virtual account provisioning?')
                    ->modalDescription('Idempotent — re-runs BridgePostKycHandler VA provisioning. Requires the user to have a registered Polygon address; Bridge Idempotency-Key prevents duplicate VAs.')
                    ->action(function (BridgeCustomer $record): void {
                        static::runRetryVaProvisioning($record);
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No Bridge customers yet')
            ->emptyStateDescription('Rows appear when users start Bridge KYC.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBridgeCustomers::route('/'),
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

    protected static function runSyncDevFee(BridgeCustomer $record): void
    {
        $user = $record->user;

        if ($user === null) {
            Notification::make()
                ->title('Sync failed')
                ->body('No user found for this Bridge customer.')
                ->danger()
                ->send();

            return;
        }

        try {
            $result = app(BridgeDeveloperFeeSync::class)->syncForUser($user);
        } catch (Throwable $e) {
            Log::error('admin.bridge.sync_dev_fee_failed', [
                'operator_id'        => auth()->id(),
                'bridge_customer_id' => $record->bridge_customer_id,
                'exception'          => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Sync failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Log::info('admin.bridge.sync_dev_fee', [
            'operator_id'        => auth()->id(),
            'bridge_customer_id' => $record->bridge_customer_id,
            'developer_fee_bps'  => $result,
        ]);

        Notification::make()
            ->title('Dev fee synced')
            ->body(sprintf('developer_fee_bps is now %d.', (int) $result))
            ->success()
            ->send();
    }

    protected static function runRetryVaProvisioning(BridgeCustomer $record): void
    {
        try {
            app(BridgePostKycHandler::class)->tryProvisionVirtualAccount($record);
        } catch (Throwable $e) {
            Log::error('admin.bridge.retry_va_failed', [
                'operator_id'        => auth()->id(),
                'bridge_customer_id' => $record->bridge_customer_id,
                'exception'          => $e->getMessage(),
            ]);

            Notification::make()
                ->title('VA provisioning failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $record->refresh();

        Log::info('admin.bridge.retry_va', [
            'operator_id'        => auth()->id(),
            'bridge_customer_id' => $record->bridge_customer_id,
            'virtual_account_id' => $record->virtual_account_id,
        ]);

        if ($record->hasVirtualAccount()) {
            Notification::make()
                ->title('Virtual account provisioned')
                ->body('VA ' . $record->virtual_account_id . ' is now active.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Still no virtual account')
            ->body('Provisioning was deferred or failed — check logs. Most common cause: the user has no Polygon address in blockchain_addresses yet.')
            ->warning()
            ->send();
    }
}
