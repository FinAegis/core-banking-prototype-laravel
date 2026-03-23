<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Fraud\Models\AnomalyDetection;
use App\Filament\Admin\Resources\AnomalyDetectionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AnomalyDetectionResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = AnomalyDetection::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Fraud Detection';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Anomaly Alerts';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Alert Overview')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('id')
                                    ->label('Alert ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('anomaly_type')
                                    ->label('Anomaly Type')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                                Forms\Components\TextInput::make('detection_method')
                                    ->label('Detection Method')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Scoring')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('anomaly_score')
                                    ->label('Anomaly Score')
                                    ->suffix('/100')
                                    ->disabled(),
                                Forms\Components\TextInput::make('confidence')
                                    ->label('Confidence')
                                    ->disabled(),
                                Forms\Components\TextInput::make('severity')
                                    ->formatStateUsing(fn ($state) => ucfirst((string) $state))
                                    ->disabled(),
                                Forms\Components\Toggle::make('is_real_time')
                                    ->label('Real-Time Detection')
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Entity Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('entity_type')
                                    ->label('Entity Type')
                                    ->disabled(),
                                Forms\Components\TextInput::make('entity_id')
                                    ->label('Entity ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('model_version')
                                    ->label('Model Version')
                                    ->disabled(),
                                Forms\Components\TextInput::make('pipeline_run_id')
                                    ->label('Pipeline Run')
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Review')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('feedback_outcome')
                                    ->label('Outcome')
                                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', (string) $state)) : 'Pending')
                                    ->disabled(),
                                Forms\Components\Textarea::make('feedback_notes')
                                    ->label('Review Notes')
                                    ->disabled(),
                                Forms\Components\TextInput::make('reviewed_by')
                                    ->label('Reviewed By')
                                    ->disabled(),
                                Forms\Components\TextInput::make('reviewed_at')
                                    ->label('Reviewed At')
                                    ->disabled(),
                            ]
                        )->columns(2),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Detected')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('anomaly_type')
                        ->label('Type')
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->sortable(),
                    Tables\Columns\TextColumn::make('detection_method')
                        ->label('Method')
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('anomaly_score')
                        ->label('Score')
                        ->numeric(1)
                        ->sortable()
                        ->color(
                            fn ($state): string => match (true) {
                                $state >= 80 => 'danger',
                                $state >= 60 => 'warning',
                                $state >= 40 => 'info',
                                default      => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('severity')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ucfirst((string) $state))
                        ->color(
                            fn (string $state): string => match ($state) {
                                'critical' => 'danger',
                                'high'     => 'warning',
                                'medium'   => 'info',
                                'low'      => 'gray',
                                default    => 'gray',
                            }
                        )
                        ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(
                            fn ($state): string => match ($state->value) {
                                'detected'       => 'danger',
                                'investigating'  => 'warning',
                                'confirmed'      => 'danger',
                                'false_positive' => 'gray',
                                'resolved'       => 'success',
                                default          => 'gray',
                            }
                        )
                        ->sortable(),
                    Tables\Columns\IconColumn::make('is_real_time')
                        ->label('RT')
                        ->boolean()
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('User')
                        ->searchable()
                        ->toggleable(),
                ]
            )
            ->defaultSort('created_at', 'desc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            collect(\App\Domain\Fraud\Enums\AnomalyStatus::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('anomaly_type')
                        ->label('Type')
                        ->options(
                            collect(\App\Domain\Fraud\Enums\AnomalyType::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('severity')
                        ->options(
                            [
                                'critical' => 'Critical',
                                'high'     => 'High',
                                'medium'   => 'Medium',
                                'low'      => 'Low',
                            ]
                        ),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('mark_false_positive')
                        ->label('False Positive')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (AnomalyDetection $record): bool => ! $record->status->isTerminal())
                        ->action(
                            fn (AnomalyDetection $record) => $record->update([
                                'status'           => \App\Domain\Fraud\Enums\AnomalyStatus::FalsePositive,
                                'feedback_outcome' => 'false_positive',
                                'reviewed_at'      => now(),
                            ])
                        ),
                    Tables\Actions\Action::make('confirm_fraud')
                        ->label('Confirm')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (AnomalyDetection $record): bool => ! $record->status->isTerminal())
                        ->action(
                            fn (AnomalyDetection $record) => $record->update([
                                'status'           => \App\Domain\Fraud\Enums\AnomalyStatus::Confirmed,
                                'feedback_outcome' => 'confirmed_fraud',
                                'reviewed_at'      => now(),
                            ])
                        ),
                ]
            )
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnomalyDetections::route('/'),
            'view'  => Pages\ViewAnomalyDetection::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
