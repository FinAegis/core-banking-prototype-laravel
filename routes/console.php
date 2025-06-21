<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule monthly GCU voting poll creation
// Runs on the 20th of each month at midnight
Schedule::command('voting:setup')
    ->monthlyOn(20, '00:00')
    ->description('Create next month\'s GCU voting poll')
    ->appendOutputTo(storage_path('logs/gcu-voting-setup.log'));

// Schedule monthly basket rebalancing
// Runs on the 1st of each month at 00:05 (5 minutes after midnight)
Schedule::command('baskets:rebalance')
    ->monthlyOn(1, '00:05')
    ->description('Rebalance dynamic baskets including GCU')
    ->appendOutputTo(storage_path('logs/basket-rebalancing.log'));

// Schedule hourly basket value calculations for performance tracking
Schedule::call(function () {
    $service = app(\App\Domain\Basket\Services\BasketValueCalculationService::class);
    $service->calculateAllBasketValues();
})->hourly()
    ->description('Calculate and store basket values for performance tracking');

// Regulatory reporting
Schedule::command('compliance:generate-reports --type=ctr')
    ->dailyAt('01:00')
    ->description('Generate daily Currency Transaction Report')
    ->appendOutputTo(storage_path('logs/regulatory-ctr.log'));

Schedule::command('compliance:generate-reports --type=kyc')
    ->dailyAt('02:00')
    ->description('Generate daily KYC compliance report')
    ->appendOutputTo(storage_path('logs/regulatory-kyc.log'));

Schedule::command('compliance:generate-reports --type=sar')
    ->weeklyOn(1, '03:00') // Monday at 3 AM
    ->description('Generate weekly Suspicious Activity Report candidates')
    ->appendOutputTo(storage_path('logs/regulatory-sar.log'));

Schedule::command('compliance:generate-reports --type=summary')
    ->monthlyOn(1, '04:00') // 1st of month at 4 AM
    ->description('Generate monthly compliance summary')
    ->appendOutputTo(storage_path('logs/regulatory-summary.log'));

// Bank Health Monitoring
Schedule::command('banks:monitor-health --interval=60')
    ->everyFiveMinutes()
    ->description('Monitor bank connector health status')
    ->appendOutputTo(storage_path('logs/bank-health-monitor.log'))
    ->withoutOverlapping()
    ->onFailure(function () {
        // Alert operations team on monitoring failure
        \Log::critical('Bank health monitoring failed to run');
    });

// Custodian Balance Synchronization
Schedule::command('custodian:sync-balances')
    ->everyThirtyMinutes()
    ->description('Synchronize balances with external custodians')
    ->appendOutputTo(storage_path('logs/custodian-sync.log'))
    ->withoutOverlapping();

// Bank Health Alert Checks
Schedule::command('banks:check-alerts')
    ->everyTenMinutes()
    ->description('Check bank health and send alerts if necessary')
    ->appendOutputTo(storage_path('logs/bank-alerts.log'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::critical('Bank health alert check failed to run');
    });

// Daily Balance Reconciliation
Schedule::command('reconciliation:daily')
    ->dailyAt('02:00')
    ->description('Perform daily balance reconciliation')
    ->appendOutputTo(storage_path('logs/daily-reconciliation.log'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::critical('Daily reconciliation failed to run');
    });
