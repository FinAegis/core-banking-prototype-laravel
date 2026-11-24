<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CgoInvestmentResource\Pages;

use App\Filament\Admin\Resources\CgoInvestmentResource;
use Filament\Resources\Pages\ListRecords;

class ListCgoInvestments extends ListRecords
{
    protected static string $resource = CgoInvestmentResource::class;
}
