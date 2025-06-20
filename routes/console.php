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
