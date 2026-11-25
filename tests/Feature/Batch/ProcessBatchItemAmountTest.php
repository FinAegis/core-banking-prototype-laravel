<?php

/**
 * The batch item activity built a Money object with a `currency:` argument
 * that Money does not have, so every batch transfer stopped with an Error. It
 * also never checked that the source account belongs to the owner of the
 * batch: fixing only the Money call would have let any user spend from any
 * account. And it booked a TransferAggregate event that no projector applies,
 * so a "completed" transfer moved no balance.
 *
 * Now the activity checks the item again against the batch owner, books it as
 * an asset transfer that debits and credits the exact minor units, and uses a
 * transfer id derived from the batch and the item, so a retry or a re-run
 * workflow does not pay twice.
 */

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Batch\Activities\ProcessBatchItemActivity;
use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Domain\Batch\DataObjects\BatchJob as BatchJobData;
use App\Domain\Batch\Exceptions\BatchItemRefused;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Models\BatchJobItem;
use App\Domain\Batch\Workflows\ProcessBatchJobWorkflow;
use App\Models\User;
use Illuminate\Support\Str;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
});

function batchUsdAccount(User $owner, int $cents): Account
{
    $account = Account::factory()->create(['uuid' => (string) Str::uuid(), 'user_uuid' => $owner->uuid, 'frozen' => false, 'balance' => 0]);
    AccountBalance::query()->updateOrCreate(['account_uuid' => $account->uuid, 'asset_code' => 'USD'], ['balance' => $cents]);

    return $account;
}

function batchUsdBalance(Account $account): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance');
}

/**
 * A batch recorded straight on the aggregate and started, so the activity can
 * be run on its own, whatever the items hold (the API would refuse them).
 *
 * @param  list<array<string, mixed>>  $items
 */
function startedBatch(User $owner, array $items): string
{
    $data = BatchJobData::create(userUuid: (string) $owner->uuid, name: 'Payroll', type: 'transfer', items: $items);
    BatchAggregate::retrieve($data->uuid)->createBatchJob($data)->persist();
    BatchAggregate::retrieve($data->uuid)->startBatchJob()->persist();

    return $data->uuid;
}

/**
 * @param  array<string, mixed>  $item
 * @return array<string, mixed>
 */
function runBatchItem(string $batchUuid, int $index, array $item): array
{
    $workflow = StoredWorkflow::query()->whereKey(WorkflowStub::make(ProcessBatchJobWorkflow::class)->id())->firstOrFail();
    $activity = new ProcessBatchItemActivity($index, now()->format('Y-m-d\TH:i:s.u\Z'), $workflow, $batchUuid, $index, $item);

    /** @var array<string, mixed> $result */
    $result = $activity->handle();

    return $result;
}

it('moves the exact amount from the owner to the recipient', function (): void {
    $owner = User::factory()->create();
    $from = batchUsdAccount($owner, 20_000);
    $to = batchUsdAccount(User::factory()->create(), 0);
    $item = ['type' => 'transfer', 'from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '128.14', 'currency' => 'USD'];
    $batch = startedBatch($owner, [$item]);

    $result = runBatchItem($batch, 0, $item);

    expect($result['status'])->toBe('completed')
        ->and($result['amount'])->toBe(12_814)
        ->and($result['transfer_id'])->toBe(ProcessBatchItemActivity::transferId($batch, 0))
        ->and(batchUsdBalance($from))->toBe(7_186)
        ->and(batchUsdBalance($to))->toBe(12_814)
        ->and(BatchJobItem::query()->where('batch_job_uuid', $batch)->value('status'))->toBe('completed');
});

it('refuses to spend from an account the batch owner does not own', function (): void {
    $attacker = User::factory()->create();
    $victim = batchUsdAccount(User::factory()->create(), 50_000);
    $attackerAccount = batchUsdAccount($attacker, 0);
    $item = ['type' => 'transfer', 'from_account' => $victim->uuid, 'to_account' => $attackerAccount->uuid, 'amount' => '100.00', 'currency' => 'USD'];
    $batch = startedBatch($attacker, [$item]);

    expect(fn () => runBatchItem($batch, 0, $item))
        ->toThrow(BatchItemRefused::class, 'does not belong to the owner of the batch');

    expect(batchUsdBalance($victim))->toBe(50_000)
        ->and(batchUsdBalance($attackerAccount))->toBe(0)
        ->and(BatchJobItem::query()->where('batch_job_uuid', $batch)->value('status'))->toBe('failed');
});

it('does not pay twice when the item runs again or the workflow is re-run', function (): void {
    $owner = User::factory()->create();
    $from = batchUsdAccount($owner, 20_000);
    $to = batchUsdAccount(User::factory()->create(), 0);
    $item = ['type' => 'transfer', 'from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '50.00', 'currency' => 'USD'];
    $batch = startedBatch($owner, [$item]);

    $first = runBatchItem($batch, 0, $item);
    // A second worker run, under a new stored workflow, for the same item.
    $second = runBatchItem($batch, 0, $item);

    expect($second['transfer_id'])->toBe($first['transfer_id'])
        ->and(batchUsdBalance($from))->toBe(15_000)
        ->and(batchUsdBalance($to))->toBe(5_000)
        ->and(BatchJob::query()->where('uuid', $batch)->value('processed_items'))->toBe(1);
});

it('refuses a transfer one cent above the balance', function (): void {
    $owner = User::factory()->create();
    $from = batchUsdAccount($owner, 12_813);
    $to = batchUsdAccount(User::factory()->create(), 0);
    $item = ['type' => 'transfer', 'from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => 128.14, 'currency' => 'USD'];
    $batch = startedBatch($owner, [$item]);

    expect(fn () => runBatchItem($batch, 0, $item))->toThrow(BatchItemRefused::class, 'Insufficient balance');
    expect(batchUsdBalance($from))->toBe(12_813);
});

it('refuses a transfer amount with more decimals than the currency has', function (): void {
    $owner = User::factory()->create();
    $from = batchUsdAccount($owner, 100_000);
    $to = batchUsdAccount(User::factory()->create(), 0);
    $item = ['type' => 'transfer', 'from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '1.005', 'currency' => 'USD'];
    $batch = startedBatch($owner, [$item]);

    expect(fn () => runBatchItem($batch, 0, $item))->toThrow(BatchItemRefused::class, 'at most 2 decimal places');
    expect(batchUsdBalance($from))->toBe(100_000);
});

it('moves no more money once the batch is cancelled', function (): void {
    $owner = User::factory()->create();
    $from = batchUsdAccount($owner, 20_000);
    $to = batchUsdAccount(User::factory()->create(), 0);
    $item = ['type' => 'transfer', 'from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '10.00', 'currency' => 'USD'];
    $batch = startedBatch($owner, [$item]);
    BatchAggregate::retrieve($batch)->cancelBatchJob('stop')->persist();

    expect(fn () => runBatchItem($batch, 0, $item))->toThrow(BatchItemRefused::class, 'cancelled');
    expect(batchUsdBalance($from))->toBe(20_000);
});

it('refuses batch conversions, which never moved money', function (): void {
    $owner = User::factory()->create();
    $account = batchUsdAccount($owner, 10_000);
    $item = ['type' => 'conversion', 'from_account' => $account->uuid, 'amount' => '0.29', 'from_currency' => 'USD', 'to_currency' => 'EUR'];
    $batch = startedBatch($owner, [$item]);

    expect(fn () => runBatchItem($batch, 0, $item))->toThrow(BatchItemRefused::class, 'conversions are not available');
    expect(batchUsdBalance($account))->toBe(10_000);
});
