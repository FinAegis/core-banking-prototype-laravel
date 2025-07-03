<?php

namespace App\Filament\Resources\CgoRefundResource\Pages;

use App\Filament\Resources\CgoRefundResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCgoRefund extends ViewRecord
{
    protected static string $resource = CgoRefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions are defined in the table
        ];
    }
}