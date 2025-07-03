<?php

namespace App\Filament\Resources\CgoInvestmentResource\Widgets;

use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class CgoInvestmentStats extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRaised = CgoInvestment::where('status', 'confirmed')->sum('amount');
        $pendingInvestments = CgoInvestment::where('status', 'pending')->count();
        $totalInvestors = CgoInvestment::where('status', 'confirmed')->distinct('user_id')->count('user_id');
        $activeRound = CgoPricingRound::active()->first();
        
        return [
            Stat::make('Total Raised', '$' . Number::abbreviate($totalRaised, 2))
                ->description('All-time CGO investments')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            
            Stat::make('Active Investors', $totalInvestors)
                ->description('Unique confirmed investors')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            
            Stat::make('Pending Investments', $pendingInvestments)
                ->description('Awaiting payment confirmation')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('Current Round', $activeRound ? "Round {$activeRound->round_number}" : 'No Active Round')
                ->description($activeRound ? 
                    Number::percentage($activeRound->progress_percentage, 1) . ' sold' : 
                    'Create a new pricing round'
                )
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($activeRound ? 'info' : 'danger'),
        ];
    }
}