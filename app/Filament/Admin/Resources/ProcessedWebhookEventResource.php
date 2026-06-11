<?php

/**
 * ProcessedWebhookEventResource — webhook delivery forensics.
 *
 * Strictly read-only view of the processed_webhook_events dedupe table so an
 * operator can answer "did <provider> deliver event X, and when" without DB
 * access. Rows are the idempotency source of truth for HyperSwitch, Bridge,
 * Apple and Google webhook ingestion — mutating them here would re-open
 * replay windows, so no actions exist at all.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Filament\Admin\Resources\ProcessedWebhookEventResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProcessedWebhookEventResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = ProcessedWebhookEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Webhooks';

    protected static ?string $navigationLabel = 'Processed Events';

    protected static ?string $modelLabel = 'Processed Webhook Event';

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
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::providerLabel($state))
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_id')
                    ->label('Event ID')
                    ->limit(48)
                    ->copyable()
                    ->tooltip(fn (ProcessedWebhookEvent $record): string => $record->event_id)
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('processed_at', 'desc')
            ->filters([
                SelectFilter::make('provider')
                    ->options(fn (): array => self::providerOptions()),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No processed webhook events yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessedWebhookEvents::route('/'),
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

    private static function providerLabel(string $provider): string
    {
        return match ($provider) {
            'stripe'               => 'Stripe',
            'apple_iap'            => 'Apple IAP',
            'google_play'          => 'Google Play',
            'stripe_crypto_onramp' => 'Stripe Crypto Onramp',
            'hyperswitch'          => 'HyperSwitch',
            'bridge'               => 'Bridge',
            default                => ucwords(str_replace('_', ' ', $provider)),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function providerOptions(): array
    {
        /** @var array<int, string> $providers */
        $providers = ProcessedWebhookEvent::query()
            ->distinct()
            ->orderBy('provider')
            ->pluck('provider')
            ->all();

        return array_combine($providers, array_map(fn (string $p): string => self::providerLabel($p), $providers)) ?: [];
    }
}
