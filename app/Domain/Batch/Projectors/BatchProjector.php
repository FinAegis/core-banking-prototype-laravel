<?php

namespace App\Domain\Batch\Projectors;

use App\Domain\Batch\Actions\CreateBatchJob;
use App\Domain\Batch\Actions\StartBatchJob;
use App\Domain\Batch\Actions\UpdateBatchItem;
use App\Domain\Batch\Actions\CompleteBatchJob;
use App\Domain\Batch\Actions\CancelBatchJob;
use App\Domain\Batch\Events\BatchJobCreated;
use App\Domain\Batch\Events\BatchJobStarted;
use App\Domain\Batch\Events\BatchItemProcessed;
use App\Domain\Batch\Events\BatchJobCompleted;
use App\Domain\Batch\Events\BatchJobCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class BatchProjector extends Projector implements ShouldQueue
{
    /**
     * @param BatchJobCreated $event
     * @return void
     */
    public function onBatchJobCreated(BatchJobCreated $event): void
    {
        app(CreateBatchJob::class)($event);
    }
    
    /**
     * @param BatchJobStarted $event
     * @return void
     */
    public function onBatchJobStarted(BatchJobStarted $event): void
    {
        app(StartBatchJob::class)($event);
    }
    
    /**
     * @param BatchItemProcessed $event
     * @return void
     */
    public function onBatchItemProcessed(BatchItemProcessed $event): void
    {
        app(UpdateBatchItem::class)($event);
    }
    
    /**
     * @param BatchJobCompleted $event
     * @return void
     */
    public function onBatchJobCompleted(BatchJobCompleted $event): void
    {
        app(CompleteBatchJob::class)($event);
    }
    
    /**
     * @param BatchJobCancelled $event
     * @return void
     */
    public function onBatchJobCancelled(BatchJobCancelled $event): void
    {
        app(CancelBatchJob::class)($event);
    }
}