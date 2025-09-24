<?php

declare(strict_types=1);

namespace Tests\Performance\AgentProtocol;

use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\AgentWalletService;
use App\Domain\AgentProtocol\Services\Integration\CoordinationIntegrationService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Performance benchmark tests for Agent Protocol.
 *
 * Tests system performance under various load conditions including:
 * - Transaction throughput
 * - Message delivery latency
 * - Concurrent agent handling
 * - Resource utilization
 */
class PerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private const PERFORMANCE_THRESHOLDS = [
        'transaction_throughput' => 100, // transactions per second
        'message_latency'        => 100, // milliseconds
        'concurrent_agents'      => 1000, // simultaneous agents
        'memory_limit'           => 512, // MB
    ];

    /**
     * Test transaction throughput.
     */
    public function test_transaction_throughput(): void
    {
        $walletService = app(AgentWalletService::class);

        // Create test agents with wallets
        $agents = [];
        for ($i = 0; $i < 100; $i++) {
            $agent = Agent::factory()->create();
            $wallet = $walletService->createWallet(
                $agent->agent_id,
                'USD',
                10000.00
            );
            $agents[] = ['agent' => $agent, 'wallet' => $wallet];
        }

        // Measure transaction throughput
        $startTime = microtime(true);
        $transactions = 0;

        DB::beginTransaction();

        for ($i = 0; $i < 1000; $i++) {
            $from = $agents[array_rand($agents)];
            $to = $agents[array_rand($agents)];

            if ($from === $to) {
                continue;
            }

            try {
                $walletService->transfer(
                    $from['wallet']->wallet_id,
                    $to['wallet']->wallet_id,
                    10.00,
                    'USD'
                );
                $transactions++;
            } catch (Exception $e) {
                // Skip failed transactions
            }
        }

        DB::commit();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $throughput = $transactions / $duration;

        $this->assertGreaterThan(
            self::PERFORMANCE_THRESHOLDS['transaction_throughput'],
            $throughput,
            "Transaction throughput ({$throughput} tps) below threshold"
        );

        // Log performance metrics
        $this->logPerformanceMetric('transaction_throughput', $throughput, 'tps');
    }

    /**
     * Test message delivery latency.
     */
    public function test_message_delivery_latency(): void
    {
        $sender = Agent::factory()->create();
        $recipient = Agent::factory()->create();

        $latencies = [];

        for ($i = 0; $i < 100; $i++) {
            $startTime = microtime(true);

            $response = $this->postJson('/api/a2a/messages', [
                'header' => [
                    'version'    => '2.0',
                    'message_id' => 'perf_test_' . $i,
                    'timestamp'  => now()->toIso8601String(),
                    'from'       => $sender->did,
                    'to'         => $recipient->did,
                ],
                'body' => [
                    'type'    => 'test',
                    'content' => ['test' => 'data'],
                ],
                'signature' => 'test-signature',
            ]);

            $endTime = microtime(true);

            if ($response->status() === 200) {
                $latency = ($endTime - $startTime) * 1000; // Convert to milliseconds
                $latencies[] = $latency;
            }
        }

        $averageLatency = array_sum($latencies) / count($latencies);
        $maxLatency = max($latencies);
        $p95Latency = $this->calculatePercentile($latencies, 95);

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLDS['message_latency'],
            $averageLatency,
            "Average message latency ({$averageLatency}ms) exceeds threshold"
        );

        // Log performance metrics
        $this->logPerformanceMetric('message_latency_avg', $averageLatency, 'ms');
        $this->logPerformanceMetric('message_latency_max', $maxLatency, 'ms');
        $this->logPerformanceMetric('message_latency_p95', $p95Latency, 'ms');
    }

    /**
     * Test concurrent agent handling.
     */
    public function test_concurrent_agent_handling(): void
    {
        $registryService = app(AgentRegistryService::class);

        // Create many agents
        $agentCount = self::PERFORMANCE_THRESHOLDS['concurrent_agents'];
        $agents = Agent::factory()->count($agentCount)->create();

        $startTime = microtime(true);

        // Simulate concurrent operations
        $operations = [];
        foreach ($agents as $agent) {
            // Register agent
            $operations[] = $registryService->registerAgent([
                'name'         => $agent->name,
                'did'          => $agent->did,
                'capabilities' => ['payment', 'communication'],
            ]);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $opsPerSecond = count($operations) / $duration;

        $this->assertCount($agentCount, $operations);
        $this->assertGreaterThan(
            100, // Minimum 100 operations per second
            $opsPerSecond,
            "Concurrent operations ({$opsPerSecond} ops/s) below threshold"
        );

        // Log performance metrics
        $this->logPerformanceMetric('concurrent_agents', $agentCount, 'agents');
        $this->logPerformanceMetric('registration_rate', $opsPerSecond, 'ops/s');
    }

    /**
     * Test multi-party transaction performance.
     */
    public function test_multi_party_transaction_performance(): void
    {
        $coordinationService = app(CoordinationIntegrationService::class);
        $walletService = app(AgentWalletService::class);

        // Create agents with wallets
        $agents = [];
        for ($i = 0; $i < 10; $i++) {
            $agent = Agent::factory()->create();
            $wallet = $walletService->createWallet(
                $agent->agent_id,
                'USD',
                1000.00
            );
            $agents[] = $agent;
        }

        $startTime = microtime(true);

        // Execute multi-party transactions
        for ($i = 0; $i < 10; $i++) {
            $senders = [];
            $recipients = [];

            // Random senders and recipients
            for ($j = 0; $j < 3; $j++) {
                $senders[$agents[$j]->agent_id] = 100.00;
            }

            for ($j = 3; $j < 6; $j++) {
                $recipients[$agents[$j]->agent_id] = 100.00;
            }

            $coordinationService->executeMultiPartyTransaction(
                $senders,
                $recipients,
                300.00,
                ['equal' => true],
                ['test'  => true]
            );
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $avgTime = ($duration / 10) * 1000; // Average time per transaction in ms

        $this->assertLessThan(
            500, // Maximum 500ms per multi-party transaction
            $avgTime,
            "Multi-party transaction time ({$avgTime}ms) exceeds threshold"
        );

        // Log performance metrics
        $this->logPerformanceMetric('multi_party_tx_time', $avgTime, 'ms');
    }

    /**
     * Test resource utilization.
     */
    public function test_resource_utilization(): void
    {
        $initialMemory = memory_get_usage(true) / 1024 / 1024; // MB

        // Create load
        $agents = Agent::factory()->count(100)->create();
        $walletService = app(AgentWalletService::class);

        foreach ($agents as $agent) {
            $walletService->createWallet(
                $agent->agent_id,
                'USD',
                1000.00
            );
        }

        // Process transactions
        for ($i = 0; $i < 100; $i++) {
            $from = $agents[array_rand($agents->toArray())];
            $to = $agents[array_rand($agents->toArray())];

            if ($from->id !== $to->id) {
                try {
                    // Simulate transaction
                    DB::table('agent_transactions')->insert([
                        'transaction_id' => 'tx_perf_' . $i,
                        'from_wallet_id' => $from->wallet_id ?? 'wallet_' . $from->id,
                        'to_wallet_id'   => $to->wallet_id ?? 'wallet_' . $to->id,
                        'amount'         => 10.00,
                        'currency'       => 'USD',
                        'created_at'     => now(),
                    ]);
                } catch (Exception $e) {
                    // Skip errors
                }
            }
        }

        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB
        $memoryUsed = $peakMemory - $initialMemory;

        $this->assertLessThan(
            self::PERFORMANCE_THRESHOLDS['memory_limit'],
            $memoryUsed,
            "Memory usage ({$memoryUsed}MB) exceeds threshold"
        );

        // Log performance metrics
        $this->logPerformanceMetric('memory_usage', $memoryUsed, 'MB');
        $this->logPerformanceMetric('peak_memory', $peakMemory, 'MB');
    }

    /**
     * Test database query performance.
     */
    public function test_database_query_performance(): void
    {
        // Create test data
        Agent::factory()->count(1000)->create();

        $queries = [
            'simple_select' => function () {
                return Agent::where('status', 'active')->count();
            },
            'complex_join' => function () {
                return DB::table('agents')
                    ->leftJoin('agent_wallets', 'agents.agent_id', '=', 'agent_wallets.agent_id')
                    ->leftJoin('agent_compliance', 'agents.agent_id', '=', 'agent_compliance.agent_id')
                    ->where('agents.status', 'active')
                    ->select('agents.*', 'agent_wallets.balance', 'agent_compliance.kyc_status')
                    ->get();
            },
            'aggregation' => function () {
                return DB::table('agent_transactions')
                    ->selectRaw('agent_id, COUNT(*) as transaction_count, SUM(amount) as total_amount')
                    ->groupBy('agent_id')
                    ->having('transaction_count', '>', 10)
                    ->get();
            },
        ];

        $results = [];

        foreach ($queries as $name => $query) {
            $startTime = microtime(true);

            for ($i = 0; $i < 10; $i++) {
                $query();
            }

            $endTime = microtime(true);
            $avgTime = (($endTime - $startTime) / 10) * 1000; // ms

            $results[$name] = $avgTime;

            $this->assertLessThan(
                50, // Maximum 50ms per query
                $avgTime,
                "Query '{$name}' ({$avgTime}ms) exceeds threshold"
            );
        }

        // Log performance metrics
        foreach ($results as $name => $time) {
            $this->logPerformanceMetric("query_{$name}", $time, 'ms');
        }
    }

    /**
     * Test API endpoint performance under load.
     */
    public function test_api_endpoint_performance(): void
    {
        $agent = Agent::factory()->create();

        $endpoints = [
            '/api/ap2/agents'                     => 'GET',
            '/api/ap2/agents/' . $agent->agent_id => 'GET',
            '/api/a2a/validate'                   => 'POST',
        ];

        $results = [];

        foreach ($endpoints as $endpoint => $method) {
            $times = [];

            for ($i = 0; $i < 50; $i++) {
                $startTime = microtime(true);

                if ($method === 'GET') {
                    $response = $this->getJson($endpoint);
                } else {
                    $response = $this->postJson($endpoint, [
                        'header' => [
                            'version'    => '2.0',
                            'message_id' => 'test_' . $i,
                            'timestamp'  => now()->toIso8601String(),
                            'from'       => 'did:ap2:sender',
                            'to'         => 'did:ap2:recipient',
                        ],
                        'body'      => ['type' => 'test', 'content' => []],
                        'signature' => 'test',
                    ]);
                }

                $endTime = microtime(true);

                if ($response->status() < 500) {
                    $times[] = ($endTime - $startTime) * 1000;
                }
            }

            if (count($times) > 0) {
                $avgTime = array_sum($times) / count($times);
                $results[$endpoint] = $avgTime;

                $this->assertLessThan(
                    100, // Maximum 100ms per request
                    $avgTime,
                    "Endpoint '{$endpoint}' ({$avgTime}ms) exceeds threshold"
                );
            }
        }

        // Log performance metrics
        foreach ($results as $endpoint => $time) {
            $this->logPerformanceMetric(
                'endpoint_' . str_replace('/', '_', $endpoint),
                $time,
                'ms'
            );
        }
    }

    /**
     * Calculate percentile from array of values.
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;

        return $values[$index] ?? 0;
    }

    /**
     * Log performance metric for analysis.
     */
    private function logPerformanceMetric(string $metric, float $value, string $unit): void
    {
        // In a real application, this would send metrics to a monitoring service
        // For testing, we'll just log to the test output
        echo sprintf(
            "\n[PERFORMANCE] %s: %.2f %s",
            $metric,
            $value,
            $unit
        );

        // Store in database for trend analysis
        DB::table('performance_metrics')->insert([
            'metric'     => $metric,
            'value'      => $value,
            'unit'       => $unit,
            'test_run'   => class_basename($this),
            'created_at' => now(),
        ]);
    }
}
