<?php

namespace App\Filament\Resources\CgoInvestmentResource\Pages;

use App\Filament\Resources\CgoInvestmentResource;
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

    protected function getHeaderWidgets(): array
    {
        return [
            CgoInvestmentResource\Widgets\CgoInvestmentStats::class,
        ];
    }
}