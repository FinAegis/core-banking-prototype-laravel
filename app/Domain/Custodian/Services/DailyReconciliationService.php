<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Custodian\Events\ReconciliationCompleted;
use App\Domain\Custodian\Events\ReconciliationDiscrepancyFound;
use App\Domain\Custodian\Mail\ReconciliationReport;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DailyReconciliationService
{
    private array $reconciliationResults = [];

    private array $discrepancies = [];

    public function __construct(
        private readonly BalanceSynchronizationService $syncService,
        private readonly CustodianRegistry $custodianRegistry
    ) {
    }

    /**
     * Perform daily reconciliation for all accounts.
     */
    public function performDailyReconciliation(): array
    {
        Log::info('Starting daily reconciliation process');

        $this->reconciliationResults = [
            'date'                     => now()->toDateString(),
            'start_time'               => now(),
            'accounts_checked'         => 0,
            'discrepancies_found'      => 0,
            'total_discrepancy_amount' => 0,
            'status'                   => 'in_progress',
        ];

        try {
            // Step 1: Synchronize all balances
            $syncResults = $this->syncService->synchronizeAllBalances();

            // Step 2: Perform reconciliation checks
            $this->performReconciliationChecks();

            // Step 3: Send notifications if discrepancies found
            if (! empty($this->discrepancies)) {
                $this->handleDiscrepancies();
            }

            $this->reconciliationResults['end_time'] = now();
            $this->reconciliationResults['duration_minutes'] =
                $this->reconciliationResults['end_time']->diffInMinutes($this->reconciliationResults['start_time']);
            $this->reconciliationResults['status'] = 'completed';

            // Step 4: Generate reconciliation report
            $report = $this->generateReconciliationReport();

            // Fire reconciliation completed event
            event(
                new ReconciliationCompleted(
                    date: $this->reconciliationResults['date'],
                    results: $this->reconciliationResults,
                    discrepancies: $this->discrepancies
                )
            );

            return $report;
        } catch (Exception $e) {
            Log::error(
                'Daily reconciliation failed',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            $this->reconciliationResults['status'] = 'failed';
            $this->reconciliationResults['error'] = $e->getMessage();

            throw $e;
        }
    }

    /**
     * Perform reconciliation checks.
     */
    private function performReconciliationChecks(): void
    {
        $accounts = Account::with(['balances', 'custodianAccounts'])->get();

        foreach ($accounts as $account) {
            $this->reconciliationResults['accounts_checked']++;

            // Check internal vs external balances
            $this->checkAccountBalances($account);

            // Check for orphaned balances
            $this->checkOrphanedBalances($account);

            // Check for stale data
            $this->checkStaleData($account);
        }
    }

    /**
     * Check internal vs external balances for an account.
     */
    private function checkAccountBalances(Account $account): void
    {
        $internalBalances = $this->getInternalBalances($account);
        $externalBalances = $this->getExternalBalances($account);

        foreach ($internalBalances as $assetCode => $internalAmount) {
            $externalAmount = $externalBalances[$assetCode] ?? 0;

            if ($internalAmount !== $externalAmount) {
                $discrepancy = [
                    'account_uuid'     => $account->uuid,
                    'asset_code'       => $assetCode,
                    'internal_balance' => $internalAmount,
                    'external_balance' => $externalAmount,
                    'difference'       => abs($internalAmount - $externalAmount),
                    'type'             => 'balance_mismatch',
                    'detected_at'      => now(),
                ];

                $this->discrepancies[] = $discrepancy;
                $this->reconciliationResults['discrepancies_found']++;
                $this->reconciliationResults['total_discrepancy_amount'] += $discrepancy['difference'];

                // Fire discrepancy event
                event(new ReconciliationDiscrepancyFound($discrepancy));

                Log::warning('Reconciliation discrepancy found', $discrepancy);
            }
        }
    }

    /**
     * Get internal balances for an account.
     */
    private function getInternalBalances(Account $account): array
    {
        $balances = [];

        foreach ($account->balances as $balance) {
            $balances[$balance->asset_code] = $balance->balance;
        }

        return $balances;
    }

    /**
     * Get external balances from all custodians.
     */
    private function getExternalBalances(Account $account): array
    {
        $aggregatedBalances = [];

        foreach ($account->custodianAccounts as $custodianAccount) {
            if ($custodianAccount->status !== 'active') {
                continue;
            }

            try {
                $connector = $this->custodianRegistry->getConnector($custodianAccount->custodian_name);

                if (! $connector->isAvailable()) {
                    Log::warning(
                        'Custodian not available for reconciliation',
                        [
                            'custodian' => $custodianAccount->custodian_name,
                            'account'   => $account->uuid,
                        ]
                    );

                    continue;
                }

                $accountInfo = $connector->getAccountInfo($custodianAccount->custodian_account_id);

                foreach ($accountInfo->balances as $assetCode => $amount) {
                    $aggregatedBalances[$assetCode] = ($aggregatedBalances[$assetCode] ?? 0) + $amount;
                }
            } catch (Exception $e) {
                Log::error(
                    'Failed to get external balance',
                    [
                        'custodian' => $custodianAccount->custodian_name,
                        'account'   => $account->uuid,
                        'error'     => $e->getMessage(),
                    ]
                );
            }
        }

        return $aggregatedBalances;
    }

    /**
     * Check for orphaned balances.
     */
    private function checkOrphanedBalances(Account $account): void
    {
        // Check for balances without corresponding custodian accounts
        if ($account->balances->isNotEmpty() && $account->custodianAccounts->isEmpty()) {
            $this->discrepancies[] = [
                'account_uuid' => $account->uuid,
                'type'         => 'orphaned_balance',
                'message'      => 'Account has balances but no custodian accounts',
                'detected_at'  => now(),
            ];

            $this->reconciliationResults['discrepancies_found']++;
        }
    }

    /**
     * Check for stale data.
     */
    private function checkStaleData(Account $account): void
    {
        // TODO: Implement stale data check when last_synced_at column is added
        // For now, we'll skip this check

        // $staleCutoff = now()->subHours(24);
        //
        // foreach ($account->custodianAccounts as $custodianAccount) {
        //     if ($custodianAccount->last_synced_at &&
        //         $custodianAccount->last_synced_at->isBefore($staleCutoff)) {
        //
        //         $this->discrepancies[] = [
        //             'account_uuid' => $account->uuid,
        //             'custodian_id' => $custodianAccount->custodian_name,
        //             'type' => 'stale_data',
        //             'message' => 'Custodian account not synced in 24 hours',
        //             'last_synced_at' => $custodianAccount->last_synced_at,
        //             'detected_at' => now(),
        //         ];
        //
        //         $this->reconciliationResults['discrepancies_found']++;
        //     }
        // }
    }

    /**
     * Generate reconciliation report.
     */
    private function generateReconciliationReport(): array
    {
        $report = [
            'summary'         => $this->reconciliationResults,
            'discrepancies'   => $this->discrepancies,
            'recommendations' => $this->generateRecommendations(),
            'generated_at'    => now(),
        ];

        // Store report in database or file system
        $this->storeReport($report);

        return $report;
    }

    /**
     * Generate recommendations based on findings.
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];

        if ($this->reconciliationResults['discrepancies_found'] > 0) {
            $recommendations[] = 'Investigate and resolve balance discrepancies immediately';
        }

        $staleCounts = collect($this->discrepancies)
            ->where('type', 'stale_data')
            ->count();

        if ($staleCounts > 0) {
            $recommendations[] = "Force synchronization for {$staleCounts} accounts with stale data";
        }

        $orphanedCounts = collect($this->discrepancies)
            ->where('type', 'orphaned_balance')
            ->count();

        if ($orphanedCounts > 0) {
            $recommendations[] = "Review {$orphanedCounts} accounts with orphaned balances";
        }

        return $recommendations;
    }

    /**
     * Handle discrepancies.
     */
    private function handleDiscrepancies(): void
    {
        // Group discrepancies by severity
        $criticalDiscrepancies = collect($this->discrepancies)
            ->filter(
                function ($d) {
                    return isset($d['difference']) && $d['difference'] > 100000; // Over $1000
                }
            );

        if ($criticalDiscrepancies->isNotEmpty()) {
            // Send immediate alert for critical discrepancies
            $this->sendCriticalAlert($criticalDiscrepancies);
        }

        // Send reconciliation report email
        $this->sendReconciliationReport();
    }

    /**
     * Send critical alert.
     */
    private function sendCriticalAlert(Collection $criticalDiscrepancies): void
    {
        Log::critical(
            'Critical reconciliation discrepancies found',
            [
                'count'        => $criticalDiscrepancies->count(),
                'total_amount' => $criticalDiscrepancies->sum('difference'),
            ]
        );

        // In production, send alerts to operations team
    }

    /**
     * Send reconciliation report.
     */
    private function sendReconciliationReport(): void
    {
        $recipients = config('reconciliation.report_recipients', []);

        if (! empty($recipients)) {
            Mail::to($recipients)->send(
                new ReconciliationReport(
                    $this->reconciliationResults,
                    $this->discrepancies
                )
            );
        }
    }

    /**
     * Store reconciliation report.
     */
    private function storeReport(array $report): void
    {
        $filename = sprintf(
            'reconciliation-%s.json',
            $this->reconciliationResults['date']
        );

        $path = storage_path("app/reconciliation/{$filename}");

        // Ensure directory exists
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));

        Log::info('Reconciliation report stored', ['path' => $path]);
    }

    /**
     * Get latest reconciliation report.
     */
    public function getLatestReport(): ?array
    {
        $files = glob(storage_path('app/reconciliation/reconciliation-*.json'));

        if (empty($files)) {
            return null;
        }

        // Sort by filename (date) descending
        rsort($files);

        $latestFile = $files[0];
        $content = file_get_contents($latestFile);

        return json_decode($content, true);
    }
}
