<?php

declare(strict_types=1);

namespace App\Domain\Account\Workflows;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Account\Models\Turnover;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Workflow\Activity;

/**
 * Activity to execute a single batch operation
 * This is a refactored version of BatchProcessingActivity that processes one operation at a time.
 */
class SingleBatchOperationActivity extends Activity
{
    /**
     * Execute a single batch operation.
     */
    public function execute(string $operation, string $batchId): array
    {
        $startTime = now();

        logger()->info(
            'Starting single batch operation',
            [
                'batch_id'   => $batchId,
                'operation'  => $operation,
                'start_time' => $startTime->toISOString(),
            ]
        );

        try {
            $result = $this->performOperation($operation, $batchId);

            $endTime = now();

            logger()->info(
                'Batch operation completed',
                [
                    'batch_id'         => $batchId,
                    'operation'        => $operation,
                    'duration_seconds' => $endTime->diffInSeconds($startTime),
                    'result'           => $result,
                ]
            );

            return [
                'operation'  => $operation,
                'status'     => 'success',
                'result'     => $result,
                'start_time' => $startTime->toISOString(),
                'end_time'   => $endTime->toISOString(),
            ];
        } catch (Throwable $th) {
            logger()->error(
                'Batch operation failed',
                [
                    'batch_id'  => $batchId,
                    'operation' => $operation,
                    'error'     => $th->getMessage(),
                ]
            );

            throw $th;
        }
    }

    private function performOperation(string $operation, string $batchId): array
    {
        switch ($operation) {
            case 'calculate_daily_turnover':
                return $this->calculateDailyTurnover();
            case 'generate_account_statements':
                return $this->generateAccountStatements();
            case 'process_interest_calculations':
                return $this->processInterestCalculations();
            case 'perform_compliance_checks':
                return $this->performComplianceChecks();
            case 'archive_old_transactions':
                return $this->archiveOldTransactions();
            case 'generate_regulatory_reports':
                return $this->generateRegulatoryReports();
            default:
                throw new InvalidArgumentException("Unknown batch operation: {$operation}");
        }
    }

    /**
     * Calculate daily turnover for all accounts.
     */
    private function calculateDailyTurnover(): array
    {
        $today = now()->startOfDay();

        // Store the date we're processing for potential reversal
        $processedData = [
            'date'      => $today->toDateString(),
            'turnovers' => [],
        ];

        // Calculate turnover for all accounts
        $accounts = Account::all();
        $processed = 0;

        foreach ($accounts as $account) {
            $dailyCredit = TransactionProjection::where('account_uuid', $account->uuid)
                ->whereDate('created_at', $today)
                ->where('type', 'credit')
                ->sum('amount');

            $dailyDebit = TransactionProjection::where('account_uuid', $account->uuid)
                ->whereDate('created_at', $today)
                ->where('type', 'debit')
                ->sum('amount');

            $turnover = Turnover::updateOrCreate(
                [
                    'account_uuid' => $account->uuid,
                    'date'         => $today->toDateString(),
                ],
                [
                    'credit' => $dailyCredit,
                    'debit'  => abs($dailyDebit),
                    'amount' => $dailyCredit - abs($dailyDebit),
                    'count'  => TransactionProjection::where('account_uuid', $account->uuid)
                        ->whereDate('created_at', $today)
                        ->count(),
                ]
            );

            // Track what we created/updated for potential reversal
            $processedData['turnovers'][] = [
                'account_uuid' => $account->uuid,
                'was_created'  => $turnover->wasRecentlyCreated,
            ];

            $processed++;
        }

        return [
            'operation'          => 'calculate_daily_turnover',
            'accounts_processed' => $processed,
            'date'               => $today->toDateString(),
            'processed_data'     => $processedData,
        ];
    }

    /**
     * Generate account statements.
     */
    private function generateAccountStatements(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $statementsGenerated = 0;
        $generatedFiles = [];

        // Generate monthly statements for all active accounts
        $accounts = Account::where('frozen', false)->get();

        foreach ($accounts as $account) {
            // Get transactions for the current month
            $transactions = TransactionProjection::where('account_uuid', $account->uuid)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($transactions->isEmpty()) {
                continue; // Skip accounts with no transactions
            }

            $statementData = [
                'account_uuid'     => $account->uuid,
                'account_name'     => $account->name,
                'statement_period' => [
                    'from' => $startOfMonth->toDateString(),
                    'to'   => $endOfMonth->toDateString(),
                ],
                'opening_balance'   => $account->balance - $transactions->sum('amount'),
                'closing_balance'   => $account->balance,
                'total_credits'     => $transactions->where('amount', '>', 0)->sum('amount'),
                'total_debits'      => $transactions->where('amount', '<', 0)->sum('amount'),
                'transaction_count' => $transactions->count(),
                'transactions'      => $transactions->map(
                    function ($transaction) {
                        return [
                            'date'        => $transaction->created_at->toDateString(),
                            'description' => $transaction->reference ?? 'Transaction',
                            'amount'      => $transaction->amount,
                            'balance'     => $transaction->balance_after ?? 0,
                        ];
                    }
                )->toArray(),
            ];

            // Store as JSON for auditing
            $filename = "statements/{$account->uuid}/" . $startOfMonth->format('Y-m') . '.json';
            Storage::disk('local')->put($filename, json_encode($statementData, JSON_PRETTY_PRINT));

            $generatedFiles[] = $filename;
            $statementsGenerated++;
        }

        return [
            'operation'            => 'generate_account_statements',
            'statements_generated' => $statementsGenerated,
            'period'               => $startOfMonth->format('M Y'),
            'storage_path'         => 'storage/app/statements/',
            'generated_files'      => $generatedFiles,
        ];
    }

    /**
     * Process interest calculations.
     */
    private function processInterestCalculations(): array
    {
        // Process interest for savings accounts
        $savingsAccounts = Account::where('frozen', false)
            ->where('balance', '>', 0)
            ->get();

        $accountsProcessed = 0;
        $totalInterestPaid = 0;
        $interestRate = 0.02; // 2% annual interest rate
        $dailyRate = $interestRate / 365;
        $interestTransactions = [];

        foreach ($savingsAccounts as $account) {
            // Calculate daily interest based on current balance
            $dailyInterest = $account->balance * $dailyRate;

            // Only apply interest if it's significant (> $0.01)
            if ($dailyInterest >= 1) { // 1 cent minimum
                $interestAmount = round($dailyInterest);

                $transactionId = Str::uuid();

                // Create interest transaction
                DB::table('transactions')->insert(
                    [
                        'uuid'          => $transactionId,
                        'account_uuid'  => $account->uuid,
                        'amount'        => $interestAmount,
                        'type'          => 'credit',
                        'reference'     => 'Daily Interest Payment',
                        'description'   => "Interest earned at {$interestRate}% APR",
                        'balance_after' => $account->balance + $interestAmount,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]
                );

                // Update account balance
                $account->update(['balance' => $account->balance + $interestAmount]);

                // Track for potential reversal
                $interestTransactions[] = [
                    'transaction_uuid' => $transactionId,
                    'account_uuid'     => $account->uuid,
                    'amount'           => $interestAmount,
                ];

                $totalInterestPaid += $interestAmount;
                $accountsProcessed++;
            }
        }

        return [
            'operation'             => 'process_interest_calculations',
            'accounts_processed'    => $accountsProcessed,
            'total_interest_paid'   => $totalInterestPaid,
            'interest_rate'         => $interestRate,
            'eligible_accounts'     => $savingsAccounts->count(),
            'interest_transactions' => $interestTransactions,
        ];
    }

    /**
     * Perform compliance checks.
     */
    private function performComplianceChecks(): array
    {
        $today = now()->startOfDay();
        $complianceFlags = [];

        // Check for large transactions (> $10,000)
        $largeTransactions = TransactionProjection::where('amount', '>', 1000000) // > $10,000 in cents
            ->whereDate('created_at', $today)
            ->with('account.user')
            ->get();

        $complianceFlags['large_transactions'] = $largeTransactions->map(
            function ($transaction) {
                return [
                    'transaction_uuid' => $transaction->uuid,
                    'account_uuid'     => $transaction->account_uuid,
                    'amount'           => $transaction->amount,
                    'user_email'       => $transaction->account->user->email ?? 'Unknown',
                    'flag_reason'      => 'Transaction exceeds $10,000 threshold',
                ];
            }
        )->toArray();

        // Check for multiple transactions from same account in short time
        $rapidTransactions = DB::select(
            '
            SELECT account_uuid, COUNT(*) as transaction_count, user_uuid
            FROM transactions t
            JOIN accounts a ON t.account_uuid = a.uuid
            WHERE t.created_at >= ?
            GROUP BY account_uuid, user_uuid
            HAVING COUNT(*) > 10
        ',
            [$today]
        );

        $complianceFlags['rapid_transactions'] = collect($rapidTransactions)->map(
            function ($item) {
                return [
                    'account_uuid'      => $item->account_uuid,
                    'transaction_count' => $item->transaction_count,
                    'user_uuid'         => $item->user_uuid,
                    'flag_reason'       => 'More than 10 transactions in one day',
                ];
            }
        )->toArray();

        // Check for unusual account balance patterns
        $highBalanceAccounts = Account::where('balance', '>', 100000000) // > $1M
            ->with('user')
            ->get();

        $complianceFlags['high_balance_accounts'] = $highBalanceAccounts->map(
            function ($account) {
                return [
                    'account_uuid' => $account->uuid,
                    'balance'      => $account->balance,
                    'user_email'   => $account->user->email ?? 'Unknown',
                    'flag_reason'  => 'Account balance exceeds $1,000,000',
                ];
            }
        )->toArray();

        // Check for round-number transactions (possible structuring)
        $roundTransactions = TransactionProjection::whereIn(
            'amount',
            [
                1000000, 900000, 800000, 700000, 600000, 500000, // $10k, $9k, etc.
            ]
        )
            ->whereDate('created_at', $today)
            ->count();

        $complianceFlags['round_transactions'] = [
            'count'       => $roundTransactions,
            'flag_reason' => 'Round-number transactions may indicate structuring',
        ];

        $totalFlags = count($complianceFlags['large_transactions']) +
                     count($complianceFlags['rapid_transactions']) +
                     count($complianceFlags['high_balance_accounts']) +
                     ($roundTransactions > 5 ? 1 : 0);

        // Store compliance report
        $reportData = [
            'date'         => $today->toDateString(),
            'total_flags'  => $totalFlags,
            'flags'        => $complianceFlags,
            'generated_at' => now()->toISOString(),
        ];

        $reportFilename = "compliance/daily_report_{$today->format('Y-m-d')}.json";
        Storage::disk('local')->put(
            $reportFilename,
            json_encode($reportData, JSON_PRETTY_PRINT)
        );

        return [
            'operation'             => 'perform_compliance_checks',
            'total_flags'           => $totalFlags,
            'large_transactions'    => count($complianceFlags['large_transactions']),
            'rapid_transactions'    => count($complianceFlags['rapid_transactions']),
            'high_balance_accounts' => count($complianceFlags['high_balance_accounts']),
            'round_transactions'    => $roundTransactions,
            'report_file'           => $reportFilename,
        ];
    }

    /**
     * Archive old transactions.
     */
    private function archiveOldTransactions(): array
    {
        // Archive transactions older than 7 years
        $cutoffDate = now()->subYears(7);

        // Get transactions to be archived before updating
        $transactionsToArchive = TransactionProjection::where('created_at', '<', $cutoffDate)
            ->where(
                function ($query) {
                    $query->whereNull('archived')
                        ->orWhere('archived', false);
                }
            )
            ->pluck('uuid')
            ->toArray();

        $archivedCount = TransactionProjection::whereIn('uuid', $transactionsToArchive)
            ->update(['archived' => true]);

        return [
            'operation'             => 'archive_old_transactions',
            'transactions_archived' => $archivedCount,
            'cutoff_date'           => $cutoffDate->toDateString(),
            'archived_uuids'        => $transactionsToArchive,
        ];
    }

    /**
     * Generate regulatory reports.
     */
    private function generateRegulatoryReports(): array
    {
        $today = now();
        $reportsGenerated = [];
        $generatedFiles = [];

        // Daily Transaction Summary Report
        $dailyStats = [
            'total_transactions' => TransactionProjection::whereDate('created_at', $today)->count(),
            'total_volume'       => TransactionProjection::whereDate('created_at', $today)->sum('amount'),
            'total_credits'      => TransactionProjection::whereDate('created_at', $today)->where('amount', '>', 0)->sum('amount'),
            'total_debits'       => abs(TransactionProjection::whereDate('created_at', $today)->where('amount', '<', 0)->sum('amount')),
            'unique_accounts'    => TransactionProjection::whereDate('created_at', $today)->distinct('account_uuid')->count(),
        ];

        $dailyReportFile = "regulatory/daily_transaction_summary_{$today->format('Y-m-d')}.json";
        Storage::disk('local')->put(
            $dailyReportFile,
            json_encode($dailyStats, JSON_PRETTY_PRINT)
        );
        $reportsGenerated[] = 'daily_transaction_summary';
        $generatedFiles[] = $dailyReportFile;

        // Large Transaction Report (CTR - Currency Transaction Report)
        $largeTransactions = TransactionProjection::where('amount', '>', 1000000) // > $10,000
            ->whereDate('created_at', $today)
            ->with('account.user')
            ->get()
            ->map(
                function ($transaction) {
                    return [
                        'transaction_uuid' => $transaction->uuid,
                        'account_uuid'     => $transaction->account_uuid,
                        'amount'           => $transaction->amount / 100, // Convert to dollars
                        'transaction_date' => $transaction->created_at->toDateString(),
                        'customer_name'    => $transaction->account->user->name ?? 'Unknown',
                        'customer_email'   => $transaction->account->user->email ?? 'Unknown',
                        'transaction_type' => $transaction->amount > 0 ? 'Credit' : 'Debit',
                    ];
                }
            );

        if ($largeTransactions->isNotEmpty()) {
            $ctrFile = "regulatory/ctr_report_{$today->format('Y-m-d')}.json";
            Storage::disk('local')->put(
                $ctrFile,
                json_encode($largeTransactions->toArray(), JSON_PRETTY_PRINT)
            );
            $reportsGenerated[] = 'currency_transaction_report';
            $generatedFiles[] = $ctrFile;
        }

        // Suspicious Activity Report (SAR) candidates
        $suspiciousActivities = [];

        // Check for structuring (multiple transactions just under $10k)
        $structuringCandidates = DB::select(
            '
            SELECT account_uuid, COUNT(*) as transaction_count, SUM(amount) as total_amount
            FROM transactions
            WHERE amount BETWEEN 900000 AND 999900
            AND created_at >= ?
            GROUP BY account_uuid
            HAVING COUNT(*) >= 3
        ',
            [$today->startOfDay()]
        );

        foreach ($structuringCandidates as $candidate) {
            $suspiciousActivities[] = [
                'account_uuid'      => $candidate->account_uuid,
                'activity_type'     => 'Potential Structuring',
                'description'       => 'Multiple transactions just under $10k threshold',
                'transaction_count' => $candidate->transaction_count,
                'total_amount'      => $candidate->total_amount / 100,
            ];
        }

        if (! empty($suspiciousActivities)) {
            $sarFile = "regulatory/sar_candidates_{$today->format('Y-m-d')}.json";
            Storage::disk('local')->put(
                $sarFile,
                json_encode($suspiciousActivities, JSON_PRETTY_PRINT)
            );
            $reportsGenerated[] = 'suspicious_activity_candidates';
            $generatedFiles[] = $sarFile;
        }

        // Monthly Summary (if it's end of month)
        if ($today->isLastOfMonth()) {
            $monthlyStats = [
                'month'                    => $today->format('Y-m'),
                'total_accounts'           => Account::count(),
                'active_accounts'          => Account::where('frozen', false)->count(),
                'total_transactions'       => TransactionProjection::whereMonth('created_at', $today)->count(),
                'total_volume'             => TransactionProjection::whereMonth('created_at', $today)->sum('amount') / 100,
                'average_transaction_size' => TransactionProjection::whereMonth('created_at', $today)->avg('amount') / 100,
                'largest_transaction'      => TransactionProjection::whereMonth('created_at', $today)->max('amount') / 100,
            ];

            $monthlyFile = "regulatory/monthly_summary_{$today->format('Y-m')}.json";
            Storage::disk('local')->put(
                $monthlyFile,
                json_encode($monthlyStats, JSON_PRETTY_PRINT)
            );
            $reportsGenerated[] = 'monthly_summary';
            $generatedFiles[] = $monthlyFile;
        }

        return [
            'operation'                   => 'generate_regulatory_reports',
            'reports_generated'           => $reportsGenerated,
            'daily_transaction_count'     => $dailyStats['total_transactions'],
            'large_transactions_count'    => $largeTransactions->count(),
            'suspicious_activities_count' => count($suspiciousActivities),
            'generated_files'             => $generatedFiles,
        ];
    }
}
