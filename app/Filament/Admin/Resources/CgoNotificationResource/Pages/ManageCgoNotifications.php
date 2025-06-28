<?php

namespace App\Filament\Admin\Resources\CgoNotificationResource\Pages;

use App\Filament\Admin\Resources\CgoNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCgoNotifications extends ManageRecords
{
    protected static string $resource = CgoNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
