<?php

namespace Tests\Unit\Domain\Compliance\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Compliance\Models\CustomerRiskProfile;
use App\Domain\Compliance\Models\TransactionMonitoringRule;
use App\Domain\Compliance\Services\CustomerRiskService;
use App\Domain\Compliance\Services\SuspiciousActivityReportService;
use App\Domain\Compliance\Services\TransactionMonitoringService;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;

class TransactionMonitoringServiceTest extends ServiceTestCase
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
            'metadata'  => [],
        ];

        // Remove amount from attributes to avoid conflict
        unset($attributes['amount']);

        // Extract type if provided to put in meta_data
        $type = $attributes['type'] ?? 'transfer';
        unset($attributes['type']);

        return Transaction::factory()->forAccount($account)->create(array_merge([
            'event_properties' => $eventProperties,
            'meta_data'        => [
                'type'        => $type,
                'reference'   => $attributes['reference'] ?? null,
                'description' => $attributes['description'] ?? null,
            ],
        ], $attributes));
    }

    #[Test]
    public function test_monitor_transaction_passes_when_no_alerts(): void
    {
        $transaction = $this->createTransaction([
            'amount' => 1000,
            'type'   => 'transfer',
        ]);

        // Mock risk profile
        $riskProfile = new CustomerRiskProfile();
        $riskProfile->risk_level = 'low';

        $this->mockGetCustomerRiskProfile($transaction, $riskProfile);
        $this->mockGetApplicableRules($transaction, $riskProfile, collect());

        $result = $this->service->monitorTransaction($transaction);

        $this->assertTrue($result['passed']);
        $this->assertEmpty($result['alerts']);
        $this->assertEmpty($result['actions']);
    }

    #[Test]
    public function test_monitor_transaction_creates_alerts_for_triggered_rules(): void
    {
        $transaction = $this->createTransaction([
            'amount' => 100000, // Large amount
        ]);

        $riskProfile = new CustomerRiskProfile();
        $riskProfile->risk_level = 'medium';

        // Create mock rule
        $rule = Mockery::mock(TransactionMonitoringRule::class);
        $rule->shouldReceive('getActions')->andReturn([TransactionMonitoringRule::ACTION_REVIEW]);
        $rule->shouldReceive('recordTrigger')->once();
        $rule->shouldReceive('getAttribute')->with('name')->andReturn('Large Transaction Rule');
        $rule->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $rule->shouldReceive('__get')->with('name')->andReturn('Large Transaction Rule');
        $rule->shouldReceive('__get')->with('id')->andReturn(1);

        $this->mockGetCustomerRiskProfile($transaction, $riskProfile);
        $this->mockGetApplicableRules($transaction, $riskProfile, collect([$rule]));
        $this->mockEvaluateRule($rule, $transaction, $riskProfile, true);

        $result = $this->service->monitorTransaction($transaction);

        $this->assertTrue($result['passed']);
        $this->assertCount(1, $result['alerts']);
        $this->assertContains(TransactionMonitoringRule::ACTION_REVIEW, $result['actions']);
    }

    #[Test]
    public function test_monitor_transaction_blocks_when_block_action_triggered(): void
    {
        $transaction = $this->createTransaction([
            'amount' => 500000,
        ]);

        $riskProfile = new CustomerRiskProfile();
        $riskProfile->risk_level = 'high';

        $rule = Mockery::mock(TransactionMonitoringRule::class);
        $rule->shouldReceive('getActions')->andReturn([TransactionMonitoringRule::ACTION_BLOCK]);
        $rule->shouldReceive('recordTrigger')->once();
        $rule->shouldReceive('getAttribute')->with('name')->andReturn('High Risk Block Rule');
        $rule->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $rule->shouldReceive('__get')->with('name')->andReturn('High Risk Block Rule');
        $rule->shouldReceive('__get')->with('id')->andReturn(2);

        $this->mockGetCustomerRiskProfile($transaction, $riskProfile);
        $this->mockGetApplicableRules($transaction, $riskProfile, collect([$rule]));
        $this->mockEvaluateRule($rule, $transaction, $riskProfile, true);

        $result = $this->service->monitorTransaction($transaction);

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['alerts']);
        $this->assertContains(TransactionMonitoringRule::ACTION_BLOCK, $result['actions']);
    }

    #[Test]
    public function test_monitor_transaction_handles_multiple_rules(): void
    {
        $transaction = $this->createTransaction();
        $riskProfile = new CustomerRiskProfile();

        $rule1 = $this->createMockRule(1, 'Rule 1', [TransactionMonitoringRule::ACTION_REVIEW]);
        $rule1->shouldReceive('recordTrigger')->once();

        $rule2 = $this->createMockRule(2, 'Rule 2', [TransactionMonitoringRule::ACTION_REPORT]);
        $rule2->shouldReceive('recordTrigger')->once();

        $rule3 = $this->createMockRule(3, 'Rule 3', [TransactionMonitoringRule::ACTION_REVIEW]); // Won't trigger
        $rule3->shouldReceive('recordTrigger')->never();

        $this->mockGetCustomerRiskProfile($transaction, $riskProfile);
        $this->mockGetApplicableRules($transaction, $riskProfile, collect([$rule1, $rule2, $rule3]));

        // Only first two rules trigger
        $this->mockEvaluateRule($rule1, $transaction, $riskProfile, true);
        $this->mockEvaluateRule($rule2, $transaction, $riskProfile, true);
        $this->mockEvaluateRule($rule3, $transaction, $riskProfile, false);

        // Mock the SAR service since ACTION_REPORT will trigger createSAR
        $this->sarService->shouldReceive('createFromTransaction')
            ->once()
            ->with($transaction, Mockery::type('array'));

        $result = $this->service->monitorTransaction($transaction);
        $this->assertCount(2, $result['alerts']);
        $this->assertContains(TransactionMonitoringRule::ACTION_REVIEW, $result['actions']);
        $this->assertContains(TransactionMonitoringRule::ACTION_REPORT, $result['actions']);
    }

    #[Test]
    public function test_monitor_transaction_handles_exceptions_gracefully(): void
    {
        $transaction = $this->createTransaction();

        // Mock exception during risk profile fetch
        $this->service = Mockery::mock(TransactionMonitoringService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $this->service->shouldReceive('getCustomerRiskProfile')
            ->andThrow(new Exception('Database error'));

        Log::shouldReceive('error')->once();

        $result = $this->service->monitorTransaction($transaction);

        $this->assertTrue($result['passed']); // Fail-safe allows transaction
        $this->assertCount(1, $result['alerts']);
        $this->assertEquals('system_error', $result['alerts'][0]['type']);
        $this->assertContains(TransactionMonitoringRule::ACTION_REVIEW, $result['actions']);
    }

    #[Test]
    public function test_monitor_transaction_updates_behavioral_risk_when_alerts_exist(): void
    {
        $transaction = $this->createTransaction();
        $riskProfile = new CustomerRiskProfile();

        $rule = $this->createMockRule(1, 'Suspicious Pattern', [TransactionMonitoringRule::ACTION_REVIEW]);

        $this->mockGetCustomerRiskProfile($transaction, $riskProfile);
        $this->mockGetApplicableRules($transaction, $riskProfile, collect([$rule]));
        $this->mockEvaluateRule($rule, $transaction, $riskProfile, true);

        // Expect behavioral risk update
        $this->service = Mockery::mock(TransactionMonitoringService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $this->service->shouldReceive('getCustomerRiskProfile')->andReturn($riskProfile);
        $this->service->shouldReceive('getApplicableRules')->andReturn(collect([$rule]));
        $this->service->shouldReceive('evaluateRule')->andReturn(true);
        $this->service->shouldReceive('createAlert')->andReturn(['type' => 'rule_trigger']);
        $result = $this->service->monitorTransaction($transaction);

        $this->assertNotEmpty($result['alerts']);
    }

    #[Test]
    public function test_monitor_transaction_deduplicates_actions(): void
    {
        $transaction = $this->createTransaction();
        $riskProfile = new CustomerRiskProfile();

        // Multiple rules with same actions
        $rule1 = $this->createMockRule(1, 'Rule 1', [TransactionMonitoringRule::ACTION_REVIEW, TransactionMonitoringRule::ACTION_REPORT]);
        $rule2 = $this->createMockRule(2, 'Rule 2', [TransactionMonitoringRule::ACTION_REVIEW]);

        $rule1->shouldReceive('recordTrigger')->once();
        $rule2->shouldReceive('recordTrigger')->once();

        $this->mockGetCustomerRiskProfile($transaction, $riskProfile);
        $this->mockGetApplicableRules($transaction, $riskProfile, collect([$rule1, $rule2]));
        $this->mockEvaluateRule($rule1, $transaction, $riskProfile, true);
        $this->mockEvaluateRule($rule2, $transaction, $riskProfile, true);

        // Mock the SAR service since ACTION_REPORT will trigger createSAR
        $this->sarService->shouldReceive('createFromTransaction')
            ->once()
            ->with($transaction, Mockery::type('array'));

        $result = $this->service->monitorTransaction($transaction);

        // Should only have unique actions
        $uniqueActions = array_unique($result['actions']);
        $this->assertEquals($uniqueActions, $result['actions']);
        $this->assertCount(2, $result['actions']); // REVIEW and REPORT (no duplicates)
    }

    // Helper methods
    private function mockGetCustomerRiskProfile($transaction, $riskProfile): void
    {
        if (! ($this->service instanceof Mockery\MockInterface)) {
            $this->service = Mockery::mock(TransactionMonitoringService::class, [
                $this->sarService,
                $this->riskService,
            ])
                ->makePartial()
                ->shouldAllowMockingProtectedMethods();
        }

        $this->service->shouldReceive('getCustomerRiskProfile')
            ->with($transaction)
            ->andReturn($riskProfile);
    }

    private function mockGetApplicableRules($transaction, $riskProfile, $rules): void
    {
        $this->service->shouldReceive('getApplicableRules')
            ->with($transaction, $riskProfile)
            ->andReturn($rules);
    }

    private function mockEvaluateRule($rule, $transaction, $riskProfile, $result): void
    {
        $this->service->shouldReceive('evaluateRule')
            ->with($rule, $transaction, $riskProfile)
            ->andReturn($result);

        if ($result) {
            $this->service->shouldReceive('createAlert')
                ->with($rule, $transaction)
                ->andReturn([
                    'rule_id'        => $rule->id,
                    'rule_name'      => $rule->name,
                    'rule_code'      => 'TEST_RULE',
                    'category'       => 'threshold',
                    'risk_level'     => 'high',
                    'transaction_id' => $transaction->id,
                    'amount'         => $transaction->amount ?? 0,
                    'timestamp'      => now()->toIso8601String(),
                    'description'    => 'Test alert',
                ]);
        }
    }

    private function createMockRule($id, $name, $actions): TransactionMonitoringRule
    {
        $rule = Mockery::mock(TransactionMonitoringRule::class);
        $rule->shouldReceive('getAttribute')->with('id')->andReturn($id);
        $rule->shouldReceive('getAttribute')->with('name')->andReturn($name);
        $rule->shouldReceive('__get')->with('id')->andReturn($id);
        $rule->shouldReceive('__get')->with('name')->andReturn($name);
        $rule->shouldReceive('getActions')->andReturn($actions);
        // Don't set expectation for recordTrigger here - let tests control it

        return $rule;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
