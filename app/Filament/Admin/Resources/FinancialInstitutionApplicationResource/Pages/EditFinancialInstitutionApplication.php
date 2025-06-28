<?php

namespace App\Filament\Admin\Resources\FinancialInstitutionApplicationResource\Pages;

use App\Filament\Admin\Resources\FinancialInstitutionApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFinancialInstitutionApplication extends EditRecord
{
    protected static string $resource = FinancialInstitutionApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
