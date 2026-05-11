<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CueResource\Pages;

use App\Filament\Admin\Resources\CueResource;
use Filament\Resources\Pages\ListRecords;

class ListCues extends ListRecords
{
    protected static string $resource = CueResource::class;
}
