<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Aggregates\AssetTransactionAggregate;
use App\Domain\Asset\Exceptions\InsufficientAssetBalanceException;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Services\AssetTransferService;
use App\Domain\Asset\Workflows\Activities\InitiateAssetTransferActivity;
use App\Domain\Asset\Workflows\AssetTransferWorkflow;
use App\Domain\Wallet\Activities\ConvertAssetActivity;
use App\Domain\Wallet\Workflows\WalletConvertWorkflow;
use Illuminate\Support\Str;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

/*
 * Asset transfers and wallet conversions never worked. The projector and the
 * initiate activity called AccountUuid::toString(), which does not exist, so
 * every transfer stopped with an Error before any balance moved. The history
 * projector read event fields that do not exist and left out the required
 * hash, so no history row was ever written. The conversion used a non-UUID
 * aggregate id and float arithmetic, and the transfer service only initiated
 * transfers that nothing ever completed.
 */

function assetBalance(Account $account, string $asset): int
{
    return (int) AccountBalance::query()
        ->where('account_uuid', $account->uuid)
        ->where('asset_code', $asset)
        ->value('balance');
}

/** @return list<array{type: string, asset_code: string, amount: int}> */
function assetHistory(Account $account): array
{
    return array_values(TransactionProjection::query()
        ->where('account_uuid', $account->uuid)
        ->orderBy('id')
        ->get(['type', 'asset_code', 'amount'])
        ->map(fn (TransactionProjection $row): array => ['type' => $row->type, 'asset_code' => $row->asset_code, 'amount' => (int) $row->amount])
        ->all());
}

/** Real accounts get version 4 ids; the factory's Faker ids are version 3, which the transfer service rejects. */
function assetAccount(): Account
{
    return Account::factory()->create(['uuid' => (string) Str::uuid(), 'frozen' => false]);
}

function storedWorkflowFor(string $workflow): StoredWorkflow
{
    return StoredWorkflow::query()->whereKey(WorkflowStub::make($workflow)->id())->firstOrFail();
}

function activityTimestamp(): string
{
    return now()->format('Y-m-d\TH:i:s.u\Z');
}

beforeEach(function (): void {
    ExchangeRate::query()->create([
        'from_asset_code' => 'USD',
        'to_asset_code'   => 'EUR',
        'rate'            => '0.29',
        'source'          => 'manual',
        'valid_at'        => now()->subMinute(),
        'is_active'       => true,
    ]);
});

it('converts a balance to another asset inside the same account', function (): void {
    $account = assetAccount();
    $account->balances()->updateOrCreate(['asset_code' => 'USD'], ['balance' => 10_000]);

    $activity = new ConvertAssetActivity(0, activityTimestamp(), storedWorkflowFor(WalletConvertWorkflow::class), new AccountUuid($account->uuid), 'USD', 'EUR', 10_000);
    $result = $activity->handle();

    // 10 000 x 0.29 is 2 899.999... in floating point; the ledger must book 2 900.
    expect($result['converted_amount'])->toBe(2_900)
        ->and(Str::isUuid($result['transfer_id']))->toBeTrue()
        ->and(assetBalance($account, 'USD'))->toBe(0)
        ->and(assetBalance($account, 'EUR'))->toBe(2_900)
        ->and(assetHistory($account))->toBe([
            ['type' => 'transfer_out', 'asset_code' => 'USD', 'amount' => 10_000],
            ['type' => 'transfer_in', 'asset_code' => 'EUR', 'amount' => 2_900],
        ]);
});

it('refuses a conversion above the balance without recording anything', function (): void {
    $account = assetAccount();
    $account->balances()->updateOrCreate(['asset_code' => 'USD'], ['balance' => 500]);
    $events = EloquentStoredEvent::query()->count();

    $activity = new ConvertAssetActivity(0, activityTimestamp(), storedWorkflowFor(WalletConvertWorkflow::class), new AccountUuid($account->uuid), 'USD', 'EUR', 1_000);

    expect(fn () => $activity->handle())->toThrow(Exception::class, 'Insufficient USD balance')
        ->and(EloquentStoredEvent::query()->count())->toBe($events)
        ->and(assetBalance($account, 'USD'))->toBe(500)
        ->and(assetBalance($account, 'EUR'))->toBe(0);
});

it('moves both balances when the transfer service sends an asset', function (): void {
    $from = assetAccount();
    $to = assetAccount();
    $from->balances()->updateOrCreate(['asset_code' => 'USD'], ['balance' => 5_000]);

    $transferId = app(AssetTransferService::class)->transfer((string) $from->uuid, (string) $to->uuid, 'USD', '25.00');

    expect(Str::isUuid($transferId))->toBeTrue()
        ->and(assetBalance($from, 'USD'))->toBe(2_500)
        ->and(assetBalance($to, 'USD'))->toBe(2_500)
        ->and(assetHistory($from))->toBe([['type' => 'transfer_out', 'asset_code' => 'USD', 'amount' => 2_500]])
        ->and(assetHistory($to))->toBe([['type' => 'transfer_in', 'asset_code' => 'USD', 'amount' => 2_500]]);
});

it('converts and sends an asset through the transfer service', function (): void {
    $from = assetAccount();
    $to = assetAccount();
    $from->balances()->updateOrCreate(['asset_code' => 'USD'], ['balance' => 10_000]);

    $result = app(AssetTransferService::class)->convertAndTransfer((string) $from->uuid, (string) $to->uuid, 'USD', 'EUR', '100.00');

    expect($result['to_amount'])->toBe('29.00000000')
        ->and(assetBalance($from, 'USD'))->toBe(0)
        ->and(assetBalance($to, 'EUR'))->toBe(2_900);
});

it('initiates an asset transfer through the workflow activity', function (): void {
    $from = assetAccount();
    $to = assetAccount();
    $from->balances()->updateOrCreate(['asset_code' => 'USD'], ['balance' => 5_000]);
    $events = EloquentStoredEvent::query()->count();

    $activity = new InitiateAssetTransferActivity(0, activityTimestamp(), storedWorkflowFor(AssetTransferWorkflow::class), new AccountUuid($from->uuid), new AccountUuid($to->uuid), 'USD', 'USD', new Money(1_000), null);

    expect(Str::isUuid($activity->handle()))->toBeTrue()
        ->and(EloquentStoredEvent::query()->count())->toBe($events + 1);
});

it('writes a history row for an asset deposit', function (): void {
    $account = assetAccount();

    AssetTransactionAggregate::retrieve((string) Str::uuid())
        ->credit(new AccountUuid($account->uuid), 'EUR', new Money(1_250), 'Top-up')
        ->persist();

    expect(assetBalance($account, 'EUR'))->toBe(1_250)
        ->and(assetHistory($account))->toBe([['type' => 'deposit', 'asset_code' => 'EUR', 'amount' => 1_250]]);
});

it('compares the transfer amount with the balance in the same units', function (): void {
    // The service used to compare "25.00" with a balance of 30 cents and let
    // it through, then booked 25.00 as 2 500 000 000 minor units.
    $from = assetAccount();
    $to = assetAccount();
    $from->balances()->updateOrCreate(['asset_code' => 'USD'], ['balance' => 30]);
    $events = EloquentStoredEvent::query()->count();

    expect(fn () => app(AssetTransferService::class)->transfer((string) $from->uuid, (string) $to->uuid, 'USD', '25.00'))
        ->toThrow(InsufficientAssetBalanceException::class)
        ->and(EloquentStoredEvent::query()->count())->toBe($events)
        ->and(assetBalance($from, 'USD'))->toBe(30);
});

it('refuses an amount with more decimals than the asset has', function (): void {
    $from = assetAccount();
    $to = assetAccount();
    $from->balances()->updateOrCreate(['asset_code' => 'USD'], ['balance' => 5_000]);

    expect(fn () => app(AssetTransferService::class)->transfer((string) $from->uuid, (string) $to->uuid, 'USD', '10.005'))
        ->toThrow(InvalidArgumentException::class, 'USD amounts can have at most 2 decimal places')
        ->and(assetBalance($from, 'USD'))->toBe(5_000);
});
