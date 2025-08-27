<?php

namespace App\Domain\Custodian\Console\Commands;

use App\Domain\Custodian\Services\SettlementService;
use Exception;
use Illuminate\Console\Command;

class ProcessSettlements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settlements:process 
                            {--type= : Override settlement type (realtime, batch, net)}
                            {--dry-run : Run without executing actual settlements}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending inter-custodian settlements';

    private SettlementService $settlementService;

    /**
     * Create a new command instance.
     */
    public function __construct(SettlementService $settlementService)
    {
        parent::__construct();
        $this->settlementService = $settlementService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🏦 Processing inter-custodian settlements...');

        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        if ($type) {
            $this->info("Settlement type override: {$type}");
            config(['custodians.settlement.type' => $type]);
        }

        $currentType = config('custodians.settlement.type');
        $this->info("Settlement type: {$currentType}");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual settlements will be executed');

            return $this->performDryRun();
        }

        try {
            $results = $this->settlementService->processPendingSettlements();

            $this->displayResults($results, $currentType);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Settlement processing failed: ' . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Perform a dry run to show what would be settled.
     */
    private function performDryRun(): int
    {
        $this->info('Analyzing pending settlements...');

        $stats = $this->settlementService->getSettlementStatistics();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Settlements', $stats['total']],
                ['Completed', $stats['completed']],
                ['Failed', $stats['failed']],
                ['Pending', $stats['pending']],
                ['Total Gross Amount', '$' . number_format($stats['total_gross_amount'] / 100, 2)],
                ['Total Net Amount', '$' . number_format($stats['total_net_amount'] / 100, 2)],
                ['Total Savings', '$' . number_format($stats['total_savings'] / 100, 2)],
                ['Savings Percentage', $stats['savings_percentage'] . '%'],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Display settlement results.
     */
    private function displayResults(array $results, string $type): void
    {
        $this->newLine();
        $this->info('✅ Settlement Processing Complete');
        $this->newLine();

        switch ($type) {
            case 'net':
                $this->info('📊 Net Settlement Results:');
                $this->table(
                    ['Metric', 'Value'],
                    [
                    ['Settlements Processed', $results['settlements']],
                    ['Total Gross Amount', '$' . number_format($results['total_gross'] / 100, 2)],
                    ['Total Net Amount', '$' . number_format($results['total_net'] / 100, 2)],
                    ['Savings', '$' . number_format($results['savings'] / 100, 2)],
                    ['Savings Percentage', $results['savings_percentage'] . '%'],
                    ]
                );
                break;

            case 'batch':
                $this->info('📦 Batch Settlement Results:');
                $this->table(
                    ['Metric', 'Value'],
                    [
                    ['Batches Processed', $results['batches']],
                    ['Transfers Settled', $results['transfers']],
                    ['Total Amount', '$' . number_format($results['total_amount'] / 100, 2)],
                    ]
                );
                break;

            case 'realtime':
                $this->info('⚡ Realtime Settlement Results:');
                $this->table(
                    ['Metric', 'Value'],
                    [
                    ['Settlements Processed', $results['processed']],
                    ['Failed', $results['failed']],
                    ['Total Amount', '$' . number_format($results['total_amount'] / 100, 2)],
                    ]
                );
                break;
        }

        // Show current statistics
        $this->newLine();
        $this->info('📈 Current Settlement Statistics:');
        $stats = $this->settlementService->getSettlementStatistics();

        $this->table(
            ['Settlement Type', 'Count', 'Amount'],
            collect($stats['by_type'])->map(
                function ($data, $type) {
                    return [
                    ucfirst($type),
                    $data['count'],
                    '$' . number_format(($data['amount'] ?? 0) / 100, 2),
                    ];
                }
            )->toArray()
        );
    }
}
