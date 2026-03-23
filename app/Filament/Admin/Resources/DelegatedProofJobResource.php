<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Privacy\Models\DelegatedProofJob;
use App\Filament\Admin\Resources\DelegatedProofJobResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DelegatedProofJobResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = DelegatedProofJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Privacy';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Proof Verifications';

    protected static ?string $pluralModelLabel = 'Proof Verifications';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Proof Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('id')
                                    ->label('Job ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('proof_type')
                                    ->label('Proof Type')
                                    ->disabled(),
                                Forms\Components\TextInput::make('network')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->disabled(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Progress')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('progress')
                                    ->label('Progress (%)')
                                    ->suffix('%')
                                    ->disabled(),
                                Forms\Components\TextInput::make('estimated_seconds')
                                    ->label('Estimated Seconds')
                                    ->disabled(),
                                Forms\Components\Textarea::make('error')
                                    ->label('Error')
                                    ->disabled()
                                    ->columnSpanFull(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Timestamps')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('created_at')
                                    ->label('Created At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('updated_at')
                                    ->label('Updated At')
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
                        ->label('Date')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('proof_type')
                        ->label('Proof Type')
                        ->sortable()
                        ->searchable(),
                    Tables\Columns\TextColumn::make('network')
                        ->sortable()
                        ->searchable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->color(
                            fn (string $state): string => match ($state) {
                                'queued'     => 'gray',
                                'processing' => 'warning',
                                'completed'  => 'success',
                                'failed'     => 'danger',
                                default      => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('progress')
                        ->suffix('%')
                        ->sortable(),
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
                            [
                                'queued'     => 'Queued',
                                'processing' => 'Processing',
                                'completed'  => 'Completed',
                                'failed'     => 'Failed',
                            ]
                        ),
                    Tables\Filters\SelectFilter::make('proof_type')
                        ->label('Proof Type')
                        ->options(
                            fn () => DelegatedProofJob::query()
                                ->distinct()
                                ->pluck('proof_type', 'proof_type')
                                ->all()
                        ),
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListDelegatedProofJobs::route('/'),
            'view'  => Pages\ViewDelegatedProofJob::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
