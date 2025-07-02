<?php

namespace App\Domain\Batch\Aggregates;

use App\Domain\Batch\DataObjects\BatchJob;
use App\Domain\Batch\Events\BatchJobCreated;
use App\Domain\Batch\Events\BatchJobStarted;
use App\Domain\Batch\Events\BatchItemProcessed;
use App\Domain\Batch\Events\BatchJobCompleted;
use App\Domain\Batch\Events\BatchJobCancelled;
use App\Domain\Batch\Repositories\BatchRepository;
use App\Domain\Batch\Repositories\BatchSnapshotRepository;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class BatchAggregate extends AggregateRoot
{
    protected int $processedItems = 0;
    protected int $failedItems = 0;
    protected string $status = 'pending';
    protected array $items = [];
    
    /**
     * @return BatchRepository
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getStoredEventRepository(): BatchRepository
    {
        return app()->make(BatchRepository::class);
    }

    /**
     * @return BatchSnapshotRepository
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getSnapshotRepository(): BatchSnapshotRepository
    {
        return app()->make(BatchSnapshotRepository::class);
    }

    /**
     * @param BatchJob $batchJob
     * @return $this
     */
    public function createBatchJob(BatchJob $batchJob): static
    {
        $this->recordThat(
            new BatchJobCreated($batchJob)
        );

        return $this;
    }
    
    /**
     * @return $this
     */
    public function startBatchJob(): static
    {
        if ($this->status !== 'pending') {
            throw new \InvalidArgumentException('Can only start pending batch jobs');
        }
        
        $this->recordThat(
            new BatchJobStarted(now()->toIso8601String())
        );

        return $this;
    }
    
    /**
     * @param int $itemIndex
     * @param string $status
     * @param array $result
     * @param string|null $errorMessage
     * @return $this
     */
    public function processBatchItem(int $itemIndex, string $status, array $result = [], ?string $errorMessage = null): static
    {
        if ($this->status !== 'processing') {
            throw new \InvalidArgumentException('Can only process items for processing batch jobs');
        }
        
        $this->recordThat(
            new BatchItemProcessed($itemIndex, $status, $result, $errorMessage)
        );

        return $this;
    }
    
    /**
     * @return $this
     */
    public function completeBatchJob(): static
    {
        if ($this->status !== 'processing') {
            throw new \InvalidArgumentException('Can only complete processing batch jobs');
        }
        
        $finalStatus = 'completed';
        if ($this->failedItems === count($this->items)) {
            $finalStatus = 'failed';
        } elseif ($this->failedItems > 0) {
            $finalStatus = 'completed_with_errors';
        }
        
        $this->recordThat(
            new BatchJobCompleted(
                now()->toIso8601String(),
                $this->processedItems,
                $this->failedItems,
                $finalStatus
            )
        );

        return $this;
    }
    
    /**
     * @param string $reason
     * @return $this
     */
    public function cancelBatchJob(string $reason): static
    {
        if (!in_array($this->status, ['pending', 'processing'])) {
            throw new \InvalidArgumentException('Can only cancel pending or processing batch jobs');
        }
        
        $this->recordThat(
            new BatchJobCancelled($reason, now()->toIso8601String())
        );

        return $this;
    }
    
    // Apply methods for events
    
    protected function applyBatchJobCreated(BatchJobCreated $event): void
    {
        $this->items = $event->batchJob->items;
        $this->status = 'pending';
    }
    
    protected function applyBatchJobStarted(BatchJobStarted $event): void
    {
        $this->status = 'processing';
    }
    
    protected function applyBatchItemProcessed(BatchItemProcessed $event): void
    {
        $this->processedItems++;
        if ($event->status === 'failed') {
            $this->failedItems++;
        }
    }
    
    protected function applyBatchJobCompleted(BatchJobCompleted $event): void
    {
        $this->status = $event->finalStatus;
    }
    
    protected function applyBatchJobCancelled(BatchJobCancelled $event): void
    {
        $this->status = 'cancelled';
    }
}