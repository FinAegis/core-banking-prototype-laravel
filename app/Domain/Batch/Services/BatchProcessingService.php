<?php

declare(strict_types=1);

namespace App\Domain\Batch\Services;

use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Domain\Batch\DataObjects\BatchJob as BatchJobData;
use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Models\BatchJobItem;
use App\Domain\Batch\Workflows\ProcessBatchJobWorkflow;
use App\Http\Middleware\TransactionRateLimitMiddleware;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Workflow\WorkflowStub;

class BatchProcessingService
{
    public function __construct(
        private readonly BatchTransferGuard $guard,
    ) {
    }

    /**
     * Create a new batch job.
     *
     * Every entry point (web form, GraphQL, retry) comes through here, so the
     * items are checked here: each item gets the checks of a single transfer,
     * the source accounts must belong to the user, and every item counts
     * against the user's transfer limits as if it were sent alone.
     *
     * @param  array<mixed>  $items
     *
     * @throws ValidationException when an item is invalid (HTTP 422)
     * @throws AuthorizationException when an item spends from another user's account (HTTP 403)
     * @throws ThrottleRequestsException when the items pass the user's transfer limits (HTTP 429)
     */
    public function createBatchJob(
        string $userUuid,
        string $name,
        string $type,
        array $items,
        ?string $scheduledAt = null,
        array $metadata = []
    ): BatchJob {
        /** @var User|null $user */
        $user = User::query()->where('uuid', $userUuid)->first();
        if ($user === null) {
            throw new AuthorizationException('Unknown batch owner.');
        }

        $items = $this->guard->validate($user, $type, $items);

        $refusal = TransactionRateLimitMiddleware::consumeMany(
            (int) $user->id,
            'transfer',
            array_map(fn (array $item): string => $item['amount'], $items)
        );
        if ($refusal !== null) {
            throw new ThrottleRequestsException($refusal);
        }

        // Create batch job data object
        $batchJobData = BatchJobData::create(
            userUuid: $userUuid,
            name: $name,
            type: $type,
            items: $items,
            scheduledAt: $scheduledAt,
            metadata: $metadata
        );

        // Create batch job through event sourcing
        BatchAggregate::retrieve($batchJobData->uuid)
            ->createBatchJob($batchJobData)
            ->persist();

        /** @var BatchJob|null $batchJob */
        $batchJob = BatchJob::query()->where('uuid', $batchJobData->uuid)->first();
        if ($batchJob === null) {
            throw new RuntimeException("Batch job {$batchJobData->uuid} was not recorded.");
        }

        // If not scheduled, process immediately
        if ($scheduledAt === null || $scheduledAt === '' || Carbon::parse($scheduledAt)->lessThanOrEqualTo(now())) {
            $this->processBatch($batchJobData->uuid);
        }

        return $batchJob;
    }

    /**
     * Process a batch job.
     */
    public function processBatch(string $batchJobUuid): void
    {
        $workflow = WorkflowStub::make(ProcessBatchJobWorkflow::class);
        $workflow->start($batchJobUuid);
    }

    /**
     * Cancel a batch job.
     */
    public function cancelBatchJob(string $batchJobUuid, string $reason): void
    {
        BatchAggregate::retrieve($batchJobUuid)
            ->cancelBatchJob($reason)
            ->persist();
    }

    /**
     * Retry the failed items of a batch job as a new batch job, which goes
     * through the same checks and limits as any new batch. A failed item moved
     * no money, and the new batch has its own transfer ids. The retried items
     * are then marked "retried", so a second retry of the same batch cannot
     * send them again; a lock keeps two retries at the same time from both
     * taking them. Returns null when there is nothing to retry.
     */
    public function retryFailedItems(string $batchJobUuid): ?BatchJob
    {
        /** @var BatchJob|null $batchJob */
        $batchJob = BatchJob::where('uuid', $batchJobUuid)->first();

        if (! $batchJob) {
            throw new InvalidArgumentException("Batch job not found: {$batchJobUuid}");
        }

        $lock = Cache::lock("batch_retry:{$batchJobUuid}", 60);
        if (! $lock->get()) {
            throw new ThrottleRequestsException('This batch is already being retried.');
        }

        try {
            $failed = $batchJob->items()->where('status', 'failed')->orderBy('sequence')->get();

            if ($failed->isEmpty()) {
                return null;
            }

            $retry = $this->createBatchJob(
                userUuid: (string) $batchJob->user_uuid,
                name: $batchJob->name . ' (Retry)',
                type: (string) $batchJob->type,
                items: $failed->map(fn (BatchJobItem $item): mixed => $item->data)->all(),
                metadata: [
                    'original_batch_job_uuid' => $batchJobUuid,
                    'is_retry'                => true,
                ]
            );

            BatchJobItem::query()
                ->whereKey($failed->modelKeys())
                ->where('status', 'failed')
                ->update(['status' => 'retried']);

            return $retry;
        } finally {
            $lock->release();
        }
    }
}
