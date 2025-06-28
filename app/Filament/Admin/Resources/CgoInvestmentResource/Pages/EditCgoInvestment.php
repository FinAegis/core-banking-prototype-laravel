<?php

namespace App\Filament\Admin\Resources\CgoInvestmentResource\Pages;

use App\Filament\Admin\Resources\CgoInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCgoInvestment extends EditRecord
{
    protected static string $resource = CgoInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
