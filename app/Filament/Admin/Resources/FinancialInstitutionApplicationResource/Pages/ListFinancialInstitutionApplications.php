<?php

namespace App\Filament\Admin\Resources\FinancialInstitutionApplicationResource\Pages;

use App\Filament\Admin\Resources\FinancialInstitutionApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancialInstitutionApplications extends ListRecords
{
    protected static string $resource = FinancialInstitutionApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
