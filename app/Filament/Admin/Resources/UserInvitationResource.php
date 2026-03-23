<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources;

use App\Domain\User\Models\UserInvitation;
use App\Domain\User\Services\UserInvitationService;
use App\Filament\Admin\Resources\UserInvitationResource\Pages;
use App\Filament\Admin\Traits\RespectsModuleVisibility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserInvitationResource extends Resource
{
    use RespectsModuleVisibility;

    protected static ?string $model = UserInvitation::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Invitations';

    protected static ?string $modelLabel = 'Invitation';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invite a New User')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique('user_invitations', 'email', ignoreRecord: true)
                            ->unique('users', 'email')
                            ->maxLength(255),
                        Forms\Components\Select::make('role')
                            ->options([
                                'private'     => 'User (Private)',
                                'admin'       => 'Admin',
                                'super_admin' => 'Super Admin',
                            ])
                            ->default('private')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin', 'super_admin' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('inviter.name')
                    ->label('Invited By'),
                Tables\Columns\TextColumn::make('status')
                    ->getStateUsing(function (UserInvitation $record): string {
                        if ($record->isAccepted()) {
                            return 'Accepted';
                        }
                        if ($record->isExpired()) {
                            return 'Expired';
                        }

                        return 'Pending';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Accepted' => 'success',
                        'Expired'  => 'danger',
                        default    => 'info',
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'private'     => 'User',
                        'admin'       => 'Admin',
                        'super_admin' => 'Super Admin',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (UserInvitation $record): bool => $record->isPending() || $record->isExpired())
                    ->action(function (UserInvitation $record): void {
                        $inviter = auth()->user();
                        app(UserInvitationService::class)->resend($record->id, $inviter);
                        Notification::make()->title('Invitation resent')->success()->send();
                    }),
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (UserInvitation $record): bool => $record->isPending())
                    ->action(function (UserInvitation $record): void {
                        app(UserInvitationService::class)->revoke($record->id);
                        Notification::make()->title('Invitation revoked')->success()->send();
                    }),
                Tables\Actions\Action::make('copyLink')
                    ->label('Copy Link')
                    ->icon('heroicon-o-clipboard')
                    ->visible(fn (UserInvitation $record): bool => $record->isPending())
                    ->action(function (UserInvitation $record): void {
                        $url = config('app.url') . '/invitation/accept?token=' . $record->token;
                        Notification::make()
                            ->title('Invitation link')
                            ->body($url)
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUserInvitations::route('/'),
            'create' => Pages\CreateUserInvitation::route('/create'),
        ];
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
