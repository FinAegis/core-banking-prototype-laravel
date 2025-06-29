<?php

namespace App\Domain\FinancialInstitution\Events;

use App\Models\FinancialInstitutionApplication;
use App\Models\FinancialInstitutionPartner;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public function __construct(
        public readonly FinancialInstitutionApplication $application,
        public readonly FinancialInstitutionPartner $partner
    ) {}
}