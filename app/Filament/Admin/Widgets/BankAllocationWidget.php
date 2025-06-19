<?php

namespace App\Filament\Admin\Widgets;

use App\Models\UserBankPreference;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;

class BankAllocationWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.bank-allocation-widget';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2;
    
    public function getBankDistribution(): array
    {
        $banks = UserBankPreference::AVAILABLE_BANKS;
        $distribution = [];
        
        // Count active allocations per bank
        $allocations = UserBankPreference::query()
            ->where('status', 'active')
            ->selectRaw('bank_code, COUNT(*) as user_count, AVG(allocation_percentage) as avg_allocation')
            ->groupBy('bank_code')
            ->get();
            
        foreach ($allocations as $allocation) {
            $bank = $banks[$allocation->bank_code] ?? null;
            if (!$bank) continue;
            
            $distribution[] = [
                'bank_name' => $bank['name'],
                'country' => $bank['country'],
                'type' => ucfirst($bank['type']),
                'user_count' => $allocation->user_count,
                'avg_allocation' => Number::percentage($allocation->avg_allocation, 1),
                'deposit_insurance' => Number::currency($bank['deposit_insurance'], 'EUR'),
                'features' => implode(', ', array_map(fn($f) => ucwords(str_replace('_', ' ', $f)), $bank['features'] ?? [])),
            ];
        }
        
        // Add banks with no allocations
        foreach ($banks as $code => $bank) {
            if (!$allocations->contains('bank_code', $code)) {
                $distribution[] = [
                    'bank_name' => $bank['name'],
                    'country' => $bank['country'],
                    'type' => ucfirst($bank['type']),
                    'user_count' => 0,
                    'avg_allocation' => '0%',
                    'deposit_insurance' => Number::currency($bank['deposit_insurance'], 'EUR'),
                    'features' => implode(', ', array_map(fn($f) => ucwords(str_replace('_', ' ', $f)), $bank['features'] ?? [])),
                ];
            }
        }
        
        return $distribution;
    }
    
    public function getTotalInsuranceCoverage(): string
    {
        $maxCoverage = count(UserBankPreference::AVAILABLE_BANKS) * 100000;
        return Number::currency($maxCoverage, 'EUR');
    }
}