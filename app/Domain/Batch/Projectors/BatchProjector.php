<?php

namespace App\Domain\Batch\Projectors;

use App\Domain\Batch\Actions\CancelBatchJob;
use App\Domain\Batch\Actions\CompleteBatchJob;
use App\Domain\Batch\Actions\CreateBatchJob;
use App\Domain\Batch\Actions\StartBatchJob;
use App\Domain\Batch\Actions\UpdateBatchItem;
use App\Domain\Batch\Events\BatchItemProcessed;
use App\Domain\Batch\Events\BatchJobCancelled;
use App\Domain\Batch\Events\BatchJobCompleted;
use App\Domain\Batch\Events\BatchJobCreated;
use App\Domain\Batch\Events\BatchJobStarted;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Keeps batch_jobs and batch_job_items in step with the batch events.
 *
 * It runs in the same request as the event, not on a queue: the service
 * returns the new batch row right after recording it, and the workflow reads
 * the items from these rows when it starts.
 */
class BatchProjector extends Projector
{
    public function onBatchJobCreated(BatchJobCreated $event): void
    {
        app(CreateBatchJob::class)($event);
    }

    public function onBatchJobStarted(BatchJobStarted $event): void
    {
        app(StartBatchJob::class)($event);
    }

    public function onBatchItemProcessed(BatchItemProcessed $event): void
    {
        app(UpdateBatchItem::class)($event);
    }

    public function onBatchJobCompleted(BatchJobCompleted $event): void
    {
        app(CompleteBatchJob::class)($event);
    }

    public function onBatchJobCancelled(BatchJobCancelled $event): void
    {
        app(CancelBatchJob::class)($event);
    }
}
