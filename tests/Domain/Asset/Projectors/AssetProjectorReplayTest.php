<?php

declare(strict_types=1);

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
use App\Domain\Asset\Projectors\AssetTransactionProjector;
use App\Domain\Asset\Projectors\AssetTransferProjector;
use Illuminate\Support\Str;
use Spatie\EventSourcing\Projectionist;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;
use Spatie\EventSourcing\StoredEvents\StoredEvent;

/*
 * The asset projectors move balances and write history. Transfers get both
 * from AssetTransferProjector, and single-account asset transactions get both
 * from AssetTransactionProjector, in the transaction that changes the balance.
 * A replay (event-sourcing:replay) or a stored event delivered a second time
 * used to run them again, which debited and credited the money again and added
 * the history rows again. Each stored event must change balances and history
 * once, and a refused debit writes no history.
 */

beforeEach(function (): void {
    foreach (['USD' => 'US Dollar', 'EUR' => 'Euro'] as $code => $name) {
        Asset::query()->firstOrCreate(['code' => $code], ['name' => $name, 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    }
});

function replayTestAccount(int $usdBalance = 0): Account
{
    $account = Account::factory()->create(['uuid' => (string) Str::uuid(), 'frozen' => false]);

    if ($usdBalance > 0) {
        AccountBalance::query()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => $usdBalance]);
    }

    return $account;
}

function replayTestBalance(Account $account, string $asset): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', $asset)->value('balance');
}

/** @return list<string> */
function replayTestHistory(Account $account): array
{
    return array_values(TransactionProjection::query()
        ->where('account_uuid', $account->uuid)
        ->orderBy('id')
        ->get()
        ->map(fn (TransactionProjection $row): string => "{$row->type} {$row->asset_code} {$row->amount}")
        ->all());
}

/** @param class-string $eventClass */
function replayTestStoredEvent(string $aggregateUuid, string $eventClass): StoredEvent
{
    $storedEvent = app(StoredEventRepository::class)
        ->retrieveAll($aggregateUuid)
        ->first(fn (StoredEvent $storedEvent): bool => $storedEvent->event instanceof $eventClass);

    expect($storedEvent)->toBeInstanceOf(StoredEvent::class);

    return $storedEvent;
}

function replayTestTransfer(Account $from, Account $to, int $amount): string
{
    $transferId = (string) Str::uuid();

    AssetTransferAggregate::retrieve($transferId)
        ->initiate(new AccountUuid($from->uuid), new AccountUuid($to->uuid), 'USD', 'USD', new Money($amount), new Money($amount))
        ->complete($transferId)
        ->persist();

    return $transferId;
}

it('books a transfer once when its completed event is delivered again', function (): void {
    $from = replayTestAccount(10_000);
    $to = replayTestAccount();
    $transferId = replayTestTransfer($from, $to, 2_500);

    expect(replayTestBalance($from, 'USD'))->toBe(7_500)
        ->and(replayTestBalance($to, 'USD'))->toBe(2_500);

    app(Projectionist::class)->handleWithSyncEventHandlers(replayTestStoredEvent($transferId, AssetTransferCompleted::class));

    expect(replayTestBalance($from, 'USD'))->toBe(7_500)
        ->and(replayTestBalance($to, 'USD'))->toBe(2_500)
        ->and(replayTestHistory($from))->toBe(['transfer_out USD 2500'])
        ->and(replayTestHistory($to))->toBe(['transfer_in USD 2500']);
});

it('books a transfer once when the projector replays its events', function (): void {
    $from = replayTestAccount(10_000);
    $to = replayTestAccount();
    $transferId = replayTestTransfer($from, $to, 2_500);

    app(Projectionist::class)->replay(collect([app(AssetTransferProjector::class)]), aggregateUuid: $transferId);

    expect(replayTestBalance($from, 'USD'))->toBe(7_500)
        ->and(replayTestBalance($to, 'USD'))->toBe(2_500)
        ->and(replayTestHistory($from))->toBe(['transfer_out USD 2500'])
        ->and(replayTestHistory($to))->toBe(['transfer_in USD 2500']);
});

it('still books a second transfer with the same amount', function (): void {
    $from = replayTestAccount(10_000);
    $to = replayTestAccount();
    replayTestTransfer($from, $to, 2_500);
    replayTestTransfer($from, $to, 2_500);

    expect(replayTestBalance($from, 'USD'))->toBe(5_000)
        ->and(replayTestBalance($to, 'USD'))->toBe(5_000)
        ->and(replayTestHistory($from))->toBe(['transfer_out USD 2500', 'transfer_out USD 2500']);
});

it('credits an asset transaction once when its stored event is delivered again', function (): void {
    $account = replayTestAccount();
    $aggregateUuid = (string) Str::uuid();

    AssetTransactionAggregate::retrieve($aggregateUuid)
        ->credit(new AccountUuid($account->uuid), 'USD', new Money(1_000))
        ->persist();

    app(Projectionist::class)->handleWithSyncEventHandlers(replayTestStoredEvent($aggregateUuid, AssetTransactionCreated::class));

    expect(replayTestBalance($account, 'USD'))->toBe(1_000)
        ->and(replayTestHistory($account))->toBe(['deposit USD 1000']);
});

it('debits an asset transaction once when the projector replays its events', function (): void {
    $account = replayTestAccount(5_000);
    $aggregateUuid = (string) Str::uuid();

    AssetTransactionAggregate::retrieve($aggregateUuid)
        ->debit(new AccountUuid($account->uuid), 'USD', new Money(2_000))
        ->persist();

    expect(replayTestBalance($account, 'USD'))->toBe(3_000);

    app(Projectionist::class)->replay(collect([app(AssetTransactionProjector::class)]), aggregateUuid: $aggregateUuid);

    expect(replayTestBalance($account, 'USD'))->toBe(3_000)
        ->and(replayTestHistory($account))->toBe(['withdrawal USD 2000']);
});

it('leaves a debit that found too little money open for a later delivery', function (): void {
    $account = replayTestAccount(500);
    $aggregateUuid = (string) Str::uuid();

    AssetTransactionAggregate::retrieve($aggregateUuid)
        ->debit(new AccountUuid($account->uuid), 'USD', new Money(2_000))
        ->persist();

    // Refused and logged, as before: nothing changed.
    expect(replayTestBalance($account, 'USD'))->toBe(500)
        ->and(replayTestHistory($account))->toBe([]);

    AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->update(['balance' => 2_500]);
    $storedEvent = replayTestStoredEvent($aggregateUuid, AssetTransactionCreated::class);
    app(Projectionist::class)->handleWithSyncEventHandlers($storedEvent);
    app(Projectionist::class)->handleWithSyncEventHandlers($storedEvent);

    expect(replayTestBalance($account, 'USD'))->toBe(500)
        ->and(replayTestHistory($account))->toBe(['withdrawal USD 2000']);
});

it('writes no withdrawal history for a debit it refuses', function (): void {
    $account = replayTestAccount(500);

    AssetTransactionAggregate::retrieve((string) Str::uuid())
        ->debit(new AccountUuid($account->uuid), 'USD', new Money(2_000))
        ->persist();

    expect(replayTestBalance($account, 'USD'))->toBe(500)
        ->and(replayTestHistory($account))->toBe([]);
});

it('writes the history row of an applied debit and credit', function (): void {
    $account = replayTestAccount(5_000);
    $aggregateUuid = (string) Str::uuid();

    AssetTransactionAggregate::retrieve($aggregateUuid)
        ->debit(new AccountUuid($account->uuid), 'USD', new Money(2_000), 'Card top-up')
        ->persist();
    AssetTransactionAggregate::retrieve((string) Str::uuid())
        ->credit(new AccountUuid($account->uuid), 'USD', new Money(700))
        ->persist();

    $withdrawal = TransactionProjection::query()->where('account_uuid', $account->uuid)->where('type', 'withdrawal')->firstOrFail();

    expect(replayTestHistory($account))->toBe(['withdrawal USD 2000', 'deposit USD 700'])
        ->and($withdrawal->description)->toBe('Card top-up')
        ->and($withdrawal->reference)->toBe($aggregateUuid)
        ->and($withdrawal->status)->toBe('completed');
});
