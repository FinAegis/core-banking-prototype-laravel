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
    $service = app(App\Domain\Basket\Services\BasketValueCalculationService::class);
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
        Log::critical('Bank health monitoring failed to run');
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
        Log::critical('Bank health alert check failed to run');
    });

// Daily Balance Reconciliation
Schedule::command('reconciliation:daily')
    ->dailyAt('02:00')
    ->description('Perform daily balance reconciliation')
    ->appendOutputTo(storage_path('logs/daily-reconciliation.log'))
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::critical('Daily reconciliation failed to run');
    });

// Basket Performance Calculation
Schedule::command('basket:calculate-performance')
    ->hourly()
    ->description('Calculate hourly performance metrics for all baskets')
    ->appendOutputTo(storage_path('logs/basket-performance.log'))
    ->withoutOverlapping();

// Daily basket performance summary
Schedule::command('basket:calculate-performance --period=day')
    ->dailyAt('00:30')
    ->description('Calculate daily performance metrics for all baskets')
    ->appendOutputTo(storage_path('logs/basket-performance-daily.log'));

// System Health Monitoring
// Run less frequently to avoid memory issues in CI
Schedule::command('system:health-check')
    ->everyFiveMinutes()
    ->description('Perform system health checks')
    ->appendOutputTo(storage_path('logs/system-health.log'))
    ->withoutOverlapping()
    ->onFailure(function () {
        // Use error level instead of critical to reduce memory usage
        error_log('System health check failed to run');
    })
    ->environments(['production', 'staging']);

// CGO Payment Verification
Schedule::command('cgo:verify-payments')
    ->everyThirtyMinutes()
    ->description('Verify pending CGO investment payments')
    ->appendOutputTo(storage_path('logs/cgo-payment-verification.log'))
    ->withoutOverlapping();

// CGO Expired Payment Handling
Schedule::command('cgo:verify-payments --expired')
    ->everyTwoHours()
    ->description('Handle expired CGO investment payments')
    ->appendOutputTo(storage_path('logs/cgo-expired-payments.log'))
    ->withoutOverlapping();

// Sitemap Generation
Schedule::command('sitemap:generate')
    ->daily()
    ->description('Generate sitemap.xml and robots.txt for SEO')
    ->appendOutputTo(storage_path('logs/sitemap-generation.log'));

// Liquidity Pool Management
Schedule::command('liquidity:distribute-rewards')
    ->hourly()
    ->description('Calculate and distribute rewards to liquidity providers')
    ->appendOutputTo(storage_path('logs/liquidity-rewards.log'))
    ->withoutOverlapping();

Schedule::command('liquidity:rebalance')
    ->everyThirtyMinutes()
    ->description('Check and rebalance liquidity pools')
    ->appendOutputTo(storage_path('logs/liquidity-rebalancing.log'))
    ->withoutOverlapping();

Schedule::command('liquidity:update-market-making --cancel-existing')
    ->everyFiveMinutes()
    ->description('Update automated market making orders')
    ->appendOutputTo(storage_path('logs/liquidity-market-making.log'))
    ->withoutOverlapping();

// Demo Data Cleanup (only runs in demo environment)
if (app()->environment('demo')) {
    Schedule::command('demo:cleanup --days=' . config('demo.cleanup.retention_days', 1))
        ->dailyAt(config('demo.cleanup.time', '03:00'))
        ->description('Clean up old demo data')
        ->appendOutputTo(storage_path('logs/demo-cleanup.log'))
        ->withoutOverlapping();
}
