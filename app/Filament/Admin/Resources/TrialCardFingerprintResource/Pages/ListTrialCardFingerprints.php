<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrialCardFingerprintResource\Pages;

use App\Filament\Admin\Resources\TrialCardFingerprintResource;
use Filament\Resources\Pages\ListRecords;

class ListTrialCardFingerprints extends ListRecords
{
    protected static string $resource = TrialCardFingerprintResource::class;
}
