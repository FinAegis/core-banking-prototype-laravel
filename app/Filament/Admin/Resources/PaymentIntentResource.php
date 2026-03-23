<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\MobilePayment\Enums\PaymentIntentStatus;
use App\Domain\MobilePayment\Models\PaymentIntent;
use App\Filament\Admin\Resources\PaymentIntentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentIntentResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = PaymentIntent::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Mobile Payments';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Payment Details')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('public_id')
                                    ->label('Public ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('asset')
                                    ->disabled(),
                                Forms\Components\TextInput::make('network')
                                    ->disabled(),
                                Forms\Components\TextInput::make('amount')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->disabled(),
                                Forms\Components\Toggle::make('shield_enabled')
                                    ->label('Shield Enabled')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Timing')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('expires_at')
                                    ->label('Expires At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('submitted_at')
                                    ->label('Submitted At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('confirmed_at')
                                    ->label('Confirmed At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('failed_at')
                                    ->label('Failed At')
                                    ->disabled(),
                                Forms\Components\TextInput::make('cancelled_at')
                                    ->label('Cancelled At')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('User & Merchant')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('user_id')
                                    ->label('User ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('merchant_id')
                                    ->label('Merchant ID')
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
                    Tables\Columns\TextColumn::make('public_id')
                        ->label('Public ID')
                        ->searchable()
                        ->limit(12)
                        ->tooltip(fn ($record): string => $record->public_id),
                    Tables\Columns\TextColumn::make('asset'),
                    Tables\Columns\TextColumn::make('network'),
                    Tables\Columns\TextColumn::make('amount')
                        ->numeric(),
                    Tables\Columns\TextColumn::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(
                            fn ($state): string => match ($state->value) {
                                'created'       => 'gray',
                                'awaiting_auth' => 'warning',
                                'submitting'    => 'info',
                                'pending'       => 'info',
                                'confirmed'     => 'success',
                                'failed'        => 'danger',
                                'cancelled'     => 'gray',
                                'expired'       => 'gray',
                                default         => 'gray',
                            }
                        ),
                    Tables\Columns\IconColumn::make('shield_enabled')
                        ->label('Shield')
                        ->boolean(),
                    Tables\Columns\TextColumn::make('expires_at')
                        ->label('Expires')
                        ->dateTime(),
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('User')
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime()
                        ->sortable(),
                ]
            )
            ->defaultSort('created_at', 'desc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('status')
                        ->options(
                            collect(PaymentIntentStatus::cases())
                                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                ->all()
                        ),
                    Tables\Filters\SelectFilter::make('asset')
                        ->options(
                            fn (): array => PaymentIntent::query()
                                ->distinct()
                                ->pluck('asset', 'asset')
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
            'index' => Pages\ListPaymentIntents::route('/'),
            'view'  => Pages\ViewPaymentIntent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
