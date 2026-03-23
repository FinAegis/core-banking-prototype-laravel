<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Relayer\Models\SmartAccount;
use App\Filament\Admin\Resources\SmartAccountResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmartAccountResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = SmartAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Relayer';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Smart Accounts';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Account Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('id')
                                    ->label('Account ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('account_address')
                                    ->label('Account Address')
                                    ->disabled(),
                                Forms\Components\TextInput::make('owner_address')
                                    ->label('Owner Address')
                                    ->disabled(),
                                Forms\Components\TextInput::make('network')
                                    ->disabled(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Deployment')
                        ->schema(
                            [
                                Forms\Components\Toggle::make('deployed')
                                    ->label('Deployed')
                                    ->disabled(),
                                Forms\Components\TextInput::make('deploy_tx_hash')
                                    ->label('Deploy TX Hash')
                                    ->disabled(),
                                Forms\Components\TextInput::make('nonce')
                                    ->disabled(),
                                Forms\Components\TextInput::make('pending_ops')
                                    ->label('Pending Ops')
                                    ->disabled(),
                            ]
                        )->columns(4),

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
                    Tables\Columns\TextColumn::make('account_address')
                        ->label('Account Address')
                        ->searchable()
                        ->limit(20)
                        ->tooltip(fn ($record): string => $record->account_address),
                    Tables\Columns\TextColumn::make('owner_address')
                        ->label('Owner Address')
                        ->limit(20)
                        ->tooltip(fn ($record): string => $record->owner_address),
                    Tables\Columns\TextColumn::make('network')
                        ->badge(),
                    Tables\Columns\IconColumn::make('deployed')
                        ->boolean(),
                    Tables\Columns\TextColumn::make('nonce')
                        ->numeric(),
                    Tables\Columns\TextColumn::make('pending_ops')
                        ->label('Pending Ops')
                        ->numeric()
                        ->color(
                            fn ($state): string => match (true) {
                                $state >= 5 => 'warning',
                                $state >= 1 => 'info',
                                default     => 'success',
                            }
                        ),
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('User')
                        ->toggleable(),
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
                    Tables\Filters\TernaryFilter::make('deployed'),
                    Tables\Filters\SelectFilter::make('network')
                        ->options(
                            fn (): array => SmartAccount::query()
                                ->distinct()
                                ->pluck('network', 'network')
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
            'index' => Pages\ListSmartAccounts::route('/'),
            'view'  => Pages\ViewSmartAccount::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
