<?php

/**
 * Batch creation (the /batch-processing web form, the createBatchJob GraphQL
 * mutation and retry) never checked who owns the source accounts, and the
 * batch routes have no transaction.rate_limit. Every item now gets the checks
 * of a single transfer (POST /api/transfers) and counts against the same
 * per-user transfer limits.
 */

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Batch\Activities\CompleteBatchJobActivity;
use App\Domain\Batch\Activities\ProcessBatchItemActivity;
use App\Domain\Batch\Activities\ValidateBatchJobActivity;
use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Domain\Batch\DataObjects\BatchJob as BatchJobData;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Models\BatchJobItem;
use App\Domain\Batch\Services\BatchProcessingService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

    // Workflow activities are queued after commit, which never happens inside
    // the test transaction. Faked, the workflow runs at once, and each
    // activity "mock" runs the real activity.
    WorkflowStub::fake();
    foreach ([ValidateBatchJobActivity::class, ProcessBatchItemActivity::class, CompleteBatchJobActivity::class] as $activity) {
        WorkflowStub::mock($activity, fn ($context, ...$arguments) => (new $activity($context->index, $context->now->format('Y-m-d\\TH:i:s.u\\Z'), $context->storedWorkflow, ...$arguments))->handle());
    }
});

function ownedUsdAccount(User $owner, int $cents, bool $frozen = false): Account
{
    $account = Account::factory()->create(['uuid' => (string) Str::uuid(), 'user_uuid' => $owner->uuid, 'frozen' => $frozen, 'balance' => 0]);
    AccountBalance::query()->updateOrCreate(['account_uuid' => $account->uuid, 'asset_code' => 'USD'], ['balance' => $cents]);

    return $account;
}

function usdBalanceOf(Account $account): int
{
    return (int) AccountBalance::query()->where('account_uuid', $account->uuid)->where('asset_code', 'USD')->value('balance');
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function batchForm(array $items, string $type = 'transfer'): array
{
    return ['name' => 'Payroll', 'type' => $type, 'items' => $items];
}

/**
 * @return array<string, mixed>
 */
function usdItem(Account $from, Account $to, string $amount): array
{
    return ['from_account' => $from->uuid, 'to_account' => $to->uuid, 'amount' => $amount, 'currency' => 'USD'];
}

it('refuses a batch that spends from another user\'s account', function (): void {
    $attacker = User::factory()->create();
    $attackerAccount = ownedUsdAccount($attacker, 0);
    $victim = ownedUsdAccount(User::factory()->create(), 50_000);

    $this->actingAs($attacker)
        ->post(route('batch-processing.store'), batchForm([usdItem($victim, $attackerAccount, '100.00')]))
        ->assertForbidden();

    expect(BatchJob::query()->count())->toBe(0)
        ->and(usdBalanceOf($victim))->toBe(50_000)
        ->and(usdBalanceOf($attackerAccount))->toBe(0);
});

it('refuses the whole batch when one item spends from another user\'s account', function (): void {
    $user = User::factory()->create();
    $own = ownedUsdAccount($user, 50_000);
    $recipient = ownedUsdAccount(User::factory()->create(), 0);
    $victim = ownedUsdAccount(User::factory()->create(), 50_000);

    $this->actingAs($user)
        ->postJson(route('batch-processing.store'), batchForm([
            usdItem($own, $recipient, '10.00'),
            usdItem($victim, $recipient, '10.00'),
        ], 'payment'))
        ->assertForbidden();

    expect(BatchJob::query()->count())->toBe(0)
        ->and(usdBalanceOf($own))->toBe(50_000)
        ->and(usdBalanceOf($victim))->toBe(50_000);
});

it('runs a valid batch and moves the exact amounts', function (): void {
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 30_000);
    $alice = ownedUsdAccount(User::factory()->create(), 0);
    $bob = ownedUsdAccount(User::factory()->create(), 0);

    $this->actingAs($user)
        ->post(route('batch-processing.store'), batchForm([
            usdItem($from, $alice, '128.14'),
            usdItem($from, $bob, '0.29'),
        ]))
        ->assertRedirect();

    $batch = BatchJob::query()->firstOrFail();

    expect($batch->user_uuid)->toBe($user->uuid)
        ->and($batch->status)->toBe('completed')
        ->and(BatchJobItem::query()->where('batch_job_uuid', $batch->uuid)->pluck('status')->all())->toBe(['completed', 'completed'])
        ->and(usdBalanceOf($from))->toBe(30_000 - 12_814 - 29)
        ->and(usdBalanceOf($alice))->toBe(12_814)
        ->and(usdBalanceOf($bob))->toBe(29);
});

it('applies the single transfer checks to every item', function (array $item, string $errorKey): void {
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 10_000);
    $to = ownedUsdAccount(User::factory()->create(), 0);
    $frozenTo = ownedUsdAccount(User::factory()->create(), 0, frozen: true);
    $frozenFrom = ownedUsdAccount($user, 10_000, frozen: true);

    $resolved = array_map(fn (mixed $value): mixed => match ($value) {
        'FROM'        => $from->uuid,
        'TO'          => $to->uuid,
        'FROZEN_TO'   => $frozenTo->uuid,
        'FROZEN_FROM' => $frozenFrom->uuid,
        default       => $value,
    }, $item);

    $this->actingAs($user)
        ->postJson(route('batch-processing.store'), batchForm([$resolved]))
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);

    expect(BatchJob::query()->count())->toBe(0)
        ->and(usdBalanceOf($from))->toBe(10_000);
})->with([
    'same account'          => [['from_account' => 'FROM', 'to_account' => 'FROM', 'amount' => '1.00', 'currency' => 'USD'], 'items.0.to_account'],
    'frozen source'         => [['from_account' => 'FROZEN_FROM', 'to_account' => 'TO', 'amount' => '1.00', 'currency' => 'USD'], 'items.0.from_account'],
    'frozen destination'    => [['from_account' => 'FROM', 'to_account' => 'FROZEN_TO', 'amount' => '1.00', 'currency' => 'USD'], 'items.0.to_account'],
    'unknown asset'         => [['from_account' => 'FROM', 'to_account' => 'TO', 'amount' => '1.00', 'currency' => 'XXX'], 'items.0.currency'],
    'unknown destination'   => [['from_account' => 'FROM', 'to_account' => '6f1c2d3e-4b5a-4c6d-8e7f-8091a2b3c4d5', 'amount' => '1.00', 'currency' => 'USD'], 'items.0.to_account'],
    'too many decimals'     => [['from_account' => 'FROM', 'to_account' => 'TO', 'amount' => '1.005', 'currency' => 'USD'], 'items.0.amount'],
    'more than the balance' => [['from_account' => 'FROM', 'to_account' => 'TO', 'amount' => '100.01', 'currency' => 'USD'], 'items.0.amount'],
]);

it('refuses items that together spend more than the balance', function (): void {
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 10_000);
    $to = ownedUsdAccount(User::factory()->create(), 0);

    $this->actingAs($user)
        ->postJson(route('batch-processing.store'), batchForm([
            usdItem($from, $to, '60.00'),
            usdItem($from, $to, '60.00'),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.1.amount']);

    expect(BatchJob::query()->count())->toBe(0)
        ->and(usdBalanceOf($from))->toBe(10_000);
});

it('refuses batch conversions', function (): void {
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 10_000);

    $this->actingAs($user)
        ->postJson(route('batch-processing.store'), batchForm([['from_account' => $from->uuid, 'amount' => '1.00', 'currency' => 'USD', 'from_currency' => 'USD', 'to_currency' => 'EUR']], 'conversion'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

it('counts every item against the hourly transfer limit', function (): void {
    config(['rate_limiting.force_in_tests' => true]);
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 100_000);
    $to = ownedUsdAccount(User::factory()->create(), 0);

    // 15 transfers an hour, as for POST /api/transfers.
    $this->actingAs($user)
        ->postJson(route('batch-processing.store'), batchForm(array_fill(0, 16, usdItem($from, $to, '1.00'))))
        ->assertStatus(429);

    expect(BatchJob::query()->count())->toBe(0)
        ->and(usdBalanceOf($from))->toBe(100_000)
        ->and((int) Cache::get("tx_rate_limit:{$user->id}:transfer:hourly", 0))->toBe(0);

    $this->actingAs($user)
        ->post(route('batch-processing.store'), batchForm(array_fill(0, 3, usdItem($from, $to, '1.00'))))
        ->assertRedirect();

    expect((int) Cache::get("tx_rate_limit:{$user->id}:transfer:hourly"))->toBe(3)
        ->and((int) Cache::get("tx_amount_limit:{$user->id}:transfer:hourly"))->toBe(300)
        ->and(usdBalanceOf($to))->toBe(300);
});

it('counts the batch total against the hourly amount limit', function (): void {
    config(['rate_limiting.force_in_tests' => true]);
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 1_000_000);
    $to = ownedUsdAccount(User::factory()->create(), 0);

    // The hourly transfer amount cap is 2 000.00; two items of 1 000.01 pass
    // it together, though each one alone would not.
    $this->actingAs($user)
        ->postJson(route('batch-processing.store'), batchForm([
            usdItem($from, $to, '1000.01'),
            usdItem($from, $to, '1000.00'),
        ]))
        ->assertStatus(429);

    expect(BatchJob::query()->count())->toBe(0)
        ->and(usdBalanceOf($from))->toBe(1_000_000)
        ->and((int) Cache::get("tx_rate_limit:{$user->id}:transfer:hourly", 0))->toBe(0);
});

it('refuses a GraphQL batch that spends from another user\'s account', function (): void {
    $attacker = User::factory()->create();
    $attackerAccount = ownedUsdAccount($attacker, 0);
    $victim = ownedUsdAccount(User::factory()->create(), 50_000);

    $response = $this->actingAs($attacker, 'sanctum')->postJson('/graphql', [
        'query'     => 'mutation ($input: CreateBatchJobInput!) { createBatchJob(input: $input) { id status } }',
        'variables' => ['input' => [
            'name'  => 'Payroll',
            'type'  => 'transfer',
            'items' => [(string) json_encode(usdItem($victim, $attackerAccount, '100.00'))],
        ]],
    ]);

    expect((string) $response->json('errors.0.message'))->toContain('only transfer from your own accounts')
        ->and($response->json('data.createBatchJob'))->toBeNull()
        ->and(BatchJob::query()->count())->toBe(0)
        ->and(usdBalanceOf($victim))->toBe(50_000);
});

it('runs a valid GraphQL batch', function (): void {
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 10_000);
    $to = ownedUsdAccount(User::factory()->create(), 0);

    $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
        'query'     => 'mutation ($input: CreateBatchJobInput!) { createBatchJob(input: $input) { id uuid status } }',
        'variables' => ['input' => [
            'name'  => 'Payroll',
            'type'  => 'payment',
            'items' => [(string) json_encode(usdItem($from, $to, '25.50'))],
        ]],
    ]);

    expect($response->json('errors'))->toBeNull()
        ->and($response->json('data.createBatchJob.uuid'))->toBeString()
        ->and(usdBalanceOf($from))->toBe(7_450)
        ->and(usdBalanceOf($to))->toBe(2_550);
});

it('counts GraphQL batch items against the same transfer limit', function (): void {
    config(['rate_limiting.force_in_tests' => true]);
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 100_000);
    $to = ownedUsdAccount(User::factory()->create(), 0);

    $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
        'query'     => 'mutation ($input: CreateBatchJobInput!) { createBatchJob(input: $input) { id } }',
        'variables' => ['input' => [
            'name'  => 'Payroll',
            'type'  => 'transfer',
            'items' => array_fill(0, 16, (string) json_encode(usdItem($from, $to, '1.00'))),
        ]],
    ]);

    expect((string) $response->json('errors.0.message'))->toContain('Rate limit')
        ->and(BatchJob::query()->count())->toBe(0)
        ->and(usdBalanceOf($from))->toBe(100_000);
});

it('retries the failed items once, as a new checked batch', function (): void {
    $user = User::factory()->create();
    $from = ownedUsdAccount($user, 0);
    $to = ownedUsdAccount(User::factory()->create(), 0);

    // A batch whose only item failed at run time: the money was gone by then.
    $data = BatchJobData::create(userUuid: (string) $user->uuid, name: 'Payroll', type: 'transfer', items: [
        ['type' => 'transfer'] + usdItem($from, $to, '40.00'),
    ]);
    BatchAggregate::retrieve($data->uuid)->createBatchJob($data)->persist();
    app(BatchProcessingService::class)->processBatch($data->uuid);
    $batch = BatchJob::query()->where('uuid', $data->uuid)->firstOrFail();
    expect(BatchJobItem::query()->where('batch_job_uuid', $batch->uuid)->value('status'))->toBe('failed');

    AccountBalance::query()->where('account_uuid', $from->uuid)->update(['balance' => 10_000]);

    $this->actingAs($user)->post(route('batch-processing.retry', $batch))->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('batch-processing.retry', $batch))->assertSessionHasErrors('error');

    expect(BatchJob::query()->count())->toBe(2)
        ->and(usdBalanceOf($from))->toBe(6_000)
        ->and(usdBalanceOf($to))->toBe(4_000)
        ->and(BatchJobItem::query()->where('batch_job_uuid', $batch->uuid)->value('status'))->toBe('retried');
});

it('does not retry another user\'s batch', function (): void {
    $owner = User::factory()->create();
    $from = ownedUsdAccount($owner, 10_000);
    $to = ownedUsdAccount(User::factory()->create(), 0);
    $this->actingAs($owner)->post(route('batch-processing.store'), batchForm([usdItem($from, $to, '10.00')]))->assertRedirect();
    $batch = BatchJob::query()->firstOrFail();

    $this->actingAs(User::factory()->create())->post(route('batch-processing.retry', $batch))->assertForbidden();
});
