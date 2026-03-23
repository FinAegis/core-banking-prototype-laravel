<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Cgo\Models\CgoNotification;
use App\Filament\Admin\Resources\CgoNotificationResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CgoNotificationResource extends Resource
{
    use \App\Filament\Admin\Traits\RespectsModuleVisibility;
    protected static ?string $model = CgoNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'CGO Early Access';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(
                [
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('ip_address')
                        ->maxLength(45)
                        ->disabled(),
                    Forms\Components\Textarea::make('user_agent')
                        ->rows(3)
                        ->disabled(),
                ]
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(
                [
                    Tables\Columns\TextColumn::make('email')
                        ->searchable()
                        ->sortable()
                        ->copyable()
                        ->icon('heroicon-m-envelope'),
                    Tables\Columns\TextColumn::make('ip_address')
                        ->label('IP Address')
                        ->toggleable(),
                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Signed Up')
                        ->dateTime()
                        ->sortable()
                        ->since(),
                ]
            )
            ->defaultSort('created_at', 'desc')
            ->filters(
                [
                    //
                ]
            )
            ->actions(
                [
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]
            )
            ->bulkActions(
                [
                    Tables\Actions\BulkActionGroup::make(
                        [
                            Tables\Actions\DeleteBulkAction::make(),
                            Tables\Actions\ExportBulkAction::make(),
                        ]
                    ),
                ]
            );
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCgoNotifications::route('/'),
        ];
    }
}
