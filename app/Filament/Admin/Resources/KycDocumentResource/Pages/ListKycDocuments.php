<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\KycDocumentResource\Pages;

use App\Filament\Admin\Resources\KycDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListKycDocuments extends ListRecords
{
    protected static string $resource = KycDocumentResource::class;
}
