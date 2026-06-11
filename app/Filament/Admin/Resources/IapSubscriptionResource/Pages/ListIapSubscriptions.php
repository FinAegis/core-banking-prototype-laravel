<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\IapSubscriptionResource\Pages;

use App\Filament\Admin\Resources\IapSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListIapSubscriptions extends ListRecords
{
    protected static string $resource = IapSubscriptionResource::class;
}
