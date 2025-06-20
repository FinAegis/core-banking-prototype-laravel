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
