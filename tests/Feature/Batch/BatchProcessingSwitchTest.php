<?php

/**
 * Batch processing is a demo feature. Zelta does not offer batches, so the
 * switch (BATCH_PROCESSING_ENABLED, config ledger.batch_processing_enabled)
 * is off by default in production. While it is off, the web
 * /batch-processing routes answer 404, the GraphQL createBatchJob mutation
 * is refused, and the batch item activity moves no money, so a batch that
 * was queued earlier cannot pay.
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

function switchUsdAccount(User $owner, int $cents): Account
{
    $account = Account::factory()->create(['uuid' => (string) Str::uuid(), 'user_uuid' => $owner->uuid, 'frozen' => false, 'balance' => 0]);
    AccountBalance::query()->updateOrCreate(['account_uuid' => $account->uuid, 'asset_code' => 'USD'], ['balance' => $cents]);

    return $account;
}

function switchUsdBalance(Account $account): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance');
}

/**
 * config/ledger.php as it loads under the given APP_ENV with
 * BATCH_PROCESSING_ENABLED unset.
 *
 * @return array<string, mixed>
 */
function ledgerConfigUnder(string $appEnv): array
{
    $saved = [getenv('APP_ENV'), getenv('BATCH_PROCESSING_ENABLED')];
    $previousServer = [$_SERVER['APP_ENV'] ?? null, $_ENV['APP_ENV'] ?? null, $_SERVER['BATCH_PROCESSING_ENABLED'] ?? null, $_ENV['BATCH_PROCESSING_ENABLED'] ?? null];
    putenv('APP_ENV=' . $appEnv);
    putenv('BATCH_PROCESSING_ENABLED');
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $appEnv;
    unset($_SERVER['BATCH_PROCESSING_ENABLED'], $_ENV['BATCH_PROCESSING_ENABLED']);

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/ledger.php');
    } finally {
        $saved[0] === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $saved[0]);
        if ($saved[1] !== false) {
            putenv('BATCH_PROCESSING_ENABLED=' . $saved[1]);
        }
        [$_SERVER['APP_ENV'], $_ENV['APP_ENV']] = [$previousServer[0], $previousServer[1]];
        if ($previousServer[2] !== null) {
            $_SERVER['BATCH_PROCESSING_ENABLED'] = $previousServer[2];
        }
        if ($previousServer[3] !== null) {
            $_ENV['BATCH_PROCESSING_ENABLED'] = $previousServer[3];
        }
    }

    return $config;
}

it('keeps batch processing off by default in production and staging', function (string $appEnv): void {
    expect(ledgerConfigUnder($appEnv)['batch_processing_enabled'])->toBeFalse();
})->with(['production', 'staging']);

it('keeps batch processing on by default in demo, local and testing', function (string $appEnv): void {
    expect(ledgerConfigUnder($appEnv)['batch_processing_enabled'])->toBeTrue();
})->with(['demo', 'local', 'testing']);

it('answers 404 on every batch web route while the switch is off', function (): void {
    config(['ledger.batch_processing_enabled' => false]);
    $user = User::factory()->create();
    $from = switchUsdAccount($user, 10_000);
    $to = switchUsdAccount(User::factory()->create(), 0);
    $batch = BatchJob::query()->create([
        'uuid'        => (string) Str::uuid(),
        'user_uuid'   => $user->uuid,
        'name'        => 'Payroll',
        'type'        => 'transfer',
        'status'      => 'failed',
        'total_items' => 1,
    ]);

    $this->actingAs($user);
    $this->get(route('batch-processing.index'))->assertNotFound();
    $this->get(route('batch-processing.create'))->assertNotFound();
    $this->post(route('batch-processing.store'), [
        'name'  => 'Payroll',
        'type'  => 'transfer',
        'items' => [['from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '10.00', 'currency' => 'USD']],
    ])->assertNotFound();
    $this->get(route('batch-processing.show', $batch))->assertNotFound();
    $this->post(route('batch-processing.cancel', $batch))->assertNotFound();
    $this->post(route('batch-processing.retry', $batch))->assertNotFound();
    $this->get(route('batch-processing.download', $batch))->assertNotFound();

    expect(BatchJob::query()->count())->toBe(1)
        ->and(switchUsdBalance($from))->toBe(10_000);
});

it('serves the batch web routes while the switch is on', function (): void {
    config(['ledger.batch_processing_enabled' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('batch-processing.create'))
        ->assertOk();
});

it('refuses the GraphQL batch mutation while the switch is off', function (): void {
    config(['ledger.batch_processing_enabled' => false]);
    $user = User::factory()->create();
    $from = switchUsdAccount($user, 10_000);
    $to = switchUsdAccount(User::factory()->create(), 0);

    $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
        'query'     => 'mutation ($input: CreateBatchJobInput!) { createBatchJob(input: $input) { id } }',
        'variables' => ['input' => [
            'name'  => 'Payroll',
            'type'  => 'transfer',
            'items' => [(string) json_encode(['from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '10.00', 'currency' => 'USD'])],
        ]],
    ]);

    expect($response->json('errors.0.message'))->toBe('This operation is not available.')
        ->and(BatchJob::query()->count())->toBe(0)
        ->and(switchUsdBalance($from))->toBe(10_000);
});

it('moves no money for a batch already queued once the switch is off', function (): void {
    $owner = User::factory()->create();
    $from = switchUsdAccount($owner, 20_000);
    $to = switchUsdAccount(User::factory()->create(), 0);
    $item = ['type' => 'transfer', 'from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => '50.00', 'currency' => 'USD'];

    // Queued while the switch was on.
    $data = BatchJobData::create(userUuid: (string) $owner->uuid, name: 'Payroll', type: 'transfer', items: [$item]);
    BatchAggregate::retrieve($data->uuid)->createBatchJob($data)->persist();
    BatchAggregate::retrieve($data->uuid)->startBatchJob()->persist();

    config(['ledger.batch_processing_enabled' => false]);

    $workflow = StoredWorkflow::query()->whereKey(WorkflowStub::make(ProcessBatchJobWorkflow::class)->id())->firstOrFail();
    $activity = new ProcessBatchItemActivity(0, now()->format('Y-m-d\TH:i:s.u\Z'), $workflow, $data->uuid, 0, $item);

    expect(fn () => $activity->handle())->toThrow(BatchItemRefused::class, 'Batch processing is not available');

    expect(switchUsdBalance($from))->toBe(20_000)
        ->and(switchUsdBalance($to))->toBe(0)
        ->and(BatchJobItem::query()->where('batch_job_uuid', $data->uuid)->value('status'))->toBe('failed');
});
