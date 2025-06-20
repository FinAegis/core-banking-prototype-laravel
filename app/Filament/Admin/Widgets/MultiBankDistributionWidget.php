<?php

namespace App\Filament\Admin\Widgets;

use App\Models\UserBankPreference;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class MultiBankDistributionWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.multi-bank-distribution-widget';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 3;
    
    /**
     * Check if this widget should be displayed
     */
    public static function canView(): bool
    {
        // Show this widget when GCU is enabled or when there are bank preferences
        return config('app.gcu_enabled', false) || UserBankPreference::exists();
    }
    
    /**
     * Get the data for the widget
     */
    protected function getViewData(): array
    {
        $bankStats = $this->getBankDistributionStats();
        $totalAllocated = $this->getTotalAllocatedFunds();
        
        return [
            'bankStats' => $bankStats,
            'totalAllocated' => $totalAllocated,
            'isGcuEnabled' => config('app.gcu_enabled', false),
        ];
    }
    
    /**
     * Get bank distribution statistics
     */
    private function getBankDistributionStats(): array
    {
        $stats = UserBankPreference::select('bank_name')
            ->selectRaw('COUNT(DISTINCT user_uuid) as user_count')
            ->selectRaw('AVG(allocation_percentage) as avg_allocation')
            ->selectRaw('SUM(CASE WHEN is_primary = true THEN 1 ELSE 0 END) as primary_count')
            ->groupBy('bank_name')
            ->get();
        
        // Add bank metadata
        $bankMetadata = [
            'Paysera' => [
                'color' => 'blue',
                'country' => 'LT',
                'coverage' => '€100,000',
            ],
            'Deutsche Bank' => [
                'color' => 'gray',
                'country' => 'DE',
                'coverage' => '€100,000',
            ],
            'Santander' => [
                'color' => 'red',
                'country' => 'ES',
                'coverage' => '€100,000',
            ],
            'Revolut' => [
                'color' => 'purple',
                'country' => 'LT',
                'coverage' => '€100,000',
            ],
            'Wise' => [
                'color' => 'green',
                'country' => 'BE',
                'coverage' => '€100,000',
            ],
        ];
        
        return $stats->map(function ($stat) use ($bankMetadata) {
            $metadata = $bankMetadata[$stat->bank_name] ?? [
                'color' => 'gray',
                'country' => 'EU',
                'coverage' => '€100,000',
            ];
            
            return array_merge($stat->toArray(), $metadata);
        })->toArray();
    }
    
    /**
     * Get total allocated funds across all banks
     */
    private function getTotalAllocatedFunds(): array
    {
        // This is a placeholder - in a real implementation, you'd sum actual account balances
        $totalUsers = UserBankPreference::distinct('user_uuid')->count('user_uuid');
        $totalBankRelationships = UserBankPreference::count();
        
        return [
            'users' => $totalUsers,
            'relationships' => $totalBankRelationships,
            'averagePerUser' => $totalUsers > 0 ? round($totalBankRelationships / $totalUsers, 1) : 0,
        ];
    }
}