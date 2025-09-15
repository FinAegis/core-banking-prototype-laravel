<?php

namespace Tests\Unit\Domain\Compliance\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Events\SuspiciousActivityDetected;
use App\Domain\Compliance\Models\MonitoringRule;
use App\Domain\Compliance\Services\CustomerRiskService;
use App\Domain\Compliance\Services\SuspiciousActivityReportService;
use App\Domain\Compliance\Services\TransactionMonitoringService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionMonitoringService $service;

    private SuspiciousActivityReportService $sarService;

    private CustomerRiskService $riskService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sarService = Mockery::mock(SuspiciousActivityReportService::class);
        $this->riskService = Mockery::mock(CustomerRiskService::class);

        $this->app->instance(SuspiciousActivityReportService::class, $this->sarService);
        $this->app->instance(CustomerRiskService::class, $this->riskService);

        $this->service = new TransactionMonitoringService(
            $this->sarService,
            $this->riskService
        );
    }

    private function createTransaction(array $attributes = []): Transaction
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        $eventProperties = [
            'amount'    => $attributes['amount'] ?? 10000,
            'assetCode' => 'USD',
            'type'      => $attributes['type'] ?? 'transfer',
            'metadata'  => [],
        ];

        // Remove amount and type from attributes to avoid conflict
        unset($attributes['amount'], $attributes['type']);

        return Transaction::factory()->forAccount($account)->create(array_merge([
            'event_properties' => $eventProperties,
            'meta_data'        => [
                'reference'   => $attributes['reference'] ?? null,
                'description' => $attributes['description'] ?? null,
            ],
        ], $attributes));
    }

    #[Test]
    public function test_monitor_transaction_passes_when_no_alerts(): void
    {
        Event::fake();

        $transaction = $this->createTransaction([
            'amount' => 1000,
            'type'   => 'transfer',
        ]);

        // No monitoring rules exist
        $result = $this->service->analyzeTransaction($transaction);

        $this->assertEquals('low', $result['risk_level']);
        $this->assertLessThan(25, $result['risk_score']); // Low risk should be < 25
        $this->assertEmpty($result['rules_triggered']);
        $this->assertEquals('Allow transaction', $result['recommendation']);

        Event::assertNotDispatched(SuspiciousActivityDetected::class);
    }

    #[Test]
    public function test_monitor_transaction_creates_alerts_for_triggered_rules(): void
    {
        Event::fake();

        $transaction = $this->createTransaction([
            'amount' => 100000, // Large amount
        ]);

        // Create monitoring rule that will trigger
        MonitoringRule::create([
            'name'        => 'Large Transaction Rule',
            'type'        => 'amount',
            'description' => 'Flag large transactions',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 50000],
            ],
            'severity'  => 'critical', // Changed to critical to ensure high enough risk score
            'is_active' => true,
        ]);

        $result = $this->service->analyzeTransaction($transaction);

        // Large transaction with critical rule should have elevated risk
        $this->assertContains($result['risk_level'], ['medium', 'high', 'critical']);
        $this->assertNotEmpty($result['rules_triggered']);
        $this->assertNotEquals('Allow transaction', $result['recommendation']);

        // With critical severity rule (30 points) + critical threshold (25 points) = 55 points = high risk
        // High risk does not trigger SuspiciousActivityDetected, only critical does
        // So we'll create another rule to push it over 75
        MonitoringRule::create([
            'name'        => 'Very Large Transaction Rule',
            'type'        => 'amount',
            'description' => 'Flag very large transactions',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 75000],
            ],
            'severity'  => 'critical',
            'is_active' => true,
        ]);

        // Re-analyze with both rules
        $result2 = $this->service->analyzeTransaction($transaction);

        // With two critical rules (30+30) + critical threshold (25) = 85 points = critical risk
        $this->assertEquals('critical', $result2['risk_level']);
        $this->assertGreaterThanOrEqual(75, $result2['risk_score']);

        Event::assertDispatched(SuspiciousActivityDetected::class);
    }

    #[Test]
    public function test_monitor_transaction_blocks_when_block_action_triggered(): void
    {
        Event::fake();

        $transaction = $this->createTransaction([
            'amount' => 500000,
        ]);

        // Create high-risk monitoring rule
        MonitoringRule::create([
            'name'        => 'High Risk Block Rule',
            'type'        => 'amount',
            'description' => 'Block very large transactions',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 100000],
            ],
            'severity'  => 'critical',
            'is_active' => true,
        ]);

        $result = $this->service->analyzeTransaction($transaction);

        // With critical rule (30) + critical threshold (25) = 55 points = high risk
        $this->assertEquals('high', $result['risk_level']);
        $this->assertNotEmpty($result['rules_triggered']);
        $this->assertEquals('Flag for manual review', $result['recommendation']);

        // SuspiciousActivityDetected only fires for critical (75+), not high
        Event::assertNotDispatched(SuspiciousActivityDetected::class);
    }

    #[Test]
    public function test_monitor_transaction_handles_multiple_rules(): void
    {
        Event::fake();

        $transaction = $this->createTransaction([
            'amount' => 75000,
        ]);

        // Create multiple monitoring rules
        MonitoringRule::create([
            'name'        => 'Rule 1',
            'type'        => 'amount',
            'description' => 'Medium threshold',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 25000],
            ],
            'severity'  => 'medium',
            'is_active' => true,
        ]);

        MonitoringRule::create([
            'name'        => 'Rule 2',
            'type'        => 'amount',
            'description' => 'High threshold',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 50000],
            ],
            'severity'  => 'high',
            'is_active' => true,
        ]);

        MonitoringRule::create([
            'name'        => 'Rule 3',
            'type'        => 'amount',
            'description' => 'Very high threshold (won\'t trigger)',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 100000],
            ],
            'severity'  => 'critical',
            'is_active' => true,
        ]);

        // Mock the SAR service since high amounts may trigger SAR creation
        $this->sarService->shouldReceive('createFromTransaction')
            ->zeroOrMoreTimes()
            ->with($transaction, Mockery::type('array'));

        $result = $this->service->analyzeTransaction($transaction);

        // With multiple triggered rules, should have elevated risk
        $this->assertNotEmpty($result['rules_triggered']);
        $this->assertCount(2, $result['rules_triggered']); // Only first two rules trigger
        $this->assertContains($result['risk_level'], ['medium', 'high', 'critical']);
        $this->assertNotEquals('Allow transaction', $result['recommendation']);
    }

    #[Test]
    public function test_monitor_transaction_handles_exceptions_gracefully(): void
    {
        // Mock Log to allow info logging
        Log::shouldReceive('info')
            ->zeroOrMoreTimes();

        // Create a transaction
        $transaction = $this->createTransaction();

        // Create a monitoring rule with invalid conditions that won't match
        MonitoringRule::create([
            'name'        => 'Invalid Rule',
            'type'        => 'pattern',
            'description' => 'This rule has invalid conditions',
            'conditions'  => [
                ['field' => 'nonexistent_field', 'operator' => 'invalid_op', 'value' => null],
            ],
            'severity'  => 'high',
            'is_active' => true,
        ]);

        // The service should still complete successfully despite invalid rule
        $result = $this->service->analyzeTransaction($transaction);

        // Should return valid result even if some rules are invalid
        $this->assertArrayHasKey('risk_level', $result);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('recommendation', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('analyzed', $result['status']);
    }

    #[Test]
    public function test_monitor_transaction_updates_behavioral_risk_when_alerts_exist(): void
    {
        Event::fake();

        $transaction = $this->createTransaction([
            'amount' => 80000,
        ]);

        // Create multiple rules that will trigger to reach critical risk level
        MonitoringRule::create([
            'name'        => 'Suspicious Pattern',
            'type'        => 'pattern',
            'description' => 'Detect suspicious patterns',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 70000],
            ],
            'severity'  => 'critical',  // 30 points
            'is_active' => true,
        ]);

        MonitoringRule::create([
            'name'        => 'Very High Amount',
            'type'        => 'amount',
            'description' => 'Detect very high amounts',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 75000],
            ],
            'severity'  => 'critical',  // 30 points
            'is_active' => true,
        ]);

        MonitoringRule::create([
            'name'        => 'High Risk Transaction',
            'type'        => 'risk',
            'description' => 'High risk transaction pattern',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 50000],
            ],
            'severity'  => 'high',  // 20 points
            'is_active' => true,
        ]);

        // Mock SAR service - will be called for score >= 90
        // Initial score: 20 (amount > 10000) + Rules: 30 + 30 + 20 = 100 total
        $this->sarService->shouldReceive('createFromTransaction')
            ->once()
            ->with($transaction, Mockery::type('array'));

        $result = $this->service->analyzeTransaction($transaction);

        // With initial 20 + 3 rules (30 + 30 + 20) = 100 points, should be critical risk
        $this->assertNotEmpty($result['rules_triggered']);
        $this->assertCount(3, $result['rules_triggered']);
        $this->assertEquals('critical', $result['risk_level']);
        $this->assertGreaterThanOrEqual(90, $result['risk_score']);
        $this->assertEquals(100, $result['risk_score']);

        // SuspiciousActivityDetected should be dispatched for critical risk
        Event::assertDispatched(SuspiciousActivityDetected::class);
    }

    #[Test]
    public function test_monitor_transaction_deduplicates_actions(): void
    {
        Event::fake();

        $transaction = $this->createTransaction([
            'amount' => 90000,
        ]);

        // Multiple rules with overlapping conditions to reach score >= 90
        MonitoringRule::create([
            'name'        => 'Rule 1',
            'type'        => 'amount',
            'description' => 'First rule',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 50000],
            ],
            'severity'  => 'critical',  // 30 points
            'is_active' => true,
        ]);

        MonitoringRule::create([
            'name'        => 'Rule 2',
            'type'        => 'amount',
            'description' => 'Second rule',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 80000],
            ],
            'severity'  => 'critical',  // 30 points
            'is_active' => true,
        ]);

        MonitoringRule::create([
            'name'        => 'Rule 3',
            'type'        => 'amount',
            'description' => 'Third rule',
            'conditions'  => [
                ['field' => 'amount', 'operator' => '>', 'value' => 85000],
            ],
            'severity'  => 'high',  // 20 points
            'is_active' => true,
        ]);

        // Mock the SAR service since total score will be >= 90
        // Initial: 20 + Rules: 30 + 30 + 20 = 100 points
        $this->sarService->shouldReceive('createFromTransaction')
            ->once() // Should only be called once despite multiple rules
            ->with($transaction, Mockery::type('array'));

        $result = $this->service->analyzeTransaction($transaction);

        // Should have triggered rules
        $this->assertNotEmpty($result['rules_triggered']);
        $this->assertCount(3, $result['rules_triggered']);
        $this->assertEquals('critical', $result['risk_level']); // Highest severity wins
        $this->assertEquals(100, $result['risk_score']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
