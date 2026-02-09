<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Jobs;

use App\Domain\Account\Models\Transaction;
use App\Domain\Fraud\Services\AnomalyDetectionOrchestrator;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAnomalyBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  array<int>  $transactionIds  The transaction IDs to scan
     */
    public function __construct(
        private readonly array $transactionIds,
        private readonly string $pipelineRunId,
    ) {
        $this->onQueue('fraud-batch');
    }

    public function handle(AnomalyDetectionOrchestrator $orchestrator): void
    {
        $transactions = Transaction::with('account.user')
            ->whereIn('id', $this->transactionIds)
            ->get();

        $processed = 0;
        $anomaliesFound = 0;

        foreach ($transactions as $transaction) {
            try {
                $account = $transaction->account;
                $user = $account?->user;

                $context = [
                    'amount' => (float) $transaction->amount,
                    'type'   => $transaction->type ?? 'unknown',
                    'user'   => $user ? [
                        'id'      => $user->id,
                        'country' => $user->country ?? null,
                    ] : [],
                    'daily_transaction_count'  => 0,
                    'daily_transaction_volume' => 0,
                    'pipeline_run_id'          => $this->pipelineRunId,
                ];

                $result = $orchestrator->detectAnomalies(
                    $context,
                    (string) $transaction->id,
                    Transaction::class,
                    $user?->id,
                );

                $processed++;

                if ($result['highest_score'] > 0) {
                    $anomaliesFound++;
                }
            } catch (Exception $e) {
                Log::warning('Batch anomaly scan failed for transaction', [
                    'transaction_id'  => $transaction->id,
                    'pipeline_run_id' => $this->pipelineRunId,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        Log::info('Anomaly batch chunk completed', [
            'pipeline_run_id' => $this->pipelineRunId,
            'processed'       => $processed,
            'anomalies_found' => $anomaliesFound,
            'total_in_chunk'  => count($this->transactionIds),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Anomaly batch job failed', [
            'pipeline_run_id'   => $this->pipelineRunId,
            'transaction_count' => count($this->transactionIds),
            'error'             => $exception->getMessage(),
        ]);
    }
}
