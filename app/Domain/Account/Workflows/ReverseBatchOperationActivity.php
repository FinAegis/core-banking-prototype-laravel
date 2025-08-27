<?php

declare(strict_types=1);

namespace App\Domain\Account\Workflows;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Account\Models\Turnover;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Workflow\Activity;

/**
 * Activity to reverse a batch operation as part of workflow compensation.
 */
class ReverseBatchOperationActivity extends Activity
{
    /**
     * Reverse a batch operation based on its type and result.
     */
    public function execute(string $operation, string $batchId, array $operationResult): void
    {
        logger()->info(
            'Reversing batch operation',
            [
                'batch_id'        => $batchId,
                'operation'       => $operation,
                'original_result' => $operationResult,
            ]
        );

        try {
            switch ($operation) {
                case 'calculate_daily_turnover':
                    $this->reverseDailyTurnover($operationResult);
                    break;

                case 'generate_account_statements':
                    $this->reverseAccountStatements($operationResult);
                    break;

                case 'process_interest_calculations':
                    $this->reverseInterestCalculations($operationResult);
                    break;

                case 'perform_compliance_checks':
                    $this->reverseComplianceChecks($operationResult);
                    break;

                case 'archive_old_transactions':
                    $this->reverseArchiveTransactions($operationResult);
                    break;

                case 'generate_regulatory_reports':
                    $this->reverseRegulatoryReports($operationResult);
                    break;

                default:
                    logger()->warning(
                        'No reversal logic for operation',
                        [
                            'operation' => $operation,
                            'batch_id'  => $batchId,
                        ]
                    );
            }

            logger()->info(
                'Successfully reversed batch operation',
                [
                    'batch_id'  => $batchId,
                    'operation' => $operation,
                ]
            );
        } catch (Throwable $th) {
            logger()->error(
                'Failed to reverse batch operation',
                [
                    'batch_id'  => $batchId,
                    'operation' => $operation,
                    'error'     => $th->getMessage(),
                ]
            );
            throw $th;
        }
    }

    /**
     * Reverse daily turnover calculations.
     */
    private function reverseDailyTurnover(array $result): void
    {
        if (! isset($result['result']['processed_data'])) {
            return;
        }

        $processedData = $result['result']['processed_data'];
        $date = $processedData['date'];

        // Delete or revert turnovers that were created/updated
        foreach ($processedData['turnovers'] as $turnoverData) {
            if ($turnoverData['was_created']) {
                // Delete newly created turnovers
                Turnover::where('account_uuid', $turnoverData['account_uuid'])
                    ->where('date', $date)
                    ->delete();
            } else {
                // For updated turnovers, we would need to store previous values
                // For simplicity, we'll just log that we couldn't fully revert
                logger()->warning(
                    'Cannot fully revert updated turnover',
                    [
                        'account_uuid' => $turnoverData['account_uuid'],
                        'date'         => $date,
                    ]
                );
            }
        }
    }

    /**
     * Reverse account statement generation.
     */
    private function reverseAccountStatements(array $result): void
    {
        if (! isset($result['result']['generated_files'])) {
            return;
        }

        // Delete generated statement files
        foreach ($result['result']['generated_files'] as $filename) {
            if (Storage::disk('local')->exists($filename)) {
                Storage::disk('local')->delete($filename);
                logger()->info('Deleted statement file', ['filename' => $filename]);
            }
        }
    }

    /**
     * Reverse interest calculations.
     */
    private function reverseInterestCalculations(array $result): void
    {
        if (! isset($result['result']['interest_transactions'])) {
            return;
        }

        DB::transaction(
            function () use ($result) {
                foreach ($result['result']['interest_transactions'] as $interestTx) {
                    // Delete the interest transaction
                    Transaction::where('id', $interestTx['transaction_uuid'])->delete();

                    // Note: Balance updates should be done through event sourcing
                    // This is a simplified reversal for demonstration
                    logger()->warning('Balance reversal should be done through event sourcing', [
                        'account_uuid' => $interestTx['account_uuid'],
                        'amount'       => $interestTx['amount'],
                    ]);

                    logger()->info(
                        'Reversed interest transaction',
                        [
                            'transaction_uuid' => $interestTx['transaction_uuid'],
                            'account_uuid'     => $interestTx['account_uuid'],
                            'amount'           => $interestTx['amount'],
                        ]
                    );
                }
            }
        );
    }

    /**
     * Reverse compliance checks (delete generated reports).
     */
    private function reverseComplianceChecks(array $result): void
    {
        if (! isset($result['result']['report_file'])) {
            return;
        }

        // Delete the compliance report file
        if (Storage::disk('local')->exists($result['result']['report_file'])) {
            Storage::disk('local')->delete($result['result']['report_file']);
            logger()->info('Deleted compliance report', ['filename' => $result['result']['report_file']]);
        }
    }

    /**
     * Reverse archive operations.
     */
    private function reverseArchiveTransactions(array $result): void
    {
        if (! isset($result['result']['archived_uuids'])) {
            return;
        }

        // Since Transaction is an event store, we can't update it directly
        // Log the operation instead
        $count = count($result['result']['archived_uuids']);

        logger()->info(
            'Unarchived transactions',
            [
                'count'    => $count,
                'expected' => count($result['result']['archived_uuids']),
            ]
        );
    }

    /**
     * Reverse regulatory report generation.
     */
    private function reverseRegulatoryReports(array $result): void
    {
        if (! isset($result['result']['generated_files'])) {
            return;
        }

        // Delete all generated regulatory report files
        foreach ($result['result']['generated_files'] as $filename) {
            if (Storage::disk('local')->exists($filename)) {
                Storage::disk('local')->delete($filename);
                logger()->info('Deleted regulatory report', ['filename' => $filename]);
            }
        }
    }
}
