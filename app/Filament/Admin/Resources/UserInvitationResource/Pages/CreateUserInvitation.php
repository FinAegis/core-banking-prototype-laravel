<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserInvitationResource\Pages;

use App\Domain\User\Services\UserInvitationService;
use App\Filament\Admin\Resources\UserInvitationResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUserInvitation extends CreateRecord
{
    protected static string $resource = UserInvitationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $service = app(UserInvitationService::class);
        $invitation = $service->invite(
            (string) $data['email'],
            (string) $data['role'],
            $user,
        );

        Notification::make()
            ->title('Invitation sent to ' . $data['email'])
            ->success()
            ->send();

        // Redirect back to list — the record is already created by the service
        $this->redirect(UserInvitationResource::getUrl('index'));

        // Return the data from the created invitation so Filament doesn't try to create again
        return [
            'email'      => $invitation->email,
            'token'      => $invitation->token,
            'role'       => $invitation->role,
            'invited_by' => $invitation->invited_by,
            'expires_at' => $invitation->expires_at,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return UserInvitationResource::getUrl('index');
    }
}
