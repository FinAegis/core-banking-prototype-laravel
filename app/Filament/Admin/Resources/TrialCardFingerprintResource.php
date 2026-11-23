<?php

/**
 * TrialCardFingerprintResource — Plan B Backend-Q5 admin override.
 *
 * Read-only listing of trial_card_fingerprints rows. Operators can use the
 * "Block Override" action to push `last_used_at` 13 months into the past
 * (clearing the gate so the user can retry a trial) or to delete the row
 * outright. Used during fraud disputes and customer escalations.
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Models\TrialCardFingerprint;
use App\Domain\Subscription\Services\TrialFingerprintService;
use App\Filament\Admin\Resources\TrialCardFingerprintResource\Pages;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TrialCardFingerprintResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = TrialCardFingerprint::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Trial Fingerprints';

    protected static ?string $modelLabel = 'Trial Fingerprint';

    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fingerprint_hash')
                    ->label('Fingerprint Hash')
                    ->limit(16)
                    ->copyable()
                    ->tooltip(fn (TrialCardFingerprint $record): string => $record->fingerprint_hash),
                Tables\Columns\TextColumn::make('first_user_id')
                    ->label('First User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_user_id')
                    ->label('Last User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('trial_user_count')
                    ->label('Claims')
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_used_at')
                    ->label('First Used')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('eligible_after')
                    ->label('Eligible After')
                    ->getStateUsing(function (TrialCardFingerprint $record): string {
                        $eligibleAt = $record->last_used_at
                            ->copy()
                            ->addMonths(TrialFingerprintService::RETRY_WINDOW_MONTHS);

                        return $eligibleAt->isFuture()
                            ? $eligibleAt->toDateTimeString()
                            : 'Now';
                    }),
            ])
            ->defaultSort('last_used_at', 'desc')
            ->actions([
                Action::make('blockOverride')
                    ->label('Lift Trial Block')
                    ->color('warning')
                    ->icon('heroicon-o-shield-check')
                    ->requiresConfirmation()
                    ->modalHeading('Lift the trial block for this payment card?')
                    ->modalDescription('This moves the card\'s last trial date back 13 months. The user can then start a new trial straight away. Use this only after you have checked the case for fraud by hand.')
                    ->action(function (TrialCardFingerprint $record): void {
                        $record->forceFill([
                            'last_used_at' => Carbon::now()->subMonths(
                                TrialFingerprintService::RETRY_WINDOW_MONTHS + 1
                            ),
                        ])->save();

                        Notification::make()
                            ->title('Trial block lifted')
                            ->body('This payment card can now be used for a new trial.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Delete Record')
                    ->requiresConfirmation()
                    ->modalDescription('This removes the card fingerprint completely. The same payment card can then be used for a new trial straight away. Use "Lift Trial Block" instead, unless a fraud dispute requires the record to be erased.'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrialCardFingerprints::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
