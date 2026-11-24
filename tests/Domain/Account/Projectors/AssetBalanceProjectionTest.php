<?php

declare(strict_types=1);

use App\Domain\Account\Aggregates\AssetTransactionAggregate;
use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Repositories\TransactionRepository;
use App\Domain\Asset\Models\Asset;
use Illuminate\Support\Str;
use Spatie\EventSourcing\Projectionist;
use Spatie\EventSourcing\StoredEvents\HandleStoredEventJob;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

/*
 * AssetBalanceAdded and AssetBalanceSubtracted were projected by two queued
 * projectors, AccountProjector and AssetBalanceProjector, which both changed
 * the same account_balances row, so one credit of 1000 landed as 2000.
 * AccountProjector now owns these balances. It applies each stored event once
 * and locks the row while it changes it.
 */

beforeEach(function (): void {
    Asset::query()->firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
});

function balanceProjectionAccount(): Account
{
    return Account::factory()->create(['uuid' => (string) Str::uuid(), 'frozen' => false]);
}

function balanceProjectionUsd(Account $account): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance');
}

it('credits 1000 once for one credit of 1000', function (): void {
    $account = balanceProjectionAccount();

    AssetTransactionAggregate::retrieve($account->uuid)->credit('USD', 1_000)->persist();

    expect(balanceProjectionUsd($account))->toBe(1_000);
});

it('debits from the credited balance', function (): void {
    $account = balanceProjectionAccount();

    AssetTransactionAggregate::retrieve($account->uuid)->credit('USD', 1_000)->debit('USD', 300)->persist();

    expect(balanceProjectionUsd($account))->toBe(700);
});

it('changes nothing when the queued handler runs a credit again', function (): void {
    $account = balanceProjectionAccount();
    AssetTransactionAggregate::retrieve($account->uuid)->credit('USD', 1_000)->persist();

    /** @var StoredEvent $storedEvent */
    $storedEvent = app(TransactionRepository::class)
        ->retrieveAll($account->uuid)
        ->first(fn (StoredEvent $storedEvent): bool => $storedEvent->event instanceof AssetBalanceAdded);
    HandleStoredEventJob::createForEvent($storedEvent, [])->handle(app(Projectionist::class));

    expect(balanceProjectionUsd($account))->toBe(1_000);
});

it('refuses a debit above the projected balance as before', function (): void {
    $account = balanceProjectionAccount();
    AssetTransactionAggregate::retrieve($account->uuid)->credit('USD', 1_000)->persist();
    AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->update(['balance' => 100]);

    expect(fn () => AssetTransactionAggregate::retrieve($account->uuid)->debit('USD', 500)->persist())
        ->toThrow(Exception::class, 'Insufficient balance for USD');

    expect(balanceProjectionUsd($account))->toBe(100);
});
