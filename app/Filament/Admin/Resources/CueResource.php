<?php

/**
 * CueResource — Plan B Slice 4 Filament admin for the cues table.
 *
 * Read-only listing of cue rows with operator override actions.
 * Follows TrialCardFingerprintResource conventions exactly:
 * - RespectsModuleVisibility trait
 * - Commerce nav group (matches slice 1 convention)
 * - canCreate(): false
 * - canEdit(): false
 * - Empty bulk actions
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.9
 */

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Subscription\Models\Cue;
use App\Filament\Admin\Resources\CueResource\Pages;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CueResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;

    protected static ?string $model = Cue::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Cue Queue';

    protected static ?string $modelLabel = 'Cue';

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->limit(8)
                    ->copyable()
                    ->tooltip(fn (Cue $record): string => $record->id),
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('priority')
                    ->label('Priority')
                    ->colors([
                        'danger'  => 'critical',
                        'warning' => 'high',
                        'gray'    => 'normal',
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(function (Cue $record): string {
                        if ($record->dismissed_at !== null) {
                            return 'dismissed';
                        }

                        $now = now();

                        if ($record->due_at->gt($now)) {
                            return 'pending';
                        }

                        if ($record->expires_at->lt($now)) {
                            return 'expired';
                        }

                        return 'pending';
                    })
                    ->badge()
                    ->colors([
                        'success' => 'pending',
                        'gray'    => 'dismissed',
                        'danger'  => 'expired',
                    ]),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Due At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires At')
                    ->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('dismissed_at')
                    ->label('Dismissed At')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('idempotency_key')
                    ->label('Idempotency Key')
                    ->limit(8)
                    ->copyable()
                    ->tooltip(fn (Cue $record): string => $record->idempotency_key),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('kind')
                    ->label('Kind')
                    ->options([
                        'trial_ending_2d'                => 'trial_ending_2d',
                        'trial_ending_1d'                => 'trial_ending_1d',
                        'trial_ending_1h'                => 'trial_ending_1h',
                        'payment_failed'                 => 'payment_failed',
                        'subscription_canceled_external' => 'subscription_canceled_external',
                        'refund_processed'               => 'refund_processed',
                        'grace_period_started'           => 'grace_period_started',
                        'kyc_required'                   => 'kyc_required',
                        'family_sharing_unsupported'     => 'family_sharing_unsupported',
                        'export_ready'                   => 'export_ready',
                        'pro_trial_reminder_d1'          => 'pro_trial_reminder_d1',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'   => 'Pending',
                        'dismissed' => 'Dismissed',
                    ])
                    ->query(function ($query, array $data): void {
                        if (! isset($data['value']) || $data['value'] === '') {
                            return;
                        }

                        if ($data['value'] === 'pending') {
                            $query->whereNull('dismissed_at');
                        } elseif ($data['value'] === 'dismissed') {
                            $query->whereNotNull('dismissed_at');
                        }
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')->label('Created from'),
                        \Filament\Forms\Components\DatePicker::make('created_until')->label('Created until'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn ($q, $date) => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn ($q, $date) => $q->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Action::make('markDismissed')
                    ->label('Mark dismissed')
                    ->color('warning')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Dismiss this cue?')
                    ->modalDescription('Sets dismissed_at = now(). Use when a cue is known-stale and the user should not see it.')
                    ->visible(fn (Cue $record): bool => $record->dismissed_at === null)
                    ->action(function (Cue $record): void {
                        $record->forceFill([
                            'dismissed_at'     => now(),
                            'dismissed_action' => 'dismissed',
                        ])->save();

                        Notification::make()
                            ->title('Cue dismissed')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Force delete')
                    ->requiresConfirmation()
                    ->modalHeading('Force delete this cue?')
                    ->modalDescription('Hard-deletes the row. Use only when the cue was created erroneously. This action cannot be undone.')
                    ->visible(fn (): bool => auth()->user()?->canAccessPanel(\Filament\Facades\Filament::getCurrentPanel() ?? \Filament\Facades\Filament::getDefaultPanel()) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCues::route('/'),
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
