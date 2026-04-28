<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\McpToolInvocationResource\Pages;

use App\Filament\Admin\Resources\McpToolInvocationResource;
use Filament\Resources\Pages\ListRecords;

final class ListMcpToolInvocations extends ListRecords
{
    protected static string $resource = McpToolInvocationResource::class;

    /**
     * Read-only audit log — no header actions (no Create button).
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
