<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Commerce\Enums\MerchantStatus;
use App\Domain\Commerce\Models\Merchant;
use App\Filament\Admin\Resources\MerchantResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MerchantResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = Merchant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Merchant Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('public_id')
                                    ->label('Public ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('display_name')
                                    ->label('Display Name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('icon_url')
                                    ->label('Icon URL')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                                Forms\Components\TextInput::make('terminal_id')
                                    ->label('Terminal ID')
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
                    Tables\Columns\TextColumn::make('display_name')
                        ->label('Display Name')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('public_id')
                        ->label('Public ID')
                        ->limit(12)
                        ->tooltip(fn ($record): string => $record->public_id)
                        ->sortable(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(
                            fn ($state): string => match ($state->value) {
                                'pending'      => 'gray',
                                'under_review' => 'warning',
                                'approved'     => 'info',
                                'active'       => 'success',
                                'suspended'    => 'danger',
                                'terminated'   => 'gray',
                                default        => 'gray',
                            }
                        ),
                    Tables\Columns\TextColumn::make('terminal_id')
                        ->label('Terminal ID')
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
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            collect(MerchantStatus::cases())
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
            'index' => Pages\ListMerchants::route('/'),
            'view'  => Pages\ViewMerchant::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
