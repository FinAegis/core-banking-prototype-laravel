<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Custodian;

use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Services\BalanceSynchronizationService;
use Illuminate\Support\Str;

/*
 * MySQL-faithful regression for the production failure:
 *   SQLSTATE[42S22] Unknown column 'sync_status' in 'WHERE'
 *
 * BalanceSynchronizationService writes sync_status / sync_error and
 * getSynchronizationStats() filters on sync_status, but no migration created
 * those columns. MySQL rejects the query; SQLite silently rewrites the unknown
 * quoted identifier as a string literal and never raises — which is why the
 * fast suite missed it. These run on the real MySQL topology so a missing
 * column fails loudly.
 */

it('computes sync stats without an unknown-column error when no accounts exist', function () {
    // This is the literal production failure: the stats query runs on every
    // scheduled invocation, and its `where('sync_status', 'failed')` count threw
    // 42S22 even with zero custodian accounts.
    $stats = app(BalanceSynchronizationService::class)->getSynchronizationStats();

    expect($stats['total_accounts'])->toBe(0);
    expect($stats['failed_last_hour'])->toBe(0);
});

it('counts custodian accounts that failed to sync in the last hour', function () {
    $account = Account::factory()->create();

    CustodianAccount::create([
        'account_uuid'         => $account->uuid,
        'custodian_name'       => 'mock',
        'custodian_account_id' => (string) Str::uuid(),
        'status'               => 'active',
        'sync_status'          => 'failed',
        'sync_error'           => 'Custodian not available',
        'last_synced_at'       => now()->subMinutes(10),
    ]);

    $stats = app(BalanceSynchronizationService::class)->getSynchronizationStats();

    expect($stats['total_accounts'])->toBe(1);
    expect($stats['failed_last_hour'])->toBe(1);
});
