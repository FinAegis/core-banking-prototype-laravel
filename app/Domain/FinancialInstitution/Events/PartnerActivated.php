<?php

namespace App\Domain\FinancialInstitution\Events;

use App\Models\FinancialInstitutionPartner;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartnerActivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public readonly FinancialInstitutionPartner $partner
    ) {}
}