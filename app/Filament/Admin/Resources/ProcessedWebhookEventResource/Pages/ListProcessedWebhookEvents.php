<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProcessedWebhookEventResource\Pages;

use App\Filament\Admin\Resources\ProcessedWebhookEventResource;
use Filament\Resources\Pages\ListRecords;

class ListProcessedWebhookEvents extends ListRecords
{
    protected static string $resource = ProcessedWebhookEventResource::class;
}
