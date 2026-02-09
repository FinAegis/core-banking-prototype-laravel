<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\Models\Transaction;
use App\Domain\Fraud\Jobs\ProcessAnomalyBatchJob;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScanAnomaliesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'fraud:scan-anomalies
                            {--hours=24 : How many hours back to scan}
                            {--chunk=100 : Number of transactions per batch job}
                            {--sync : Run synchronously instead of dispatching jobs}';

    /**
     * @var string
     */
    protected $description = 'Batch scan recent transactions for anomaly detection';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $chunkSize = (int) $this->option('chunk');
        $sync = $this->option('sync');
        $pipelineRunId = Str::uuid()->toString();

        $this->info("Anomaly Detection Batch Scan (pipeline: {$pipelineRunId})");
        $this->info("Scanning transactions from the last {$hours} hours in chunks of {$chunkSize}");

        $query = Transaction::where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No transactions found in the specified time range.');

            return Command::SUCCESS;
        }

        $this->info("Found {$total} transactions to scan.");

        $dispatched = 0;

        $query->select('id')->chunkById($chunkSize, function ($transactions) use ($pipelineRunId, $sync, &$dispatched) {
            $ids = $transactions->pluck('id')->all();

            if ($sync) {
                ProcessAnomalyBatchJob::dispatchSync($ids, $pipelineRunId);
            } else {
                ProcessAnomalyBatchJob::dispatch($ids, $pipelineRunId);
            }

            $dispatched++;
        });

        $this->info("Dispatched {$dispatched} batch jobs covering {$total} transactions.");
        $this->info('Pipeline run ID: ' . $pipelineRunId);

        return Command::SUCCESS;
    }
}
