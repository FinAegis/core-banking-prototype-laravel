<?php

namespace App\Filament\Resources\CgoInvestmentResource\Pages;

use App\Filament\Resources\CgoInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCgoInvestment extends ViewRecord
{
    protected static string $resource = CgoInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}