<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\KeyManagement\Enums\ShardStatus;
use App\Domain\KeyManagement\Enums\ShardType;
use App\Domain\KeyManagement\Models\KeyShardRecord;
use App\Filament\Admin\Resources\KeyShardRecordResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KeyShardRecordResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = KeyShardRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Key Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Key Shards';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Shard Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('uuid')
                                    ->label('UUID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('shard_type')
                                    ->label('Shard Type')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                                Forms\Components\TextInput::make('shard_index')
                                    ->label('Shard Index')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                                Forms\Components\TextInput::make('key_version')
                                    ->label('Key Version')
                                    ->disabled(),
                                Forms\Components\TextInput::make('public_key_hash')
                                    ->label('Public Key Hash')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Access')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('last_accessed_at')
                                    ->label('Last Accessed At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('created_at')
                                    ->label('Created At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('updated_at')
                                    ->label('Updated At')
                                    ->disabled(),
                            ]
                        )->columns(3),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('uuid')
                        ->label('UUID')
                        ->limit(12)
                        ->tooltip(fn ($record): string => $record->uuid)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('shard_type')
                        ->label('Type')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label()),
                    Tables\Columns\TextColumn::make('shard_index')
                        ->label('Index'),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(
                            fn ($state): string => match ($state->value) {
                                'active'  => 'success',
                                'revoked' => 'danger',
                                'rotated' => 'gray',
                                'pending' => 'warning',
                                default   => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('key_version')
                        ->label('Key Version'),
                    Tables\Columns\TextColumn::make('last_accessed_at')
                        ->label('Last Accessed')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(),
                ]
            )
            ->defaultSort('created_at', 'desc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('shard_type')
                        ->label('Shard Type')
                        ->options(
                            collect(ShardType::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            collect(ShardStatus::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
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
            'index' => Pages\ListKeyShardRecords::route('/'),
            'view'  => Pages\ViewKeyShardRecord::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
