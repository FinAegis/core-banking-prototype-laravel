<?php

declare(strict_types=1);

use App\Domain\Batch\Actions\CancelBatchJob;
use App\Domain\Batch\Events\BatchJobCancelled;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Models\BatchJobItem;
use Illuminate\Support\Str;

// Batch items live in batch_job_items (BatchJobItem, keyed by batch_job_uuid).
// Cancelling used the BatchItem model, whose batch_items table does not
// exist, so cancelling a batch job failed and its items stayed pending.

it('cancels the pending items of a cancelled batch job', function (): void {
    $batchJob = BatchJob::query()->create([
        'uuid'        => (string) Str::uuid(),
        'user_uuid'   => (string) Str::uuid(),
        'name'        => 'Cancel test',
        'type'        => 'transfer',
        'status'      => 'pending',
        'total_items' => 2,
    ]);
    BatchJobItem::create(['batch_job_uuid' => $batchJob->uuid, 'sequence' => 1, 'status' => 'pending', 'data' => []]);
    BatchJobItem::create(['batch_job_uuid' => $batchJob->uuid, 'sequence' => 2, 'status' => 'completed', 'data' => []]);

    $event = new BatchJobCancelled('Operator request', now()->toIso8601String());
    $event->setAggregateRootUuid($batchJob->uuid);

    app(CancelBatchJob::class)($event);

    $statuses = BatchJobItem::query()
        ->where('batch_job_uuid', $batchJob->uuid)
        ->orderBy('sequence')
        ->pluck('status')
        ->all();

    expect($statuses)->toBe(['cancelled', 'completed']);
    expect($batchJob->fresh()?->status)->toBe('cancelled');
});
