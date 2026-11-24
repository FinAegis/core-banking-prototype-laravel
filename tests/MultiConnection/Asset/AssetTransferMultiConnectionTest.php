<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Asset;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Aggregates\AssetTransactionAggregate;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Asset\Events\AssetTransactionCreated;
use App\Domain\Asset\Events\AssetTransferCompleted;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Projectors\AssetTransferProjector;
use App\Domain\Asset\Services\AssetTransferService;
use App\Domain\Shared\EventSourcing\AppliedStoredEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\EventSourcing\Projectionist;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

// The asset transfer projector debits, credits and writes both history rows in
// one transaction on the balances' (tenant) connection, while the stored events
// go to the default connection. On SQLite both are one database, which hides
// connection mix-ups, missing NOT NULL values (transaction_projections.hash) and
// non-UUID aggregate ids. This runs the same completion against real MySQL with
// separate sessions.
beforeEach(function (): void {
    Asset::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
});

function mcBalance(Account $account, string $asset): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', $asset)->value('balance');
}

/** @param class-string $eventClass */
function mcStoredEvent(string $aggregateUuid, string $eventClass): StoredEvent
{
    $storedEvent = app(StoredEventRepository::class)
        ->retrieveAll($aggregateUuid)
        ->first(fn (StoredEvent $storedEvent): bool => $storedEvent->event instanceof $eventClass);

    expect($storedEvent)->toBeInstanceOf(StoredEvent::class);

    return $storedEvent;
}

it('books a same-account conversion with balances and history on the tenant connection', function (): void {
    $account = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    AccountBalance::query()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => 10_000]);
    $transferId = (string) Str::uuid();
    $uuid = new AccountUuid($account->uuid);

    AssetTransferAggregate::retrieve($transferId)
        ->initiate($uuid, $uuid, 'USD', 'EUR', new Money(10_000), new Money(2_900), 0.29, 'Wallet currency conversion')
        ->complete($transferId)
        ->persist();

    expect(mcBalance($account, 'USD'))->toBe(0)
        ->and(mcBalance($account, 'EUR'))->toBe(2_900)
        ->and(TransactionProjection::query()->where('account_uuid', $account->uuid)->where('reference', $transferId)->count())->toBe(2);
});

it('moves both balances for a service transfer under real multi-session topology', function (): void {
    $from = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    $to = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    AccountBalance::query()->create(['account_uuid' => $from->uuid, 'asset_code' => 'USD', 'balance' => 5_000]);

    app(AssetTransferService::class)->transfer((string) $from->uuid, (string) $to->uuid, 'USD', '25.00');

    expect(mcBalance($from, 'USD'))->toBe(2_500)
        ->and(mcBalance($to, 'USD'))->toBe(2_500);
});

// A replay or a second delivery of the same stored event must not move money
// again. The claim in applied_stored_events is written in the same tenant
// transaction as the balances, so this runs it with separate MySQL sessions.
it('books a transfer once when its completion is delivered again and replayed', function (): void {
    $from = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    $to = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    AccountBalance::query()->create(['account_uuid' => $from->uuid, 'asset_code' => 'USD', 'balance' => 10_000]);
    $transferId = (string) Str::uuid();

    AssetTransferAggregate::retrieve($transferId)
        ->initiate(new AccountUuid($from->uuid), new AccountUuid($to->uuid), 'USD', 'USD', new Money(2_500), new Money(2_500))
        ->complete($transferId)
        ->persist();

    $completed = mcStoredEvent($transferId, AssetTransferCompleted::class);
    app(Projectionist::class)->handleWithSyncEventHandlers($completed);
    app(Projectionist::class)->replay(collect([app(AssetTransferProjector::class)]), aggregateUuid: $transferId);

    expect(mcBalance($from, 'USD'))->toBe(7_500)
        ->and(mcBalance($to, 'USD'))->toBe(2_500)
        ->and(TransactionProjection::query()->where('reference', $transferId)->count())->toBe(2)
        ->and(DB::connection('tenant')->table(AppliedStoredEvents::TABLE)->where('stored_event_id', (string) $completed->id)->count())->toBe(1);
});

it('credits an asset deposit and writes its history once when it is delivered again', function (): void {
    $account = Account::factory()->create(['uuid' => (string) Str::uuid()]);
    $aggregateUuid = (string) Str::uuid();

    AssetTransactionAggregate::retrieve($aggregateUuid)
        ->credit(new AccountUuid($account->uuid), 'USD', new Money(1_000))
        ->persist();

    $created = mcStoredEvent($aggregateUuid, AssetTransactionCreated::class);
    app(Projectionist::class)->handleWithSyncEventHandlers($created);
    app(Projectionist::class)->handleWithSyncEventHandlers($created);

    expect(mcBalance($account, 'USD'))->toBe(1_000)
        ->and(TransactionProjection::query()->where('account_uuid', $account->uuid)->count())->toBe(1);
});
