<?php

namespace App\Filament\Resources\CgoPricingRoundResource\Pages;

use App\Filament\Resources\CgoPricingRoundResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCgoPricingRounds extends ListRecords
{
    protected static string $resource = CgoPricingRoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}