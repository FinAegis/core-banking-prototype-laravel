<?php

namespace App\Filament\Admin\Resources\GcuVotingProposalResource\Pages;

use App\Filament\Admin\Resources\GcuVotingProposalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGcuVotingProposal extends EditRecord
{
    protected static string $resource = GcuVotingProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}