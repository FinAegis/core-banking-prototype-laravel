<?php

namespace App\Filament\Admin\Resources\GcuVotingProposalResource\Pages;

use App\Filament\Admin\Resources\GcuVotingProposalResource;
use App\Models\Account;
use Filament\Resources\Pages\CreateRecord;

class CreateGcuVotingProposal extends CreateRecord
{
    protected static string $resource = GcuVotingProposalResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['current_composition'] = config('platform.gcu.composition');
        
        // Calculate total GCU supply if status is active
        if ($data['status'] === 'active') {
            $data['total_gcu_supply'] = Account::where('currency', 'GCU')
                ->where('type', 'personal')
                ->sum('balance');
        }
        
        return $data;
    }
}