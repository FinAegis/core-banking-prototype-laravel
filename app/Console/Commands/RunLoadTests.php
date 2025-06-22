<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RunLoadTests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:load 
                            {--test=* : Specific tests to run}
                            {--iterations=100 : Number of iterations per test}
                            {--concurrent=10 : Number of concurrent operations}
                            {--report : Generate detailed performance report}
                            {--benchmark : Save results as performance benchmark}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run performance load tests against the system';

    private array $results = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting FinAegis Load Testing Suite');
        $this->info('=====================================');
        
        // Prepare environment
        $this->prepareEnvironment();
        
        $tests = $this->option('test') ?: [
            'account-creation',
            'transfers',
            'exchange-rates',
            'webhooks',
            'database-queries',
            'cache-operations',
        ];
        
        $iterations = (int) $this->option('iterations');
        $concurrent = (int) $this->option('concurrent');
        
        foreach ($tests as $test) {
            $this->runTest($test, $iterations, $concurrent);
        }
        
        if ($this->option('report')) {
            $this->generateReport();
        }
        
        if ($this->option('benchmark')) {
            $this->saveBenchmark();
        }
        
        return Command::SUCCESS;
    }
    
    private function prepareEnvironment(): void
    {
        $this->info('Preparing test environment...');
        
        // Clear caches
        Cache::flush();
        
        // Optimize database
        if (config('database.default') === 'mysql') {
            DB::statement('ANALYZE TABLE accounts');
            DB::statement('ANALYZE TABLE account_balances');
            DB::statement('ANALYZE TABLE transactions');
            DB::statement('ANALYZE TABLE stored_events');
        }
        
        $this->info('✅ Environment ready');
        $this->newLine();
    }
    
    private function runTest(string $test, int $iterations, int $concurrent): void
    {
        $this->info("Running test: {$test}");
        $this->info("Iterations: {$iterations}, Concurrent: {$concurrent}");
        
        $startTime = microtime(true);
        
        switch ($test) {
            case 'account-creation':
                $this->testAccountCreation($iterations);
                break;
                
            case 'transfers':
                $this->testTransfers($iterations, $concurrent);
                break;
                
            case 'exchange-rates':
                $this->testExchangeRates($iterations);
                break;
                
            case 'webhooks':
                $this->testWebhooks($iterations);
                break;
                
            case 'database-queries':
                $this->testDatabaseQueries($iterations);
                break;
                
            case 'cache-operations':
                $this->testCacheOperations($iterations);
                break;
                
            default:
                $this->warn("Unknown test: {$test}");
                return;
        }
        
        $totalTime = microtime(true) - $startTime;
        
        $this->results[$test] = [
            'iterations' => $iterations,
            'total_time' => $totalTime,
            'avg_time' => $totalTime / $iterations,
            'ops_per_second' => $iterations / $totalTime,
        ];
        
        $this->info(sprintf(
            "✅ Completed in %.3fs (%.2f ops/sec, avg: %.2fms)",
            $totalTime,
            $iterations / $totalTime,
            ($totalTime / $iterations) * 1000
        ));
        $this->newLine();
    }
    
    private function testAccountCreation(int $iterations): void
    {
        $bar = $this->output->createProgressBar($iterations);
        $bar->start();
        
        for ($i = 0; $i < $iterations; $i++) {
            // Create user and account directly
            $user = \App\Models\User::factory()->create();
            $account = \App\Models\Account::factory()->forUser($user)->create();
            $account->addBalance('USD', 100000);
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }
    
    private function testTransfers(int $iterations, int $concurrent): void
    {
        // Create test accounts
        $this->info('Creating test accounts...');
        $accounts = [];
        for ($i = 0; $i < $concurrent; $i++) {
            $user = \App\Models\User::factory()->create();
            $account = \App\Models\Account::factory()->forUser($user)->create();
            $account->addBalance('USD', 10000000); // $100,000
            $accounts[] = $account;
        }
        
        $bar = $this->output->createProgressBar($iterations);
        $bar->start();
        
        for ($i = 0; $i < $iterations; $i++) {
            $from = $accounts[array_rand($accounts)];
            $to = $accounts[array_rand($accounts)];
            
            if ($from->uuid !== $to->uuid) {
                try {
                    $workflow = app(\App\Domain\Payment\Workflows\TransferWorkflow::class);
                    $workflow->execute(
                        new \App\Domain\Account\ValueObjects\AccountUuid($from->uuid),
                        new \App\Domain\Account\ValueObjects\AccountUuid($to->uuid),
                        new \App\Domain\Account\ValueObjects\Money(1000)
                    );
                } catch (\Exception $e) {
                    // Log but continue testing
                    $this->warn("Transfer failed: " . $e->getMessage());
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }
    
    private function testExchangeRates(int $iterations): void
    {
        $assets = ['USD', 'EUR', 'GBP', 'CHF', 'JPY'];
        $bar = $this->output->createProgressBar($iterations);
        $bar->start();
        
        for ($i = 0; $i < $iterations; $i++) {
            $from = $assets[array_rand($assets)];
            $to = $assets[array_rand($assets)];
            
            if ($from !== $to) {
                \App\Models\ExchangeRate::getRate($from, $to);
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }
    
    private function testWebhooks(int $iterations): void
    {
        // Create test webhook
        $webhook = \App\Models\Webhook::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'name' => 'Load Test Webhook',
            'url' => 'https://httpbin.org/post',
            'events' => ['account.created', 'transaction.completed'],
            'secret' => \Illuminate\Support\Str::random(32),
        ]);
        
        $bar = $this->output->createProgressBar($iterations);
        $bar->start();
        
        for ($i = 0; $i < $iterations; $i++) {
            // Simulate webhook retrieval
            \App\Models\Webhook::where('is_active', true)->get();
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        // Cleanup
        $webhook->delete();
    }
    
    private function testDatabaseQueries(int $iterations): void
    {
        $bar = $this->output->createProgressBar($iterations);
        $bar->start();
        
        for ($i = 0; $i < $iterations; $i++) {
            // Complex query with joins
            DB::table('accounts')
                ->join('users', 'accounts.user_uuid', '=', 'users.uuid')
                ->join('account_balances', 'accounts.uuid', '=', 'account_balances.account_uuid')
                ->select(
                    'accounts.uuid',
                    'accounts.name',
                    'users.name as user_name',
                    DB::raw('SUM(account_balances.balance) as total_balance')
                )
                ->where('accounts.is_active', true)
                ->groupBy('accounts.uuid', 'accounts.name', 'users.name')
                ->orderBy('total_balance', 'desc')
                ->limit(50)
                ->get();
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
    }
    
    private function testCacheOperations(int $iterations): void
    {
        $data = [
            'account' => \App\Models\Account::factory()->make()->toArray(),
            'balances' => [
                'USD' => 100000,
                'EUR' => 50000,
                'GBP' => 30000,
            ],
            'metadata' => [
                'last_updated' => now()->toIso8601String(),
                'version' => '1.0',
            ],
        ];
        
        $bar = $this->output->createProgressBar($iterations * 2); // Write + Read
        $bar->start();
        
        // Test writes
        for ($i = 0; $i < $iterations; $i++) {
            Cache::put("load_test:{$i}", $data, 300);
            $bar->advance();
        }
        
        // Test reads
        for ($i = 0; $i < $iterations; $i++) {
            Cache::get("load_test:{$i}");
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        // Cleanup
        for ($i = 0; $i < $iterations; $i++) {
            Cache::forget("load_test:{$i}");
        }
    }
    
    private function generateReport(): void
    {
        $this->newLine();
        $this->info('📊 Performance Report');
        $this->info('====================');
        
        $this->table(
            ['Test', 'Iterations', 'Total Time', 'Avg Time (ms)', 'Ops/Sec'],
            collect($this->results)->map(function ($result, $test) {
                return [
                    $test,
                    $result['iterations'],
                    sprintf('%.3fs', $result['total_time']),
                    sprintf('%.2f', $result['avg_time'] * 1000),
                    sprintf('%.2f', $result['ops_per_second']),
                ];
            })->toArray()
        );
        
        // Performance thresholds
        $this->newLine();
        $this->info('🎯 Performance Thresholds');
        $this->info('========================');
        
        $thresholds = [
            'account-creation' => 100, // ms
            'transfers' => 200,
            'exchange-rates' => 50,
            'webhooks' => 50,
            'database-queries' => 100,
            'cache-operations' => 1,
        ];
        
        foreach ($this->results as $test => $result) {
            $avgMs = $result['avg_time'] * 1000;
            $threshold = $thresholds[$test] ?? 100;
            
            if ($avgMs <= $threshold) {
                $this->info("✅ {$test}: {$avgMs}ms (threshold: {$threshold}ms)");
            } else {
                $this->error("❌ {$test}: {$avgMs}ms (threshold: {$threshold}ms)");
            }
        }
        
        // System information
        $this->newLine();
        $this->info('💻 System Information');
        $this->info('====================');
        $this->line('Environment: ' . app()->environment());
        $this->line('Database: ' . config('database.default'));
        $this->line('Cache: ' . config('cache.default'));
        $this->line('Queue: ' . config('queue.default'));
        $this->line('PHP: ' . PHP_VERSION);
        $this->line('Laravel: ' . app()->version());
        $this->line('Memory: ' . round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB');
    }
    
    private function saveBenchmark(): void
    {
        $benchmarkFile = storage_path('app/benchmarks/load-test-' . now()->format('Y-m-d-His') . '.json');
        
        if (!is_dir(dirname($benchmarkFile))) {
            mkdir(dirname($benchmarkFile), 0755, true);
        }
        
        $benchmark = [
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'system' => [
                'database' => config('database.default'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'results' => $this->results,
        ];
        
        file_put_contents($benchmarkFile, json_encode($benchmark, JSON_PRETTY_PRINT));
        
        $this->info("📁 Benchmark saved to: {$benchmarkFile}");
    }
}