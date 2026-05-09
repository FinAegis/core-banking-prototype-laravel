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
                            : 'now';
                    }),
            ])
            ->defaultSort('last_used_at', 'desc')
            ->actions([
                Action::make('blockOverride')
                    ->label('Block Override')
                    ->color('warning')
                    ->icon('heroicon-o-shield-check')
                    ->requiresConfirmation()
                    ->modalHeading('Override trial-block for this fingerprint?')
                    ->modalDescription('Sets last_used_at to 13 months ago, making the user eligible for a new trial immediately. Use only after manual fraud review.')
                    ->action(function (TrialCardFingerprint $record): void {
                        $record->forceFill([
                            'last_used_at' => Carbon::now()->subMonths(
                                TrialFingerprintService::RETRY_WINDOW_MONTHS + 1
                            ),
                        ])->save();

                        Notification::make()
                            ->title('Trial block overridden')
                            ->body('Fingerprint is now eligible for a new trial.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Delete Row')
                    ->requiresConfirmation()
                    ->modalDescription('Removes the fingerprint row entirely. The user can immediately reuse the same card for a new trial. Prefer "Block Override" unless this is a hard fraud-disputes wipe.'),
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
