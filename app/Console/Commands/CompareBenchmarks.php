<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CompareBenchmarks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:compare-benchmarks 
                            {baseline : Path to baseline benchmark file}
                            {current? : Path to current benchmark file (latest if not provided)}
                            {--threshold=10 : Performance regression threshold percentage}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare performance benchmarks to detect regressions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $baselinePath = $this->argument('baseline');
        $currentPath = $this->argument('current') ?? $this->findLatestBenchmark();
        $threshold = (float) $this->option('threshold');

        if (! file_exists($baselinePath)) {
            $this->error("Baseline benchmark not found: {$baselinePath}");

            return Command::FAILURE;
        }

        if (! $currentPath || ! file_exists($currentPath)) {
            $this->error('Current benchmark not found');

            return Command::FAILURE;
        }

        $baselineContent = file_get_contents($baselinePath);
        $currentContent = file_get_contents($currentPath);

        if ($baselineContent === false || $currentContent === false) {
            $this->error('Failed to read benchmark files');

            return 1;
        }

        $baseline = json_decode($baselineContent, true);
        $current = json_decode($currentContent, true);

        $this->info('📊 Performance Benchmark Comparison');
        $this->info('===================================');
        $this->info("Baseline: {$baseline['timestamp']}");
        $this->info("Current:  {$current['timestamp']}");
        $this->info("Threshold: {$threshold}%");
        $this->newLine();

        $hasRegression = false;
        $results = [];

        foreach ($current['results'] as $test => $currentResult) {
            if (! isset($baseline['results'][$test])) {
                $this->warn("⚠️  New test: {$test} (no baseline)");

                continue;
            }

            $baselineResult = $baseline['results'][$test];
            $baselineAvg = $baselineResult['avg_time'] * 1000; // Convert to ms
            $currentAvg = $currentResult['avg_time'] * 1000;

            $percentChange = (($currentAvg - $baselineAvg) / $baselineAvg) * 100;

            $results[] = [
                'test'     => $test,
                'baseline' => sprintf('%.2f ms', $baselineAvg),
                'current'  => sprintf('%.2f ms', $currentAvg),
                'change'   => sprintf('%+.1f%%', $percentChange),
                'status'   => $this->getStatus($percentChange, $threshold),
            ];

            if ($percentChange > $threshold) {
                $hasRegression = true;
            }
        }

        // Display results table
        $this->table(
            ['Test', 'Baseline', 'Current', 'Change', 'Status'],
            collect($results)->map(
                function ($result) {
                    return [
                        $result['test'],
                        $result['baseline'],
                        $result['current'],
                        $result['change'],
                        $result['status'],
                    ];
                }
            )->toArray()
        );

        // Summary
        $this->newLine();
        if ($hasRegression) {
            $this->error('❌ Performance regression detected!');
            $this->error("Some tests exceeded the {$threshold}% threshold.");

            // Show detailed regression info
            $this->newLine();
            $this->info('Regressions:');
            foreach ($results as $result) {
                if (str_contains($result['status'], '❌')) {
                    $this->line("  - {$result['test']}: {$result['change']}");
                }
            }

            return Command::FAILURE;
        } else {
            $this->info('✅ No performance regressions detected!');

            // Show improvements
            $improvements = collect($results)->filter(
                function ($result) {
                    return str_contains($result['status'], '🚀');
                }
            );

            if ($improvements->isNotEmpty()) {
                $this->newLine();
                $this->info('Improvements:');
                foreach ($improvements as $result) {
                    $this->line("  - {$result['test']}: {$result['change']}");
                }
            }

            return Command::SUCCESS;
        }
    }

    private function findLatestBenchmark(): ?string
    {
        $benchmarkDir = storage_path('app/benchmarks');

        if (! is_dir($benchmarkDir)) {
            return null;
        }

        $files = glob($benchmarkDir . '/load-test-*.json');

        if (empty($files)) {
            return null;
        }

        // Sort by modification time and get the latest
        usort(
            $files,
            function ($a, $b) {
                return filemtime($b) - filemtime($a);
            }
        );

        return $files[0];
    }

    private function getStatus(float $percentChange, float $threshold): string
    {
        if ($percentChange > $threshold) {
            return '❌ Regression';
        } elseif ($percentChange < -$threshold) {
            return '🚀 Improvement';
        } elseif ($percentChange > 5) {
            return '⚠️  Warning';
        } else {
            return '✅ OK';
        }
    }
}
