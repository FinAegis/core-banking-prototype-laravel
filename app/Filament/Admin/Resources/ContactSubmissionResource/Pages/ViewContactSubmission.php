<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContactSubmissionResource\Pages;

use App\Filament\Admin\Resources\ContactSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContactSubmission extends ViewRecord
{
    protected static string $resource = ContactSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
