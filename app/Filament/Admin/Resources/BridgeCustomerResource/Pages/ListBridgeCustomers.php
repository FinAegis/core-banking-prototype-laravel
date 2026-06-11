<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BridgeCustomerResource\Pages;

use App\Filament\Admin\Resources\BridgeCustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListBridgeCustomers extends ListRecords
{
    protected static string $resource = BridgeCustomerResource::class;
}
