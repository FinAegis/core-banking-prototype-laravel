<?php

namespace App\Filament\Resources\CgoPricingRoundResource\Pages;

use App\Filament\Resources\CgoPricingRoundResource;
use App\Models\CgoPricingRound;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCgoPricingRound extends EditRecord
{
    protected static string $resource = CgoPricingRoundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If this round is set as active, deactivate all other rounds
        if ($data['is_active'] ?? false) {
            CgoPricingRound::where('id', '!=', $this->record->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}