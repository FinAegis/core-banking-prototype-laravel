<?php

namespace App\Filament\Admin\Resources\KycDocumentResource\Pages;

use App\Filament\Admin\Resources\KycDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKycDocument extends EditRecord
{
    protected static string $resource = KycDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
