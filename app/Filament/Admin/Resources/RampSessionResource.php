<?php

/**
 * RampSessionResource — operator view of fiat ↔ crypto ramp sessions.
 *
 * Strictly read-only forensics: status/source/amount triage for Bridge (and
 * legacy provider) ramp sessions. The encrypted `deposit_instructions` blob
 * (bank account + memo PII) is NEVER rendered — the table only shows a
 * configured/absent badge derived from the raw (still-encrypted) column.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RampSessionResource\Pages;
use App\Models\RampSession;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RampSessionResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = RampSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Ramp';

    protected static ?string $navigationLabel = 'Ramp Sessions';

    protected static ?string $modelLabel = 'Ramp Session';

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
                Tables\Columns\TextColumn::make('id')
                    ->label('Session')
                    ->limit(8)
                    ->copyable()
                    ->tooltip(fn (RampSession $record): string => $record->id),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Direction')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'on' ? 'Onramp' : 'Offramp')
                    ->color(fn (string $state): string => $state === 'on' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RampSession::STATUS_COMPLETED  => 'success',
                        RampSession::STATUS_PROCESSING => 'info',
                        RampSession::STATUS_PENDING    => 'warning',
                        RampSession::STATUS_FAILED     => 'danger',
                        default                        => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state))
                    ->color(fn (string $state): string => $state === RampSession::SOURCE_BRIDGE_INITIATED ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('fiat_amount')
                    ->label('Fiat')
                    ->formatStateUsing(function (RampSession $record): string {
                        $amount = (string) $record->fiat_amount;

                        return (is_numeric($amount) ? bcadd($amount, '0', 2) : '—') . ' ' . $record->fiat_currency;
                    })
                    ->placeholder('—')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('crypto_amount')
                    ->label('Crypto')
                    ->formatStateUsing(function (RampSession $record): string {
                        $amount = (string) $record->crypto_amount;

                        return (is_numeric($amount) ? rtrim(rtrim(bcadd($amount, '0', 8), '0'), '.') : '—') . ' ' . $record->crypto_currency;
                    })
                    ->placeholder('—')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('deposit_instructions')
                    ->label('Deposit instructions')
                    ->badge()
                    // PII (bank account + memo) — show presence only; never decrypt.
                    ->getStateUsing(fn (RampSession $record): string => $record->getRawOriginal('deposit_instructions') !== null ? 'configured' : 'absent')
                    ->color(fn (string $state): string => $state === 'configured' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        RampSession::STATUS_PENDING    => 'Pending',
                        RampSession::STATUS_PROCESSING => 'Processing',
                        RampSession::STATUS_COMPLETED  => 'Completed',
                        RampSession::STATUS_FAILED     => 'Failed',
                        RampSession::STATUS_EXPIRED    => 'Expired',
                    ]),
                SelectFilter::make('source')
                    ->options([
                        RampSession::SOURCE_USER_INITIATED   => 'User initiated',
                        RampSession::SOURCE_BRIDGE_INITIATED => 'Bridge initiated',
                    ]),
                SelectFilter::make('type')
                    ->label('Direction')
                    ->options([
                        'on'  => 'Onramp',
                        'off' => 'Offramp',
                    ]),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No ramp sessions yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRampSessions::route('/'),
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
