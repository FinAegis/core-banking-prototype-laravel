<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserInvitationResource\Pages;

use App\Filament\Admin\Resources\UserInvitationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserInvitations extends ListRecords
{
    protected static string $resource = UserInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Invite User'),
        ];
    }
}
