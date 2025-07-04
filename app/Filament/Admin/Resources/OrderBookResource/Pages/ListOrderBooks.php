<?php

namespace App\Filament\Admin\Resources\OrderBookResource\Pages;

use App\Filament\Admin\Resources\OrderBookResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrderBooks extends ListRecords
{
    protected static string $resource = OrderBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
