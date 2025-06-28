<?php

namespace App\Filament\Admin\Resources\CgoInvestmentResource\Pages;

use App\Filament\Admin\Resources\CgoInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCgoInvestments extends ListRecords
{
    protected static string $resource = CgoInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
