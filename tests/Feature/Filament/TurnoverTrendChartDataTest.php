<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Turnover;
use App\Filament\Admin\Resources\AccountResource\Widgets\TurnoverTrendChart;

// turnovers stores one row per account and date; there are no year or month
// columns. The chart selected and grouped by year/month: MySQL rejected the
// query and SQLite returned the literal strings, so every month showed zero.

it('sums debits and credits per month from the turnover dates', function (): void {
    $this->travelTo(now()->startOfMonth()->addDays(14));

    $account = Account::factory()->create();
    Turnover::factory()->create(['account_uuid' => $account->uuid, 'date' => now()->startOfMonth(), 'debit' => 10000, 'credit' => 25000]);
    Turnover::factory()->create(['account_uuid' => $account->uuid, 'date' => now()->startOfMonth()->addDay(), 'debit' => 5000, 'credit' => 0]);
    Turnover::factory()->create(['account_uuid' => $account->uuid, 'date' => now()->subMonthNoOverflow()->startOfMonth(), 'debit' => 100, 'credit' => 300]);

    $chart = new TurnoverTrendChart();
    $data = (new ReflectionMethod($chart, 'getTurnoverData'))->invoke($chart, '3m');
    $byMonth = $data->keyBy('month');

    expect($byMonth[now()->format('M Y')]['debit'])->toEqual(150.0)
        ->and($byMonth[now()->format('M Y')]['credit'])->toEqual(250.0)
        ->and($byMonth[now()->subMonthNoOverflow()->format('M Y')]['credit'])->toEqual(3.0);
});
