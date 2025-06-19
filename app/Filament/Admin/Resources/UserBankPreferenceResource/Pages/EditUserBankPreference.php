<?php

namespace App\Filament\Admin\Resources\UserBankPreferenceResource\Pages;

use App\Filament\Admin\Resources\UserBankPreferenceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserBankPreference extends EditRecord
{
    protected static string $resource = UserBankPreferenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
