<?php

namespace App\Domain\Regulatory\Console\Commands;

use App\Domain\Regulatory\Models\RegulatoryFilingRecord;
use App\Domain\Regulatory\Models\RegulatoryReport;
use App\Domain\Regulatory\Models\RegulatoryThreshold;
use App\Domain\Regulatory\Services\EnhancedRegulatoryReportingService;
use App\Domain\Regulatory\Services\ThresholdMonitoringService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class RegulatoryManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'regulatory:manage 
                            {action : Action to perform (generate-reports, check-thresholds, process-filings, check-overdue)}
                            {--date= : Date for report generation (YYYY-MM-DD)}
                            {--type= : Report type (CTR, SAR, AML, OFAC, BSA)}
                            {--jurisdiction= : Jurisdiction (US, EU, UK)}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage regulatory reporting and compliance';

    protected EnhancedRegulatoryReportingService $reportingService;

    protected ThresholdMonitoringService $thresholdService;

    public function __construct(
        EnhancedRegulatoryReportingService $reportingService,
        ThresholdMonitoringService $thresholdService
    ) {
        parent::__construct();
        $this->reportingService = $reportingService;
        $this->thresholdService = $thresholdService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        switch ($action) {
            case 'generate-reports':
                return $this->generateReports($dryRun);

            case 'check-thresholds':
                return $this->checkThresholds($dryRun);

            case 'process-filings':
                return $this->processFilings($dryRun);

            case 'check-overdue':
                return $this->checkOverdueReports($dryRun);

            default:
                $this->error("Unknown action: {$action}");

                return 1;
        }
    }

    /**
     * Generate regulatory reports.
     */
    protected function generateReports(bool $dryRun): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $type = $this->option('type');

        $this->info("Generating reports for date: {$date->toDateString()}");

        $reports = [];

        // Generate based on type or all if not specified
        if (! $type || $type === 'CTR') {
            $this->info('Generating CTR report...');
            if (! $dryRun) {
                try {
                    $report = $this->reportingService->generateEnhancedCTR($date);
                    $reports[] = $report;
                    $this->info("✓ CTR report generated: {$report->report_id}");
                } catch (Exception $e) {
                    $this->error("Failed to generate CTR: {$e->getMessage()}");
                }
            }
        }

        if (! $type || $type === 'SAR') {
            $this->info('Checking for SAR candidates...');
            if (! $dryRun) {
                try {
                    $startDate = $date->copy()->subDays(30);
                    $report = $this->reportingService->generateEnhancedSAR($startDate, $date);
                    $reports[] = $report;
                    $this->info("✓ SAR report generated: {$report->report_id}");

                    if ($report->report_data['requires_immediate_filing'] ?? false) {
                        $this->warn('⚠ SAR requires immediate filing!');
                    }
                } catch (Exception $e) {
                    $this->error("Failed to generate SAR: {$e->getMessage()}");
                }
            }
        }

        if (! $type || $type === 'AML') {
            if ($date->day === $date->copy()->endOfMonth()->day) {
                $this->info('Generating monthly AML report...');
                if (! $dryRun) {
                    try {
                        $report = $this->reportingService->generateAMLReport($date);
                        $reports[] = $report;
                        $this->info("✓ AML report generated: {$report->report_id}");
                    } catch (Exception $e) {
                        $this->error("Failed to generate AML: {$e->getMessage()}");
                    }
                }
            }
        }

        if (! $type || $type === 'OFAC') {
            $this->info('Generating OFAC screening report...');
            if (! $dryRun) {
                try {
                    $report = $this->reportingService->generateOFACReport($date);
                    $reports[] = $report;
                    $this->info("✓ OFAC report generated: {$report->report_id}");

                    if ($report->report_data['requires_immediate_action'] ?? false) {
                        $this->error('⚠ OFAC matches found - immediate action required!');
                    }
                } catch (Exception $e) {
                    $this->error("Failed to generate OFAC: {$e->getMessage()}");
                }
            }
        }

        if (! $type || $type === 'BSA') {
            if ($date->day === $date->copy()->endOfQuarter()->day) {
                $this->info('Generating quarterly BSA report...');
                if (! $dryRun) {
                    try {
                        $report = $this->reportingService->generateBSAReport($date);
                        $reports[] = $report;
                        $this->info("✓ BSA report generated: {$report->report_id}");
                    } catch (Exception $e) {
                        $this->error("Failed to generate BSA: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->info("\nSummary: " . count($reports) . ' reports generated');

        return 0;
    }

    /**
     * Check regulatory thresholds.
     */
    protected function checkThresholds(bool $dryRun): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();

        $this->info("Running aggregate threshold monitoring for: {$date->toDateString()}");

        if (! $dryRun) {
            $triggered = $this->thresholdService->runAggregateMonitoring($date);

            if ($triggered->isNotEmpty()) {
                $this->warn("Found {$triggered->count()} triggered thresholds:");

                foreach ($triggered as $item) {
                    $threshold = $item['threshold'];
                    $this->warn("  - {$threshold->name} ({$threshold->threshold_code})");

                    if ($threshold->shouldAutoReport()) {
                        $this->info('    → Auto-generating report for this threshold');
                    }
                }
            } else {
                $this->info('No thresholds triggered');
            }
        }

        // Show threshold statistics
        $activeCount = RegulatoryThreshold::active()->count();
        $this->info("\nThreshold Statistics:");
        $this->info("  Active thresholds: {$activeCount}");

        $highTriggerThresholds = RegulatoryThreshold::where('trigger_count', '>', 100)
            ->orderByDesc('trigger_count')
            ->limit(5)
            ->get();

        if ($highTriggerThresholds->isNotEmpty()) {
            $this->info('  Most triggered thresholds:');
            foreach ($highTriggerThresholds as $threshold) {
                $this->info("    - {$threshold->name}: {$threshold->trigger_count} triggers");
            }
        }

        return 0;
    }

    /**
     * Process pending filings.
     */
    protected function processFilings(bool $dryRun): int
    {
        $this->info('Processing regulatory filings...');

        // Check status of submitted filings
        $submittedFilings = RegulatoryFilingRecord::whereIn(
            'filing_status',
            [
            RegulatoryFilingRecord::STATUS_SUBMITTED,
            RegulatoryFilingRecord::STATUS_ACKNOWLEDGED,
            ]
        )->get();

        $this->info("Checking status of {$submittedFilings->count()} submitted filings...");

        foreach ($submittedFilings as $filing) {
            if (! $dryRun) {
                try {
                    $status = app(\App\Domain\Regulatory\Services\RegulatoryFilingService::class)
                        ->checkFilingStatus($filing);

                    $this->info("  {$filing->filing_id}: {$status['status']}");

                    if ($status['status'] === 'accepted') {
                        $this->info('    ✓ Filing accepted');
                    } elseif ($status['status'] === 'rejected') {
                        $this->error("    ✗ Filing rejected: {$status['reason']}");
                    }
                } catch (Exception $e) {
                    $this->error("  Failed to check {$filing->filing_id}: {$e->getMessage()}");
                }
            }
        }

        // Retry failed filings
        $failedFilings = RegulatoryFilingRecord::requireingRetry()->get();

        if ($failedFilings->isNotEmpty()) {
            $this->info("\nRetrying {$failedFilings->count()} failed filings...");

            foreach ($failedFilings as $filing) {
                if (! $dryRun) {
                    try {
                        app(\App\Domain\Regulatory\Services\RegulatoryFilingService::class)
                            ->retryFiling($filing);

                        $this->info("  ✓ Retried {$filing->filing_id}");
                    } catch (Exception $e) {
                        $this->error("  ✗ Failed to retry {$filing->filing_id}: {$e->getMessage()}");
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Check overdue reports.
     */
    protected function checkOverdueReports(bool $dryRun): int
    {
        $this->info('Checking for overdue reports...');

        $overdueReports = RegulatoryReport::overdue()
            ->orderByDesc('days_overdue')
            ->get();

        if ($overdueReports->isEmpty()) {
            $this->info('No overdue reports found');

            return 0;
        }

        $this->warn("Found {$overdueReports->count()} overdue reports:");

        $table = [];
        foreach ($overdueReports as $report) {
            $table[] = [
                $report->report_id,
                $report->report_type,
                $report->jurisdiction,
                $report->due_date->toDateString(),
                $report->days_overdue . ' days',
                $report->getStatusLabel(),
            ];
        }

        $this->table(
            ['Report ID', 'Type', 'Jurisdiction', 'Due Date', 'Overdue', 'Status'],
            $table
        );

        // Reports due soon
        $dueSoon = RegulatoryReport::dueSoon(7)
            ->orderBy('due_date')
            ->get();

        if ($dueSoon->isNotEmpty()) {
            $this->info("\nReports due within 7 days:");

            $table = [];
            foreach ($dueSoon as $report) {
                $table[] = [
                    $report->report_id,
                    $report->report_type,
                    $report->due_date->toDateString(),
                    $report->getTimeUntilDue(),
                ];
            }

            $this->table(
                ['Report ID', 'Type', 'Due Date', 'Time Until Due'],
                $table
            );
        }

        return 0;
    }
}
