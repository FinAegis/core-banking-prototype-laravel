<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Compliance;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Events\RealTimeAlertGenerated;
use App\Domain\Compliance\Services\TransactionMonitoringService;
use App\Domain\Compliance\Streaming\PatternDetectionEngine;
use App\Domain\Compliance\Streaming\TransactionStreamProcessor;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class TransactionStreamProcessorTest extends TestCase
{
    use RefreshDatabase;

    private TransactionStreamProcessor $processor;

    private $monitoringServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear the transaction factory version cache to prevent duplicate version conflicts
        \Database\Factories\TransactionFactory::clearVersionCache();

        $this->monitoringServiceMock = $this->mock(TransactionMonitoringService::class);
        $patternEngine = new PatternDetectionEngine();

        $this->processor = new TransactionStreamProcessor(
            $this->monitoringServiceMock,
            $patternEngine
        );

        Event::fake();
        Cache::flush();
    }

    public function test_processes_single_transaction_successfully(): void
    {
        // Arrange
        $transaction = Transaction::factory()->create([
            'event_properties' => [
                'amount'   => 5000,
                'type'     => 'transfer',
                'metadata' => [
                    'counterparty' => 'external_account_1',
                ],
            ],
        ]);

        $this->monitoringServiceMock
            ->shouldReceive('monitorTransaction')
            ->once()
            ->with(Mockery::type(Transaction::class))
            ->andReturn([
                'passed'  => true,
                'alerts'  => [],
                'actions' => [],
            ]);

        // Act
        $result = $this->processor->processTransaction($transaction);

        // Assert
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertArrayHasKey('processed_at', $result);
        $this->assertArrayHasKey('alerts', $result);
        $this->assertArrayHasKey('patterns', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertEquals($transaction->id, $result['transaction_id']);
    }

    public function test_detects_velocity_pattern(): void
    {
        // Arrange
        $account = Account::factory()->create();

        // Create multiple rapid transactions
        $transactions = [];
        for ($i = 0; $i < 6; $i++) {
            $transactions[] = Transaction::factory()->create([
                'account_id'       => $account->id,
                'aggregate_uuid'   => $account->uuid,
                'event_properties' => [
                    'amount' => 2000,
                    'type'   => 'transfer',
                ],
                'created_at' => now()->subMinutes(5 - $i),
            ]);
        }

        $this->monitoringServiceMock
            ->shouldReceive('monitorTransaction')
            ->andReturn([
                'passed'  => true,
                'alerts'  => [],
                'actions' => [],
            ]);

        // Act - Process all transactions to build up velocity pattern
        foreach ($transactions as $index => $transaction) {
            $result = $this->processor->processTransaction($transaction);

            // The velocity alert should trigger on the 6th transaction (index 5)
            if ($index === 5) {
                // Assert on the last transaction
                $this->assertNotEmpty($result['alerts']);
                $this->assertArrayHasKey('type', $result['alerts'][0]);
                $this->assertEquals('velocity_exceeded', $result['alerts'][0]['type']);
            }
        }
    }

    public function test_detects_structuring_pattern(): void
    {
        // Arrange
        $account = Account::factory()->create();

        // Create transactions just below reporting threshold
        $amounts = [9500, 9800, 9600, 9700];
        foreach ($amounts as $index => $amount) {
            $transaction = Transaction::factory()->create([
                'aggregate_uuid'   => $account->uuid,
                'event_properties' => [
                    'amount' => $amount,
                    'type'   => 'deposit',
                ],
                'created_at' => now()->subMinutes(30 - $index * 5), // Within last 30 minutes
            ]);

            // Load the account relationship explicitly
            $transaction->load('account');

            $this->monitoringServiceMock
                ->shouldReceive('monitorTransaction')
                ->with(Mockery::type(Transaction::class))
                ->andReturn([
                    'passed'  => true,
                    'alerts'  => [],
                    'actions' => [],
                ]);

            // Add to stream buffer
            $this->processor->processTransaction($transaction);
        }

        // Create final transaction
        $finalTransaction = Transaction::factory()->create([
            'aggregate_uuid'   => $account->uuid,
            'event_properties' => [
                'amount' => 9600,
                'type'   => 'deposit',
            ],
        ]);

        // Load the account relationship
        $finalTransaction->load('account');

        // Set up mock for final transaction (it's the 5th call)
        $this->monitoringServiceMock
            ->shouldReceive('monitorTransaction')
            ->with(Mockery::type(Transaction::class))
            ->andReturn([
                'passed'  => true,
                'alerts'  => [],
                'actions' => [],
            ]);

        // Act
        $result = $this->processor->processTransaction($finalTransaction);

        // Assert
        $this->assertNotEmpty($result['patterns']);
        $patterns = collect($result['patterns'])->pluck('type');
        $this->assertContains('structuring', $patterns);
    }

    public function test_emits_real_time_alert_event(): void
    {
        // Arrange
        $transaction = Transaction::factory()->create([
            'event_properties' => [
                'amount' => 15000,
                'type'   => 'transfer',
            ],
        ]);

        $this->monitoringServiceMock
            ->shouldReceive('monitorTransaction')
            ->once()
            ->with(Mockery::type(Transaction::class))
            ->andReturn([
                'passed' => false,
                'alerts' => [
                    [
                        'type'     => 'threshold_exceeded',
                        'severity' => 'high',
                    ],
                ],
                'actions' => ['review'],
            ]);

        // Act
        $this->processor->processTransaction($transaction);

        // Assert
        Event::assertDispatched(RealTimeAlertGenerated::class, function ($event) use ($transaction) {
            return $event->transaction->id === $transaction->id
                && ! empty($event->alertData['alerts']);
        });
    }

    public function test_processes_batch_transactions(): void
    {
        // Arrange
        $transactions = Transaction::factory()->count(5)->create([
            'event_properties' => [
                'amount' => 1000,
                'type'   => 'transfer',
            ],
        ]);

        $this->monitoringServiceMock
            ->shouldReceive('monitorTransaction')
            ->times(5)
            ->with(Mockery::type(Transaction::class))
            ->andReturn([
                'passed'  => true,
                'alerts'  => [],
                'actions' => [],
            ]);

        // Act
        $results = $this->processor->processBatch($transactions);

        // Assert
        $this->assertCount(5, $results);
        foreach ($transactions as $transaction) {
            $this->assertArrayHasKey($transaction->id, $results);
        }
    }

    public function test_updates_real_time_risk_metrics(): void
    {
        // Arrange
        $account = Account::factory()->create();
        $transaction = Transaction::factory()->create([
            'account_id'       => $account->id,
            'aggregate_uuid'   => $account->uuid,
            'event_properties' => [
                'amount' => 5000,
                'type'   => 'transfer',
            ],
        ]);

        $this->monitoringServiceMock
            ->shouldReceive('monitorTransaction')
            ->once()
            ->with(Mockery::type(Transaction::class))
            ->andReturn([
                'passed' => false,
                'alerts' => [
                    [
                        'type'     => 'suspicious_pattern',
                        'severity' => 'medium',
                    ],
                ],
                'actions' => ['review'],
            ]);

        // Act
        $this->processor->processTransaction($transaction);

        // Assert
        $metricsKey = "risk_metrics:{$account->id}";
        $metrics = Cache::get($metricsKey);

        $this->assertNotNull($metrics);
        $this->assertEquals(1, $metrics['transaction_count']);
        $this->assertEquals(1, $metrics['alert_count']);
        $this->assertGreaterThan(0, $metrics['risk_score']);
    }

    public function test_maintains_sliding_window_buffer(): void
    {
        // Arrange
        $account = Account::factory()->create();

        // Create many transactions to exceed window size
        for ($i = 0; $i < 150; $i++) {
            $transaction = Transaction::factory()->create([
                'account_id'       => $account->id,
                'aggregate_uuid'   => $account->uuid,
                'event_properties' => [
                    'amount' => rand(100, 5000),
                    'type'   => 'transfer',
                ],
                'created_at' => now()->subMinutes(150 - $i),
            ]);

            $this->monitoringServiceMock
                ->shouldReceive('monitorTransaction')
                ->with(Mockery::type(Transaction::class))
                ->andReturn([
                    'passed'  => true,
                    'alerts'  => [],
                    'actions' => [],
                ]);

            $this->processor->processTransaction($transaction);
        }

        // Act
        $bufferKey = "stream_buffer:{$account->id}";
        $buffer = Cache::get($bufferKey);

        // Assert
        $this->assertNotNull($buffer);
        $this->assertLessThanOrEqual(100, count($buffer)); // Window size limit
    }

    public function test_cross_references_with_ongoing_cases(): void
    {
        // Arrange
        $user = \App\Models\User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        // Set ongoing cases in cache
        $ongoingCasesKey = "ongoing_cases:{$user->id}";
        Cache::put($ongoingCasesKey, [
            ['case_id' => 'CASE-001', 'priority' => 'high'],
        ], 3600);

        $transaction = Transaction::factory()->create([
            'account_id'       => $account->id,
            'aggregate_uuid'   => $account->uuid,
            'event_properties' => [
                'amount' => 5000,
                'type'   => 'transfer',
            ],
        ]);

        $this->monitoringServiceMock
            ->shouldReceive('monitorTransaction')
            ->once()
            ->with(Mockery::type(Transaction::class))
            ->andReturn([
                'passed' => true,
                'alerts' => [
                    ['type' => 'test_alert'],
                ],
                'actions' => [],
            ]);

        // Act
        $result = $this->processor->processTransaction($transaction);

        // Assert
        $this->assertArrayHasKey('related_cases', $result);
        $this->assertArrayHasKey('requires_enhanced_monitoring', $result);
        $this->assertTrue($result['requires_enhanced_monitoring']);
        $this->assertNotEmpty($result['alerts'][0]['related_cases']);
    }

    public function test_handles_processing_errors_gracefully(): void
    {
        // Arrange
        $transaction = Transaction::factory()->create();

        $this->monitoringServiceMock
            ->shouldReceive('monitorTransaction')
            ->once()
            ->with(Mockery::type(Transaction::class))
            ->andThrow(new Exception('Monitoring service error'));

        // Act
        $result = $this->processor->processTransaction($transaction);

        // Assert
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('Monitoring service error', $result['error']);
    }
}
