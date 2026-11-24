<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\OrderBookResource\Pages;

use App\Filament\Admin\Resources\OrderBookResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderBooks extends ListRecords
{
    protected static string $resource = OrderBookResource::class;
}
