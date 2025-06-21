<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Custodian\Services\CustodianHealthMonitor;
use App\Domain\Custodian\Services\DailyReconciliationService;
use App\Models\CustodianAccount;
use App\Models\Transfer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BankOperationsDashboard extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        $healthMonitor = app(CustodianHealthMonitor::class);
        $reconciliationService = app(DailyReconciliationService::class);
        
        // Get health status
        $allHealth = $healthMonitor->getAllCustodiansHealth();
        $healthyCount = collect($allHealth)->where('status', 'healthy')->count();
        $totalBanks = count($allHealth);
        
        // Get latest reconciliation report
        $latestReport = $reconciliationService->getLatestReport();
        $discrepancies = $latestReport['summary']['discrepancies_found'] ?? 0;
        
        // Get transfer statistics
        $todayTransfers = Transfer::whereDate('created_at', today())->count();
        $todayVolume = Transfer::whereDate('created_at', today())->sum('amount');
        
        // Get custodian account statistics
        $activeCustodianAccounts = CustodianAccount::where('status', 'active')->count();
        $recentlySynced = CustodianAccount::where('status', 'active')
            ->where('last_synced_at', '>=', now()->subHour())
            ->count();
        
        return [
            Stat::make('Bank Health', "{$healthyCount}/{$totalBanks} Healthy")
                ->description('Active bank connectors')
                ->descriptionIcon($healthyCount === $totalBanks ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($healthyCount === $totalBanks ? 'success' : 'warning')
                ->chart($this->getBankHealthChart()),
                
            Stat::make('Reconciliation Status', $discrepancies === 0 ? 'All Clear' : "{$discrepancies} Issues")
                ->description($latestReport ? "Last check: {$latestReport['summary']['date']}" : 'No reports')
                ->descriptionIcon($discrepancies === 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle')
                ->color($discrepancies === 0 ? 'success' : 'danger'),
                
            Stat::make('Today\'s Transfers', number_format($todayTransfers))
                ->description('$' . number_format($todayVolume / 100, 2) . ' total volume')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($this->getTransferVolumeChart())
                ->color('primary'),
                
            Stat::make('Account Sync', "{$recentlySynced}/{$activeCustodianAccounts}")
                ->description('Synced in last hour')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($recentlySynced === $activeCustodianAccounts ? 'success' : 'warning'),
        ];
    }
    
    protected function getBankHealthChart(): array
    {
        // Generate sample health data for the last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            // Simulate health scores (0-100)
            $data[] = rand(85, 100);
        }
        
        return $data;
    }
    
    protected function getTransferVolumeChart(): array
    {
        // Get transfer volume for the last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $volume = Transfer::whereDate('created_at', today()->subDays($i))
                ->sum('amount');
            $data[] = round($volume / 100000); // Convert to thousands
        }
        
        return $data;
    }
}