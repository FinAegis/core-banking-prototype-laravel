<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Console\Commands;

use App\Domain\Custodian\Services\DailyReconciliationService;
use Exception;
use Illuminate\Console\Command;

class PerformDailyReconciliation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reconciliation:daily 
                            {--force : Force reconciliation even if already done today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform daily balance reconciliation for all accounts';

    public function __construct(
        private readonly DailyReconciliationService $reconciliationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting daily reconciliation process...');

        // Check if already run today
        if (! $this->option('force')) {
            $latestReport = $this->reconciliationService->getLatestReport();

            if ($latestReport && $latestReport['summary']['date'] === now()->toDateString()) {
                $this->warn('Daily reconciliation already performed today. Use --force to run again.');

                return Command::SUCCESS;
            }
        }

        try {
            $startTime = now();
            $this->info("Started at: {$startTime->toDateTimeString()}");

            // Perform reconciliation
            $report = $this->reconciliationService->performDailyReconciliation();

            // Display results
            $this->displayResults($report);

            $endTime = now();
            $duration = $endTime->diffInSeconds($startTime);

            $this->info("Completed at: {$endTime->toDateTimeString()}");
            $this->info("Duration: {$duration} seconds");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Reconciliation failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Display reconciliation results.
     */
    private function displayResults(array $report): void
    {
        $summary = $report['summary'];

        $this->newLine();
        $this->info('=== Reconciliation Summary ===');
        $this->line("Date: {$summary['date']}");
        $this->line("Accounts Checked: {$summary['accounts_checked']}");
        $this->line("Discrepancies Found: {$summary['discrepancies_found']}");

        if ($summary['discrepancies_found'] > 0) {
            $this->line('Total Discrepancy Amount: $' . number_format($summary['total_discrepancy_amount'] / 100, 2));

            $this->newLine();
            $this->warn('=== Discrepancies ===');

            foreach ($report['discrepancies'] as $discrepancy) {
                $this->displayDiscrepancy($discrepancy);
            }
        } else {
            $this->newLine();
            $this->info('✓ No discrepancies found!');
        }

        if (! empty($report['recommendations'])) {
            $this->newLine();
            $this->info('=== Recommendations ===');
            foreach ($report['recommendations'] as $recommendation) {
                $this->line("• {$recommendation}");
            }
        }
    }

    /**
     * Display individual discrepancy.
     */
    private function displayDiscrepancy(array $discrepancy): void
    {
        $type = $discrepancy['type'];

        switch ($type) {
            case 'balance_mismatch':
                $this->error('Balance Mismatch:');
                $this->line("  Account: {$discrepancy['account_uuid']}");
                $this->line("  Asset: {$discrepancy['asset_code']}");
                $this->line('  Internal: $' . number_format($discrepancy['internal_balance'] / 100, 2));
                $this->line('  External: $' . number_format($discrepancy['external_balance'] / 100, 2));
                $this->line('  Difference: $' . number_format($discrepancy['difference'] / 100, 2));
                break;

            case 'stale_data':
                $this->warn('Stale Data:');
                $this->line("  Account: {$discrepancy['account_uuid']}");
                $this->line("  Custodian: {$discrepancy['custodian_id']}");
                $this->line("  Last Synced: {$discrepancy['last_synced_at']}");
                break;

            case 'orphaned_balance':
                $this->warn('Orphaned Balance:');
                $this->line("  Account: {$discrepancy['account_uuid']}");
                $this->line("  {$discrepancy['message']}");
                break;
        }

        $this->newLine();
    }
}
