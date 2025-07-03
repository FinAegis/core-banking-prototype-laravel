<?php

namespace App\Filament\Resources\CgoInvestmentResource\Pages;

use App\Filament\Resources\CgoInvestmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCgoInvestment extends CreateRecord
{
    protected static string $resource = CgoInvestmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}