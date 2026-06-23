<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Services\BalanceSynchronizationService;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\Custodian\ValueObjects\AccountInfo;
use App\Domain\Wallet\Services\WalletService;

/*
 * Regression for the production failure:
 *   SQLSTATE[42S22] Unknown column 'sync_status' in 'WHERE'
 *
 * BalanceSynchronizationService writes sync_status / sync_error and the stats
 * query filters on sync_status, but no migration ever created those columns.
 *
 * NOTE: the exact "unknown column" error only reproduces on MySQL. SQLite
 * silently rewrites the unknown double-quoted identifier as a string literal
 * (`WHERE 'sync_status' = 'failed'`), so it never raised. The MySQL-faithful
 * reproduction lives in tests/MultiConnection/Custodian. This fast-suite test
 * still guards the regression: if the column/migration disappears, the write is
 * dropped and the count below collapses to 0.
 */
it('counts custodian accounts that failed to sync in the last hour', function () {
    $account = Account::factory()->create();

    CustodianAccount::factory()->create([
        'account_uuid'   => $account->uuid,
        'status'         => 'active',
        'sync_status'    => 'failed',
        'sync_error'     => 'Custodian not available',
        'last_synced_at' => now()->subMinutes(10),
    ]);

    $stats = app(BalanceSynchronizationService::class)->getSynchronizationStats();

    expect($stats['total_accounts'])->toBe(1);
    expect($stats['failed_last_hour'])->toBe(1);
});

/*
 * The per-account sync path read $custodianAccount->custodian_id and
 * ->external_account_id — neither is a real column (they are custodian_name and
 * custodian_account_id), so getConnector() received null and threw a TypeError
 * the moment any active account existed. It also looked the account up with
 * Account::findOrFail($account_uuid), but account_uuid maps to accounts.uuid,
 * not the bigint PK, so the lookup threw ModelNotFoundException. This asserts
 * the connector is asked with the real column values and the sync succeeds.
 */
it('syncs active accounts through the scheduled path using the real column values', function () {
    $account = Account::factory()->create();

    $custodian = CustodianAccount::factory()->create([
        'account_uuid'         => $account->uuid,
        'custodian_name'       => 'mock',
        'custodian_account_id' => 'ext-acct-123',
        'status'               => 'active',
        'last_synced_at'       => null,
    ]);

    $connector = Mockery::mock(ICustodianConnector::class);
    $connector->shouldReceive('isAvailable')->andReturnTrue();
    // Proves external_account_id -> custodian_account_id: the connector is asked
    // for the stored external id, not null.
    $connector->shouldReceive('getAccountInfo')->once()->with('ext-acct-123')
        ->andReturn(new AccountInfo(
            accountId: 'ext-acct-123',
            name: 'Mock Account',
            status: 'active',
            balances: [],
        ));

    $registry = Mockery::mock(CustodianRegistry::class);
    // Proves custodian_id -> custodian_name: getConnector receives 'mock'.
    $registry->shouldReceive('getConnector')->once()->with('mock')->andReturn($connector);

    $service = new BalanceSynchronizationService($registry, app(WalletService::class));

    // synchronizeAllBalances() is the scheduled (no-arg) entry point that was
    // failing in production. Before the column fixes it would TypeError on
    // getConnector(null) the moment an active account existed.
    $results = $service->synchronizeAllBalances();

    expect($results['synchronized'])->toBe(1);
    expect($results['failed'])->toBe(0);
    expect($custodian->fresh()->sync_status)->toBe('success');
});

/*
 * `custodian:sync-balances --custodian=X` (without --force) called
 * $query->needsSynchronization(), a scope that did not exist — every such
 * invocation threw BadMethodCallException. This asserts the scope exists and
 * returns only never-synced or stale (>5 min) accounts.
 */
it('needsSynchronization scope returns never-synced and stale accounts only', function () {
    $make = function ($lastSyncedAt) {
        $account = Account::factory()->create();

        return CustodianAccount::factory()->create([
            'account_uuid'   => $account->uuid,
            'status'         => 'active',
            'last_synced_at' => $lastSyncedAt,
        ]);
    };

    $never = $make(null);
    $stale = $make(now()->subMinutes(10));
    $fresh = $make(now()->subMinute());

    $ids = CustodianAccount::needsSynchronization()->pluck('id')->all();

    expect($ids)->toContain($never->id);
    expect($ids)->toContain($stale->id);
    expect($ids)->not->toContain($fresh->id);
});
