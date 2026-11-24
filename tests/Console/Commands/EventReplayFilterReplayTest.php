<?php

declare(strict_types=1);

namespace Tests\Console\Commands;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Aggregates\AssetTransactionAggregate;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Asset\Events\AssetTransactionCreated;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Projectors\AssetTransactionProjector;
use App\Domain\Shared\EventSourcing\AppliedStoredEvents;
use App\Domain\Shared\EventSourcing\ClaimsAppliedStoredEvents;
use App\Domain\Shared\EventSourcing\StoredEventReplayer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/*
 * event:replay used to ignore --event-type and --aggregate-id: it replayed every
 * stored event through every chosen projector. It now replays only the matching
 * events. To see which events a replay applies, these tests delete the claims
 * in applied_stored_events, as for events stored before the claims existed.
 */

const REPLAY_CONFIRMATION = 'This will replay events through projectors. Continue?';

beforeEach(function (): void {
    Cache::flush();
    Asset::query()->firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
});

function replayCommandAccount(int $usd): Account
{
    $account = Account::factory()->create(['uuid' => (string) Str::uuid(), 'frozen' => false]);
    AccountBalance::query()->create(['account_uuid' => $account->uuid, 'asset_code' => 'USD', 'balance' => $usd]);

    return $account;
}

function replayCommandUsd(Account $account): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance');
}

function replayCommandCredit(Account $account, int $amount): string
{
    $aggregateUuid = (string) Str::uuid();
    AssetTransactionAggregate::retrieve($aggregateUuid)->credit(new AccountUuid($account->uuid), 'USD', new Money($amount))->persist();

    return $aggregateUuid;
}

function replayCommandForgetAllClaims(): void
{
    DB::table(AppliedStoredEvents::TABLE)->delete();
}

it('replays only the events of the given aggregate', function (): void {
    $first = replayCommandAccount(0);
    $second = replayCommandAccount(0);
    $firstCredit = replayCommandCredit($first, 1_000);
    replayCommandCredit($second, 400);
    replayCommandForgetAllClaims();

    $this->artisan('event:replay', ['--projector' => AssetTransactionProjector::class, '--aggregate-id' => $firstCredit])
        ->expectsConfirmation(REPLAY_CONFIRMATION, 'yes')
        ->assertSuccessful();

    expect(replayCommandUsd($first))->toBe(2_000)
        ->and(replayCommandUsd($second))->toBe(400);
});

it('replays only the events of the given type', function (): void {
    $from = replayCommandAccount(10_000);
    $to = replayCommandAccount(0);
    replayCommandCredit($from, 1_000);
    $transferId = (string) Str::uuid();
    AssetTransferAggregate::retrieve($transferId)
        ->initiate(new AccountUuid($from->uuid), new AccountUuid($to->uuid), 'USD', 'USD', new Money(2_500), new Money(2_500))
        ->complete($transferId)
        ->persist();
    expect(replayCommandUsd($from))->toBe(8_500);
    replayCommandForgetAllClaims();

    $this->artisan('event:replay', ['--event-type' => 'AssetTransferCompleted'])
        ->expectsConfirmation(REPLAY_CONFIRMATION, 'yes')
        ->expectsOutputToContain('Filtered events to replay: 1')
        ->assertSuccessful();

    // The transfer is booked again, the credit is not.
    expect(replayCommandUsd($from))->toBe(6_000)
        ->and(replayCommandUsd($to))->toBe(5_000);
});

it('replays nothing when no stored event matches', function (): void {
    $account = replayCommandAccount(0);
    $credit = replayCommandCredit($account, 1_000);
    replayCommandForgetAllClaims();

    $this->artisan('event:replay', ['--aggregate-id' => $credit, '--from' => now()->addDay()->toDateString()])
        ->expectsConfirmation(REPLAY_CONFIRMATION, 'yes')
        ->expectsOutputToContain('No events match the provided filters.')
        ->assertSuccessful();

    expect(replayCommandUsd($account))->toBe(1_000);
});

it('changes nothing when a full replay runs over projectors that keep their state', function (): void {
    $account = replayCommandAccount(0);
    replayCommandCredit($account, 1_000);

    $this->artisan('event:replay', ['--projector' => AssetTransactionProjector::class])
        ->expectsConfirmation(REPLAY_CONFIRMATION, 'yes')
        ->assertSuccessful();

    expect(replayCommandUsd($account))->toBe(1_000)
        ->and(DB::table(AppliedStoredEvents::TABLE)->where('projection', 'asset_transaction')->count())->toBe(1);
});

/**
 * A projector that resets its state on replay, as a rebuildable projection
 * would. It counts the events it applies, under a claim like the balance
 * projectors.
 */
final class ResettingReplayFixtureProjector extends Projector implements ClaimsAppliedStoredEvents
{
    public static int $applied = 0;

    public static int $resets = 0;

    public function onAssetTransactionCreated(AssetTransactionCreated $event): void
    {
        DB::transaction(function () use ($event): void {
            if (AppliedStoredEvents::claim(DB::connection(), 'replay_fixture', $event)) {
                self::$applied++;
            }
        });
    }

    public function resetState(?string $aggregateUuid = null): void
    {
        self::$resets++;
    }

    public function forgetAppliedStoredEvents(?array $storedEventIds = null): void
    {
        AppliedStoredEvents::forget(DB::connection(), 'replay_fixture', $storedEventIds);
    }
}

it('forgets the claims of a projector whose state the replay resets, so the rebuild applies every event again', function (): void {
    $account = replayCommandAccount(0);
    replayCommandCredit($account, 100);
    replayCommandCredit($account, 200);
    $fixture = new ResettingReplayFixtureProjector();
    ResettingReplayFixtureProjector::$applied = 0;
    ResettingReplayFixtureProjector::$resets = 0;

    app(StoredEventReplayer::class)->replay(collect([$fixture]));
    app(StoredEventReplayer::class)->replay(collect([$fixture]));

    expect(ResettingReplayFixtureProjector::$resets)->toBe(2)
        ->and(ResettingReplayFixtureProjector::$applied)->toBe(4)
        ->and(DB::table(AppliedStoredEvents::TABLE)->where('projection', 'replay_fixture')->count())->toBe(2);
});

it('forgets only the claims of the replayed aggregate', function (): void {
    $account = replayCommandAccount(0);
    $first = replayCommandCredit($account, 100);
    replayCommandCredit($account, 200);
    $fixture = new ResettingReplayFixtureProjector();
    app(StoredEventReplayer::class)->replay(collect([$fixture]));
    ResettingReplayFixtureProjector::$applied = 0;

    app(StoredEventReplayer::class)->replay(collect([$fixture]), $first);

    expect(ResettingReplayFixtureProjector::$applied)->toBe(1)
        ->and(DB::table(AppliedStoredEvents::TABLE)->where('projection', 'replay_fixture')->count())->toBe(2);
});
