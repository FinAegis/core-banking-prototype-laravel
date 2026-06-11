<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\RevenueOutboxEventResource\Pages;

use App\Filament\Admin\Resources\RevenueOutboxEventResource;
use Filament\Resources\Pages\ListRecords;

class ListRevenueOutboxEvents extends ListRecords
{
    protected static string $resource = RevenueOutboxEventResource::class;
}
