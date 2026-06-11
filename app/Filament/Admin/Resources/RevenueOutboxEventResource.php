<?php

/**
 * RevenueOutboxEventResource — operator view of the revenue outbox.
 *
 * Built as its own resource (not a relation manager on RevenueEvent): the
 * outbox and the projection share only a composite logical key
 * (source_type, source_event_id) — there is no Eloquent relation to manage.
 *
 * The "Re-dispatch" action queues ProjectRevenueOutbox for a single failed or
 * stuck-pending row. Safe to repeat: projection is idempotent on
 * (source_type, source_event_id, event_type) via uniq_revenue_event_source,
 * so a replay never double-counts revenue.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Jobs\ProjectRevenueOutbox;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Filament\Admin\Resources\RevenueOutboxEventResource\Pages;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class RevenueOutboxEventResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = RevenueOutboxEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?string $navigationLabel = 'Revenue Outbox';

    protected static ?string $modelLabel = 'Revenue Outbox Event';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        // Read-only resource — no form schema.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source_event_id')
                    ->label('Source Event')
                    ->limit(28)
                    ->copyable()
                    ->tooltip(fn (RevenueOutboxEvent $record): string => $record->source_event_id)
                    ->searchable(),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('event_kind')
                    ->label('Kind')
                    ->limit(32)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RevenueOutboxEvent::STATUS_DELIVERED => 'success',
                        RevenueOutboxEvent::STATUS_PENDING   => 'warning',
                        RevenueOutboxEvent::STATUS_FAILED    => 'danger',
                        default                              => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('attempts')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('failed_reason')
                    ->label('Failure')
                    ->limit(40)
                    ->placeholder('—')
                    ->tooltip(fn (RevenueOutboxEvent $record): ?string => $record->failed_reason)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Queued')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        RevenueOutboxEvent::STATUS_PENDING   => 'Pending',
                        RevenueOutboxEvent::STATUS_DELIVERED => 'Delivered',
                        RevenueOutboxEvent::STATUS_FAILED    => 'Failed',
                    ]),
                SelectFilter::make('source_type')
                    ->label('Source')
                    ->options([
                        RevenueOutboxEvent::SOURCE_STRIPE               => 'Stripe',
                        RevenueOutboxEvent::SOURCE_APPLE_IAP            => 'Apple IAP',
                        RevenueOutboxEvent::SOURCE_GOOGLE_PLAY          => 'Google Play',
                        RevenueOutboxEvent::SOURCE_STRIPE_CRYPTO_ONRAMP => 'Stripe Crypto Onramp',
                        RevenueOutboxEvent::SOURCE_ONDATO               => 'Ondato',
                    ]),
            ])
            ->actions([
                Action::make('redispatch')
                    ->label('Re-dispatch')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (RevenueOutboxEvent $record): bool => $record->status !== RevenueOutboxEvent::STATUS_DELIVERED)
                    ->requiresConfirmation()
                    ->modalHeading('Re-dispatch this outbox row?')
                    ->modalDescription('Queues ProjectRevenueOutbox for this row. Projection is idempotent on (source_type, source_event_id, event_type) — a replay never double-counts revenue.')
                    ->action(function (RevenueOutboxEvent $record): void {
                        ProjectRevenueOutbox::dispatch((int) $record->id);

                        Log::info('admin.revenue_outbox.redispatch', [
                            'operator_id'     => auth()->id(),
                            'row_id'          => $record->id,
                            'source_type'     => $record->source_type,
                            'source_event_id' => $record->source_event_id,
                        ]);

                        Notification::make()
                            ->title('Outbox row re-dispatched')
                            ->body('ProjectRevenueOutbox queued for row #' . $record->id . '.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Revenue outbox is empty');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRevenueOutboxEvents::route('/'),
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
