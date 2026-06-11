<?php

/**
 * RevenueEventResource — read-only ledger of projected revenue events.
 *
 * revenue_events is the idempotent projection target (ADR-0002) — it must
 * never be mutated by hand, so this resource is strictly read-only. Amounts
 * are the ADR-0004 money triple (amount, decimals, denomination) and are
 * formatted with bcmath — never float.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Models\RevenueEvent;
use App\Filament\Admin\Resources\RevenueEventResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RevenueEventResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = RevenueEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Subscriptions';

    protected static ?string $navigationLabel = 'Revenue Events';

    protected static ?string $modelLabel = 'Revenue Event';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        // Read-only resource — no form schema.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RevenueEvent::TYPE_SUBSCRIPTION_INITIAL => 'info',
                        RevenueEvent::TYPE_SUBSCRIPTION_RENEWAL => 'success',
                        RevenueEvent::TYPE_REFUND               => 'danger',
                        default                                 => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User')
                    ->placeholder('—')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('source_event_id')
                    ->label('Source Event')
                    ->limit(24)
                    ->copyable()
                    ->tooltip(fn (RevenueEvent $record): ?string => $record->source_event_id)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (RevenueEvent $record): string => self::formatAmount($record))
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('aggregate_id')
                    ->label('Aggregate')
                    ->limit(24)
                    ->tooltip(fn (RevenueEvent $record): ?string => $record->aggregate_id)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('emitted_at')
                    ->label('Emitted')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('emitted_at', 'desc')
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Type')
                    ->options([
                        RevenueEvent::TYPE_SUBSCRIPTION_INITIAL => 'Subscription (initial)',
                        RevenueEvent::TYPE_SUBSCRIPTION_RENEWAL => 'Subscription (renewal)',
                        RevenueEvent::TYPE_REFUND               => 'Refund',
                    ]),
                SelectFilter::make('source_type')
                    ->label('Source')
                    ->options(fn (): array => self::sourceOptions()),
                Filter::make('emitted_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('emitted_at', '>=', $d))
                            ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('emitted_at', '<=', $d));
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No revenue events yet')
            ->emptyStateDescription('Rows are projected from revenue_outbox_events by ProjectRevenueOutbox.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRevenueEvents::route('/'),
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

    /**
     * Format the ADR-0004 money triple as "<major-units> <denomination>".
     * bcmath only — money is never floated.
     */
    public static function formatAmount(RevenueEvent $record): string
    {
        $decimals = max(0, $record->amount_decimals);
        $major = bcdiv((string) $record->amount, (string) (10 ** $decimals), $decimals);

        return $major . ' ' . $record->amount_denomination;
    }

    /**
     * @return array<string, string>
     */
    private static function sourceOptions(): array
    {
        /** @var array<int, string> $sources */
        $sources = RevenueEvent::query()
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type')
            ->all();

        return array_combine($sources, $sources) ?: [];
    }
}
