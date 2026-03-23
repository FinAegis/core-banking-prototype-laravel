<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\TextInput::make('uuid')
                        ->label('UUID')
                        ->required(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\DateTimePicker::make('email_verified_at'),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('two_factor_secret')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('two_factor_recovery_codes')
                        ->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('two_factor_confirmed_at'),
                    Forms\Components\TextInput::make('current_team_id')
                        ->numeric()
                        ->default(null),
                    Forms\Components\TextInput::make('profile_photo_path')
                        ->maxLength(2048)
                        ->default(null),
                    Forms\Components\TextInput::make('stripe_id')
                        ->maxLength(255)
                        ->default(null),
                    Forms\Components\TextInput::make('pm_type')
                        ->maxLength(255)
                        ->default(null),
                    Forms\Components\TextInput::make('pm_last_four')
                        ->maxLength(4)
                        ->default(null),
                    Forms\Components\DateTimePicker::make('trial_ends_at'),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('uuid')
                        ->label('UUID'),
                    Tables\Columns\TextColumn::make('name')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('email')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('email_verified_at')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('two_factor_confirmed_at')
                        ->dateTime()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('current_team_id')
                        ->numeric()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('profile_photo_path')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('updated_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('stripe_id')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('pm_type')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('pm_last_four')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('trial_ends_at')
                        ->dateTime()
                        ->sortable(),
                ]
            )
            ->filters(
                [
                    //
                ]
            )
            ->actions(
                [
                    Tables\Actions\EditAction::make(),
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkActionGroup::make(
                        [
                            Tables\Actions\DeleteBulkAction::make(),
                        ]
                    ),
                ]
            );
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
