<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Workflows\AssetTransferWorkflow;
use App\Domain\Asset\Workflows\OptimizedAssetTransferWorkflow;
use App\Models\Account;
use App\Models\AccountBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Workflow\WorkflowStub;

class BenchmarkTransferPerformance extends Command
{
    protected $signature = 'benchmark:transfers 
                            {--iterations=100 : Number of transfers to benchmark}
                            {--parallel=10 : Number of parallel transfers}
                            {--optimized : Use optimized workflow}';

    protected $description = 'Benchmark transfer performance to test sub-second capabilities';

    public function handle(): int
    {
        $iterations = (int) $this->option('iterations');
        $parallel = (int) $this->option('parallel');
        $useOptimized = $this->option('optimized');
        
        $this->info('Transfer Performance Benchmark');
        $this->info('==============================');
        $this->info("Iterations: {$iterations}");
        $this->info("Parallel: {$parallel}");
        $this->info("Workflow: " . ($useOptimized ? 'Optimized' : 'Standard'));
        $this->info('');
        
        // Create test accounts
        $this->info('Setting up test accounts...');
        $accounts = $this->setupTestAccounts($iterations * 2);
        
        // Clear caches to ensure fair benchmark
        Cache::flush();
        
        // Run benchmark
        $this->info('Running benchmark...');
        $startTime = microtime(true);
        
        $results = [];
        $chunks = array_chunk(range(0, $iterations - 1), $parallel);
        
        foreach ($chunks as $chunk) {
            $batchStart = microtime(true);
            
            // Process transfers in parallel
            $promises = [];
            foreach ($chunk as $i) {
                $fromAccount = $accounts[$i * 2];
                $toAccount = $accounts[$i * 2 + 1];
                
                $promises[] = $this->performTransfer(
                    $fromAccount,
                    $toAccount,
                    $useOptimized
                );
            }
            
            // Wait for all transfers to complete
            foreach ($promises as $i => $promise) {
                $results[] = $promise;
            }
            
            $batchTime = microtime(true) - $batchStart;
            $this->comment(sprintf(
                "Batch %d/%d completed in %.2fms",
                count($results) / $parallel,
                ceil($iterations / $parallel),
                $batchTime * 1000
            ));
        }
        
        $totalTime = microtime(true) - $startTime;
        
        // Calculate statistics
        $this->displayResults($results, $totalTime, $iterations);
        
        // Cleanup
        $this->cleanupTestAccounts($accounts);
        
        return Command::SUCCESS;
    }
    
    private function setupTestAccounts(int $count): array
    {
        DB::beginTransaction();
        
        $accounts = [];
        for ($i = 0; $i < $count; $i++) {
            $account = Account::factory()->zeroBalance()->create();
            
            // Add USD balance
            AccountBalance::create([
                'account_uuid' => $account->uuid,
                'asset_code' => 'USD',
                'balance' => 1000000, // $10,000
            ]);
            
            $accounts[] = $account;
        }
        
        DB::commit();
        
        return $accounts;
    }
    
    private function performTransfer(Account $from, Account $to, bool $useOptimized): array
    {
        $transferStart = microtime(true);
        
        try {
            $workflowClass = $useOptimized 
                ? OptimizedAssetTransferWorkflow::class 
                : AssetTransferWorkflow::class;
            
            $workflow = WorkflowStub::make($workflowClass);
            $result = $workflow->start(
                AccountUuid::fromString((string) $from->uuid),
                AccountUuid::fromString((string) $to->uuid),
                'USD',
                'USD',
                new Money(100), // $1.00
                'Benchmark transfer'
            );
            
            $transferTime = microtime(true) - $transferStart;
            
            return [
                'success' => true,
                'time_ms' => round($transferTime * 1000, 2),
                'workflow' => $workflowClass,
            ];
        } catch (\Exception $e) {
            $transferTime = microtime(true) - $transferStart;
            
            return [
                'success' => false,
                'time_ms' => round($transferTime * 1000, 2),
                'error' => $e->getMessage(),
                'workflow' => $workflowClass ?? 'unknown',
            ];
        }
    }
    
    private function displayResults(array $results, float $totalTime, int $iterations): void
    {
        $successful = array_filter($results, fn($r) => $r['success']);
        $failed = array_filter($results, fn($r) => !$r['success']);
        
        $times = array_column($successful, 'time_ms');
        
        $this->info('');
        $this->info('Benchmark Results');
        $this->info('=================');
        $this->info(sprintf('Total time: %.2f seconds', $totalTime));
        $this->info(sprintf('Successful transfers: %d/%d', count($successful), $iterations));
        $this->info(sprintf('Failed transfers: %d/%d', count($failed), $iterations));
        
        if (count($times) > 0) {
            $this->info('');
            $this->info('Performance Metrics (successful transfers)');
            $this->info('==========================================');
            $this->info(sprintf('Average: %.2fms', array_sum($times) / count($times)));
            $this->info(sprintf('Min: %.2fms', min($times)));
            $this->info(sprintf('Max: %.2fms', max($times)));
            $this->info(sprintf('Median: %.2fms', $this->calculateMedian($times)));
            
            // Calculate percentiles
            $p95 = $this->calculatePercentile($times, 95);
            $p99 = $this->calculatePercentile($times, 99);
            
            $this->info(sprintf('95th percentile: %.2fms', $p95));
            $this->info(sprintf('99th percentile: %.2fms', $p99));
            
            // Check sub-second performance
            $subSecond = array_filter($times, fn($t) => $t < 1000);
            $this->info('');
            $this->info(sprintf(
                'Sub-second transfers: %d/%d (%.1f%%)',
                count($subSecond),
                count($times),
                (count($subSecond) / count($times)) * 100
            ));
        }
        
        if (count($failed) > 0) {
            $this->error('');
            $this->error('Failed Transfers');
            $this->error('================');
            foreach (array_slice($failed, 0, 5) as $failure) {
                $this->error($failure['error'] ?? 'Unknown error');
            }
            if (count($failed) > 5) {
                $this->error('... and ' . (count($failed) - 5) . ' more');
            }
        }
    }
    
    private function calculateMedian(array $times): float
    {
        sort($times);
        $count = count($times);
        $middle = floor(($count - 1) / 2);
        
        if ($count % 2) {
            return $times[$middle];
        } else {
            return ($times[$middle] + $times[$middle + 1]) / 2;
        }
    }
    
    private function calculatePercentile(array $times, int $percentile): float
    {
        sort($times);
        $index = ($percentile / 100) * (count($times) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;
        
        return $times[$lower] * (1 - $weight) + $times[$upper] * $weight;
    }
    
    private function cleanupTestAccounts(array $accounts): void
    {
        DB::beginTransaction();
        
        $uuids = array_map(fn($a) => $a->uuid, $accounts);
        
        // Delete balances
        AccountBalance::whereIn('account_uuid', $uuids)->delete();
        
        // Delete accounts
        Account::whereIn('uuid', $uuids)->delete();
        
        DB::commit();
    }
}