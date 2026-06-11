<?php

/**
 * IapSubscriptionResource — operator view of Apple/Google IAP subscriptions.
 *
 * Read-only: answers "how many active Pro subscribers", "what store is this
 * user billed through", and "when does their period end" without SQL access.
 * Mutations stay with the IAP verify endpoint + store webhooks — admin edits
 * would desync us from the store-side source of truth.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Models\IapSubscription;
use App\Filament\Admin\Resources\IapSubscriptionResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class IapSubscriptionResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = IapSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?string $navigationLabel = 'IAP Subscriptions';

    protected static ?string $modelLabel = 'IAP Subscription';

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
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('store')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === IapSubscription::STORE_APPLE ? 'Apple' : 'Google')
                    ->color(fn (string $state): string => $state === IapSubscription::STORE_APPLE ? 'gray' : 'success'),
                Tables\Columns\TextColumn::make('tier')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color('info'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        IapSubscription::STATUS_ACTIVE   => 'success',
                        IapSubscription::STATUS_TRIALING => 'info',
                        IapSubscription::STATUS_PAST_DUE,
                        IapSubscription::STATUS_GRACE_PERIOD => 'warning',
                        IapSubscription::STATUS_REFUNDED     => 'danger',
                        default                              => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_period_ends_at')
                    ->label('Period Ends')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\IconColumn::make('cancel_at_period_end')
                    ->label('Cancels?')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_notification_type')
                    ->label('Last Store Event')
                    ->limit(24)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('store')
                    ->options([
                        IapSubscription::STORE_APPLE  => 'Apple',
                        IapSubscription::STORE_GOOGLE => 'Google',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        IapSubscription::STATUS_ACTIVE       => 'Active',
                        IapSubscription::STATUS_TRIALING     => 'Trialing',
                        IapSubscription::STATUS_PAST_DUE     => 'Past due',
                        IapSubscription::STATUS_GRACE_PERIOD => 'Grace period',
                        IapSubscription::STATUS_PAUSED       => 'Paused',
                        IapSubscription::STATUS_CANCELLED    => 'Cancelled',
                        IapSubscription::STATUS_EXPIRED      => 'Expired',
                        IapSubscription::STATUS_REFUNDED     => 'Refunded',
                    ]),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No IAP subscriptions yet')
            ->emptyStateDescription('Rows appear after the first successful /subscriptions/iap/verify.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIapSubscriptions::route('/'),
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
