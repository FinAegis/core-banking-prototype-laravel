<?php

namespace App\Filament\Resources\CgoPricingRoundResource\Pages;

use App\Filament\Resources\CgoPricingRoundResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCgoPricingRound extends ViewRecord
{
    protected static string $resource = CgoPricingRoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}