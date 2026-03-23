<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\VirtualsAgentResource\Pages;

use App\Filament\Admin\Resources\VirtualsAgentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVirtualsAgent extends EditRecord
{
    protected static string $resource = VirtualsAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
