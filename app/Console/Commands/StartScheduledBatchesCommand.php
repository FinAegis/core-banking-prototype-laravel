<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Batch\Services\BatchProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Starts the batch jobs whose scheduled time has come. A batch created with a
 * time in the future is saved as pending; this command (every minute) is what
 * starts it. It does nothing while batch processing is off.
 */
class StartScheduledBatchesCommand extends Command
{
    protected $signature = 'batch:start-scheduled';

    protected $description = 'Start the pending batch jobs whose scheduled time has come';

    public function handle(BatchProcessingService $batches): int
    {
        if (! config('ledger.batch_processing_enabled')) {
            $this->info('Batch processing is off; nothing started.');

            return self::SUCCESS;
        }

        $result = $batches->startDueScheduledBatches();

        foreach ($result['too_late'] as $uuid) {
            // Warn once per batch, not every minute.
            if (Cache::add("batch-job-too-late:{$uuid}", true, now()->addDays(30))) {
                Log::warning('A scheduled batch fell due too long ago and was not started.', [
                    'batch_job_uuid' => $uuid,
                    'window_hours'   => BatchProcessingService::SCHEDULED_START_WINDOW_HOURS,
                ]);
            }
        }

        $this->info(sprintf(
            'Started %d scheduled batch(es); %d left for an operator.',
            count($result['started']),
            count($result['too_late'])
        ));

        return self::SUCCESS;
    }
}
