<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\AccountResource\Widgets\AccountStatsOverview;
use App\Filament\Admin\Resources\AccountResource\Widgets\RecentTransactionsChart;
use App\Filament\Admin\Resources\AccountResource\Widgets\AccountBalanceChart;
use App\Filament\Admin\Resources\AccountResource\Widgets\SystemHealthWidget;
use App\Filament\Admin\Widgets\PrimaryBasketWidget;
use App\Filament\Admin\Widgets\BankAllocationWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament.admin.pages.dashboard';
    
    public function getWidgets(): array
    {
        return [
            PrimaryBasketWidget::class,
            BankAllocationWidget::class,
            AccountStatsOverview::class,
            RecentTransactionsChart::class,
            AccountBalanceChart::class,
            SystemHealthWidget::class,
        ];
    }
    
    public function getColumns(): int|string|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }
}