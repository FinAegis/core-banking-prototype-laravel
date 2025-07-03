<?php

namespace App\Filament\Resources\CgoInvestmentResource\Pages;

use App\Filament\Resources\CgoInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCgoInvestment extends EditRecord
{
    protected static string $resource = CgoInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}