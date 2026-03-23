<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\Mobile\Models\MobileDevice;
use App\Filament\Admin\Resources\MobileDeviceResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MobileDeviceResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = MobileDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Mobile';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\Section::make('Device Info')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('device_id')
                                    ->label('Device ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('device_name')
                                    ->label('Device Name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('device_model')
                                    ->label('Device Model')
                                    ->disabled(),
                                Forms\Components\TextInput::make('platform')
                                    ->disabled(),
                                Forms\Components\TextInput::make('os_version')
                                    ->label('OS Version')
                                    ->disabled(),
                                Forms\Components\TextInput::make('app_version')
                                    ->label('App Version')
                                    ->disabled(),
                            ]
                        )->columns(3),

                    Forms\Components\Section::make('Security')
                        ->schema(
                            [
                                Forms\Components\Toggle::make('biometric_enabled')
                                    ->label('Biometric Enabled')
                                    ->disabled(),
                                Forms\Components\Toggle::make('passkey_enabled')
                                    ->label('Passkey Enabled')
                                    ->disabled(),
                                Forms\Components\Toggle::make('is_trusted')
                                    ->label('Trusted')
                                    ->disabled(),
                                Forms\Components\Toggle::make('is_blocked')
                                    ->label('Blocked')
                                    ->disabled(),
                                Forms\Components\TextInput::make('blocked_reason')
                                    ->label('Blocked Reason')
                                    ->disabled()
                                    ->columnSpanFull(),
                            ]
                        )->columns(4),

                    Forms\Components\Section::make('Activity')
                        ->schema(
                            [
                                Forms\Components\TextInput::make('last_active_at')
                                    ->label('Last Active At')
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
                    Tables\Columns\TextColumn::make('device_name')
                        ->label('Device Name')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('platform')
                        ->badge(),
                    Tables\Columns\TextColumn::make('device_model')
                        ->label('Model'),
                    Tables\Columns\TextColumn::make('os_version')
                        ->label('OS'),
                    Tables\Columns\IconColumn::make('biometric_enabled')
                        ->label('Bio')
                        ->boolean(),
                    Tables\Columns\IconColumn::make('passkey_enabled')
                        ->label('Passkey')
                        ->boolean(),
                    Tables\Columns\IconColumn::make('is_trusted')
                        ->label('Trusted')
                        ->boolean(),
                    Tables\Columns\IconColumn::make('is_blocked')
                        ->label('Blocked')
                        ->boolean()
                        ->trueColor('danger'),
                    Tables\Columns\TextColumn::make('last_active_at')
                        ->label('Last Active')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('user.name')
                        ->label('User')
                        ->toggleable(),
                ]
            )
            ->defaultSort('last_active_at', 'desc')
            ->filters(
                [
                    Tables\Filters\SelectFilter::make('platform')
                        ->options(
                            fn (): array => MobileDevice::query()
                                ->distinct()
                                ->whereNotNull('platform')
                                ->pluck('platform', 'platform')
                                ->all()
                        ),
                    Tables\Filters\TernaryFilter::make('biometric_enabled')
                        ->label('Biometric'),
                    Tables\Filters\TernaryFilter::make('is_blocked')
                        ->label('Blocked'),
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
            'index' => Pages\ListMobileDevices::route('/'),
            'view'  => Pages\ViewMobileDevice::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
