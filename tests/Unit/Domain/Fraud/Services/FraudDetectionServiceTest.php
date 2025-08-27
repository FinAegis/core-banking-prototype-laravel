<?php

namespace Tests\Unit\Domain\Fraud\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Transaction;
use App\Domain\Fraud\Events\ChallengeRequired;
use App\Domain\Fraud\Events\FraudDetected;
use App\Domain\Fraud\Events\TransactionBlocked;
use App\Domain\Fraud\Models\FraudScore;
use App\Domain\Fraud\Services\BehavioralAnalysisService;
use App\Domain\Fraud\Services\DeviceFingerprintService;
use App\Domain\Fraud\Services\FraudCaseService;
use App\Domain\Fraud\Services\FraudDetectionService;
use App\Domain\Fraud\Services\MachineLearningService;
use App\Domain\Fraud\Services\RuleEngineService;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;

class FraudDetectionServiceTest extends ServiceTestCase
{
    private FraudDetectionService $service;

    private RuleEngineService $ruleEngine;

    private BehavioralAnalysisService $behavioralAnalysis;

    private DeviceFingerprintService $deviceService;

    private MachineLearningService $mlService;

    private FraudCaseService $caseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ruleEngine = Mockery::mock(RuleEngineService::class);
        $this->behavioralAnalysis = Mockery::mock(BehavioralAnalysisService::class);
        $this->deviceService = Mockery::mock(DeviceFingerprintService::class);
        $this->mlService = Mockery::mock(MachineLearningService::class);
        $this->caseService = Mockery::mock(FraudCaseService::class);

        $this->service = new FraudDetectionService(
            $this->ruleEngine,
            $this->behavioralAnalysis,
            $this->deviceService,
            $this->mlService,
            $this->caseService
        );

        Event::fake();
    }

    #[Test]
    public function test_analyze_transaction_creates_fraud_score(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        $transaction = Transaction::factory()->forAccount($account)->create([
            'event_properties' => [
                'amount'    => 10000,
                'assetCode' => 'USD',
                'metadata'  => [],
            ],
        ]);

        // Mock service responses
        $this->mockServicesForLowRisk();

        $fraudScore = $this->service->analyzeTransaction($transaction);

        $this->assertInstanceOf(FraudScore::class, $fraudScore);
        $this->assertEquals($transaction->id, $fraudScore->entity_id);
        $this->assertEquals(Transaction::class, $fraudScore->entity_type);
        $this->assertEquals(FraudScore::SCORE_TYPE_REAL_TIME, $fraudScore->score_type);
    }

    #[Test]
    public function test_analyze_transaction_with_low_risk_passes(): void
    {
        $transaction = $this->createTransaction();

        $this->mockServicesForLowRisk();

        $fraudScore = $this->service->analyzeTransaction($transaction);

        $this->assertLessThan(40, $fraudScore->total_score);
        $this->assertEquals('allow', $fraudScore->decision);
        $this->assertEquals('low', $fraudScore->risk_level);

        Event::assertNotDispatched(FraudDetected::class);
        Event::assertNotDispatched(TransactionBlocked::class);
    }

    #[Test]
    public function test_analyze_transaction_with_medium_risk_requires_challenge(): void
    {
        $transaction = $this->createTransaction();

        $this->mockServicesForMediumRisk();

        $fraudScore = $this->service->analyzeTransaction($transaction);

        $this->assertBetween(40, 60, $fraudScore->total_score);
        $this->assertEquals('challenge', $fraudScore->decision);
        $this->assertEquals('medium', $fraudScore->risk_level);

        Event::assertDispatched(ChallengeRequired::class);
        Event::assertNotDispatched(TransactionBlocked::class);
    }

    #[Test]
    public function test_analyze_transaction_with_high_risk_blocks_transaction(): void
    {
        $transaction = $this->createTransaction();

        $this->mockServicesForHighRisk();

        $fraudScore = $this->service->analyzeTransaction($transaction);

        $this->assertGreaterThan(80, $fraudScore->total_score);
        $this->assertEquals('block', $fraudScore->decision);
        $this->assertEquals('very_high', $fraudScore->risk_level);

        Event::assertDispatched(FraudDetected::class);
        Event::assertDispatched(TransactionBlocked::class);
    }

    #[Test]
    public function test_analyze_transaction_with_ml_enabled(): void
    {
        $transaction = $this->createTransaction();

        // Set up mocks but with ML enabled
        $this->ruleEngine->shouldReceive('evaluate')
            ->withAnyArgs()
            ->andReturn([
                'total_score'     => 50,
                'triggered_rules' => [],
                'blocking_rules'  => [],
                'rule_scores'     => [],
                'rule_details'    => [],
            ]);

        $this->behavioralAnalysis->shouldReceive('analyze')
            ->withAnyArgs()
            ->andReturn([
                'risk_score'   => 20,
                'anomalies'    => [],
                'risk_factors' => [],
            ]);

        $this->behavioralAnalysis->shouldReceive('updateProfile')
            ->andReturn(null);

        $this->deviceService->shouldReceive('analyzeDevice')
            ->withAnyArgs()
            ->andReturn([
                'risk_score'   => 20,
                'is_known'     => true,
                'risk_factors' => [],
            ]);

        // ML is enabled for this test
        $this->mlService->shouldReceive('isEnabled')->andReturn(true);
        $this->mlService->shouldReceive('predict')
            ->once()
            ->andReturn(['score' => 0.15, 'confidence' => 0.95]);

        $this->caseService->shouldReceive('createFromFraudScore')
            ->withAnyArgs()
            ->andReturn(Mockery::mock(\App\Domain\Fraud\Models\FraudCase::class));

        $fraudScore = $this->service->analyzeTransaction($transaction);

        $this->assertNotNull($fraudScore->analysis_results, 'Analysis results should not be null');
        $this->assertIsArray($fraudScore->analysis_results, 'Analysis results should be an array');

        // Debug what's actually in analysis_results
        if (! isset($fraudScore->analysis_results['ml_prediction'])) {
            $this->fail('ML prediction not found in analysis_results. Contents: ' . json_encode($fraudScore->analysis_results));
        }

        $this->assertArrayHasKey('ml_prediction', $fraudScore->analysis_results);
        $this->assertEquals(0.15, $fraudScore->analysis_results['ml_prediction']['score']);
    }

    #[Test]
    public function test_analyze_transaction_handles_device_data(): void
    {
        $transaction = $this->createTransaction();
        $deviceData = [
            'fingerprint' => 'device123',
            'ip'          => '192.168.1.1',
            'user_agent'  => 'Mozilla/5.0',
        ];

        $this->mockServicesForLowRisk();
        $this->deviceService->shouldReceive('analyzeDevice')
            ->with(Mockery::on(function ($data) use ($deviceData) {
                return $data['fingerprint'] === $deviceData['fingerprint'];
            }))
            ->andReturn(['risk_score' => 10, 'is_known' => true]);

        $fraudScore = $this->service->analyzeTransaction($transaction, ['device_data' => $deviceData]);

        $this->assertArrayHasKey('device_analysis', $fraudScore->analysis_results);
        $this->assertTrue($fraudScore->analysis_results['device_analysis']['is_known']);
    }

    #[Test]
    public function test_analyze_user_activity_for_historical_analysis(): void
    {
        $user = User::factory()->create();
        $startDate = now()->subDays(30);
        $endDate = now();

        // Mock transaction history
        $this->behavioralAnalysis->shouldReceive('getHistoricalBehavior')
            ->with($user, $startDate, $endDate)
            ->andReturn([
                'avg_transaction_amount' => 5000,
                'transaction_count'      => 25,
                'unusual_patterns'       => [],
            ]);

        $analysis = $this->service->analyzeUserActivity($user, $startDate, $endDate);

        $this->assertArrayHasKey('behavioral_analysis', $analysis);
        $this->assertArrayHasKey('risk_indicators', $analysis);
        $this->assertArrayHasKey('recommendations', $analysis);
    }

    #[Test]
    public function test_recalculate_score_updates_existing_score(): void
    {
        $transaction = $this->createTransaction();
        $fraudScore = FraudScore::factory()->create([
            'entity_id'   => $transaction->id,
            'entity_type' => Transaction::class,
            'total_score' => 25,
            'decision'    => 'allow',
        ]);

        $this->mockServicesForMediumRisk();

        $updatedScore = $this->service->recalculateScore($fraudScore);

        $this->assertGreaterThan(25, $updatedScore->total_score);
        $this->assertEquals('challenge', $updatedScore->decision);
        $this->assertArrayHasKey('recalculation_reason', $updatedScore->metadata);
    }

    #[Test]
    public function test_get_fraud_indicators_returns_risk_factors(): void
    {
        $transaction = $this->createTransaction(['amount' => 50000]);

        $indicators = $this->service->getFraudIndicators($transaction);

        $this->assertIsArray($indicators);
        $this->assertArrayHasKey('transaction_indicators', $indicators);
        $this->assertArrayHasKey('user_indicators', $indicators);
        $this->assertArrayHasKey('contextual_indicators', $indicators);
    }

    // Helper methods
    private function createTransaction(array $attributes = []): Transaction
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);

        $eventProperties = [
            'amount'    => $attributes['amount'] ?? 10000,
            'assetCode' => 'USD',
            'metadata'  => [],
        ];

        $metaData = [
            'type' => $attributes['type'] ?? 'transfer',
        ];

        // Remove amount and type from attributes to avoid conflict
        unset($attributes['amount'], $attributes['type']);

        return Transaction::factory()->forAccount($account)->create(array_merge([
            'event_properties' => $eventProperties,
            'meta_data'        => $metaData,
        ], $attributes));
    }

    private function mockServicesForLowRisk(): void
    {
        $this->ruleEngine->shouldReceive('evaluate')
            ->withAnyArgs()
            ->andReturn([
                'total_score'     => 50,
                'triggered_rules' => [],
                'blocking_rules'  => [],
                'rule_scores'     => [],
                'rule_details'    => [],
            ]);

        $this->behavioralAnalysis->shouldReceive('analyze')
            ->withAnyArgs()
            ->andReturn([
                'risk_score'   => 20,
                'anomalies'    => [],
                'risk_factors' => [],
            ]);

        $this->behavioralAnalysis->shouldReceive('updateProfile')
            ->andReturn(null);

        $this->deviceService->shouldReceive('analyzeDevice')
            ->withAnyArgs()
            ->andReturn([
                'risk_score'   => 20,
                'is_known'     => true,
                'risk_factors' => [],
            ]);

        $this->mlService->shouldReceive('isEnabled')->andReturn(false);

        $this->caseService->shouldReceive('createFromFraudScore')
            ->withAnyArgs()
            ->andReturn(Mockery::mock(\App\Domain\Fraud\Models\FraudCase::class));
    }

    private function mockServicesForMediumRisk(): void
    {
        $this->ruleEngine->shouldReceive('evaluate')
            ->withAnyArgs()
            ->andReturn([
                'total_score'     => 70,
                'triggered_rules' => ['unusual_amount'],
                'blocking_rules'  => [],
                'rule_scores'     => [],
                'rule_details'    => [],
            ]);

        $this->behavioralAnalysis->shouldReceive('analyze')
            ->withAnyArgs()
            ->andReturn([
                'risk_score'   => 50,
                'anomalies'    => ['time_pattern'],
                'risk_factors' => [],
            ]);

        $this->behavioralAnalysis->shouldReceive('updateProfile')
            ->andReturn(null);

        $this->deviceService->shouldReceive('analyzeDevice')
            ->withAnyArgs()
            ->andReturn([
                'risk_score'   => 40,
                'is_known'     => false,
                'risk_factors' => [],
            ]);

        $this->mlService->shouldReceive('isEnabled')->andReturn(false);

        $this->caseService->shouldReceive('createFromFraudScore')
            ->withAnyArgs()
            ->andReturn(Mockery::mock(\App\Domain\Fraud\Models\FraudCase::class));
    }

    private function mockServicesForHighRisk(): void
    {
        $this->ruleEngine->shouldReceive('evaluate')
            ->withAnyArgs()
            ->andReturn([
                'total_score'     => 150,
                'triggered_rules' => ['blacklist_match', 'velocity_check'],
                'blocking_rules'  => [],
                'rule_scores'     => [],
                'rule_details'    => [],
            ]);

        $this->behavioralAnalysis->shouldReceive('analyze')
            ->withAnyArgs()
            ->andReturn([
                'risk_score'   => 100,
                'anomalies'    => ['location_jump', 'unusual_merchant'],
                'risk_factors' => [],
            ]);

        $this->behavioralAnalysis->shouldReceive('updateProfile')
            ->andReturn(null);

        $this->deviceService->shouldReceive('analyzeDevice')
            ->withAnyArgs()
            ->andReturn([
                'risk_score'   => 90,
                'is_known'     => false,
                'is_vpn'       => true,
                'risk_factors' => [],
            ]);

        $this->mlService->shouldReceive('isEnabled')->andReturn(false);

        $this->caseService->shouldReceive('createFromFraudScore')
            ->withAnyArgs()
            ->andReturn(Mockery::mock(\App\Domain\Fraud\Models\FraudCase::class));

        // No need for createCase expectation - createFromFraudScore is called instead
    }

    private function assertBetween($min, $max, $value): void
    {
        $this->assertGreaterThanOrEqual($min, $value);
        $this->assertLessThanOrEqual($max, $value);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
