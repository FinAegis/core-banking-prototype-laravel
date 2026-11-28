<?php

/**
 * A batch created with a time in the future was saved as "pending" and then
 * nothing ever started it. The batch:start-scheduled command (every minute)
 * now starts each due batch once. A batch that already started when it was
 * created is never started a second time, and a batch that fell due more than
 * a day ago is left for an operator instead of moving money late.
 */

declare(strict_types=1);

use App\Domain\Batch\Activities\CompleteBatchJobActivity;
use App\Domain\Batch\Activities\ValidateBatchJobActivity;
use App\Domain\Batch\DataObjects\BatchJob as BatchJobData;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Services\BatchProcessingService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    config(['ledger.batch_processing_enabled' => true, 'cache.default' => 'array']);
    Cache::flush();
    WorkflowStub::fake();
    WorkflowStub::mock(ValidateBatchJobActivity::class, BatchJobData::create(userUuid: 'u', name: 'n', type: 'transfer', items: []));
    WorkflowStub::mock(CompleteBatchJobActivity::class, true);
});

function scheduledBatch(string $status, ?Carbon\CarbonInterface $at, string $type = 'transfer'): BatchJob
{
    return BatchJob::query()->create([
        'uuid'         => (string) Str::uuid(),
        'user_uuid'    => (string) Str::uuid(),
        'name'         => 'Payroll',
        'type'         => $type,
        'status'       => $status,
        'total_items'  => 0,
        'scheduled_at' => $at,
    ]);
}

/** @return list<string> */
function startedBatchUuids(): array
{
    return WorkflowStub::dispatched(ValidateBatchJobActivity::class)
        ->map(fn (array $arguments): string => $arguments[0])
        ->values()
        ->all();
}

it('starts a pending batch once its time has come', function (): void {
    $due = scheduledBatch('pending', now()->subMinute());
    scheduledBatch('pending', now()->addHour());
    scheduledBatch('cancelled', now()->subMinute());
    scheduledBatch('completed', now()->subMinute());
    scheduledBatch('scheduled', now()->subMinute(), 'batch_operations');

    $this->artisan('batch:start-scheduled')->assertSuccessful();

    expect(startedBatchUuids())->toBe([$due->uuid]);
});

it('starts a due batch only once across runs', function (): void {
    $due = scheduledBatch('pending', now()->subMinute());

    $this->artisan('batch:start-scheduled')->assertSuccessful();
    $this->artisan('batch:start-scheduled')->assertSuccessful();

    expect(startedBatchUuids())->toBe([$due->uuid]);
});

it('does not start again a batch that started when it was created', function (): void {
    $batch = scheduledBatch('pending', now());
    app(BatchProcessingService::class)->processBatch($batch->uuid);

    $this->artisan('batch:start-scheduled')->assertSuccessful();

    expect(startedBatchUuids())->toBe([$batch->uuid]);
});

it('leaves a batch that fell due more than a day ago for an operator', function (): void {
    $stale = scheduledBatch('pending', now()->subDays(2));
    $recent = scheduledBatch('pending', now()->subHours(23));

    $this->artisan('batch:start-scheduled')->assertSuccessful();

    expect(startedBatchUuids())->toBe([$recent->uuid])
        ->and($stale->fresh()?->status)->toBe('pending');
});

it('starts nothing while batch processing is off', function (): void {
    config(['ledger.batch_processing_enabled' => false]);
    scheduledBatch('pending', now()->subMinute());

    $this->artisan('batch:start-scheduled')->assertSuccessful();

    expect(startedBatchUuids())->toBe([]);
});

it('runs every minute without overlapping', function (): void {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);
    $event = collect($schedule->events())->first(fn ($e) => str_contains($e->command ?? '', 'batch:start-scheduled'));

    expect($event)->toBeInstanceOf(Event::class);
    assert($event instanceof Event);
    expect($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
