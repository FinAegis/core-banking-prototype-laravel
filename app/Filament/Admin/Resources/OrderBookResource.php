<?php

/**
 * OrderBookResource — read-only list of the exchange order book projection.
 *
 * One row per trading pair with the best bid, best ask, last price and 24-hour
 * volume. The rows are a projection rebuilt from exchange events, so the list
 * has no create, edit or delete actions.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Exchange\Projections\OrderBook;
use App\Filament\Admin\Resources\OrderBookResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrderBookResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = OrderBook::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Exchange';

    public static function form(Form $form): Form
    {
        // Read-only resource — no form schema.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('base_currency')
                    ->label('Pair')
                    ->formatStateUsing(fn (OrderBook $record): string => $record->pair)
                    ->searchable(['base_currency', 'quote_currency']),
                Tables\Columns\TextColumn::make('best_bid')
                    ->label('Best bid')
                    ->formatStateUsing(fn ($state): string => self::decimal($state))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('best_ask')
                    ->label('Best ask')
                    ->formatStateUsing(fn ($state): string => self::decimal($state))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_price')
                    ->label('Last price')
                    ->formatStateUsing(fn ($state): string => self::decimal($state))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('volume_24h')
                    ->label('24h volume')
                    ->formatStateUsing(fn ($state): string => self::decimal($state)),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No order books yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderBooks::route('/'),
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
     * Drop the trailing zeros of a DECIMAL(36,18) string without a float cast.
     */
    private static function decimal(mixed $state): string
    {
        $value = (string) $state;

        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
