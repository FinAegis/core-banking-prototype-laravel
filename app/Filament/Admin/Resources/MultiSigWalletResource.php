<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Wallet\Models\MultiSigWallet;
use App\Filament\Admin\Resources\MultiSigWalletResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MultiSigWalletResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = MultiSigWallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Wallets';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Multi-Sig Wallets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Wallet Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('address')
                                    ->disabled(),
                                Forms\Components\TextInput::make('chain')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                                    ->disabled(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Signature Configuration')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('required_signatures')
                                    ->label('Required Signatures')
                                    ->disabled(),
                                Forms\Components\TextInput::make('total_signers')
                                    ->label('Total Signers')
                                    ->disabled(),
                            ]
                        )->columns(2),

                    Forms\Components\Section::make('Timestamps')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('created_at')
                                    ->label('Created')
                                    ->disabled(),
                                Forms\Components\TextInput::make('updated_at')
                                    ->label('Updated')
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
                    Tables\Columns\TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('address')
                        ->searchable()
                        ->limit(20)
                        ->tooltip(fn ($state) => $state)
                        ->placeholder('Not deployed'),
                    Tables\Columns\TextColumn::make('chain')
                        ->badge()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('required_signatures')
                        ->label('Req. Sigs')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('total_signers')
                        ->label('Total')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                        ->color(
                            fn (string $state): string => match ($state) {
                                'active'           => 'success',
                                'pending_setup'    => 'warning',
                                'awaiting_signers' => 'info',
                                'suspended'        => 'danger',
                                'archived'         => 'gray',
                                default            => 'gray',
                            }
                        )
                        ->sortable(),
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('Owner')
                        ->searchable()
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
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            [
                                'pending_setup'    => 'Pending Setup',
                                'awaiting_signers' => 'Awaiting Signers',
                                'active'           => 'Active',
                                'suspended'        => 'Suspended',
                                'archived'         => 'Archived',
                            ]
                        ),
                    Tables\Filters\SelectFilter::make('chain')
                        ->options(fn () => MultiSigWallet::query()->distinct()->pluck('chain', 'chain')->all()),
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
            'index' => Pages\ListMultiSigWallets::route('/'),
            'view'  => Pages\ViewMultiSigWallet::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
