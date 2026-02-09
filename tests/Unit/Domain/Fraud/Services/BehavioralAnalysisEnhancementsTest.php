<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fraud\Services;

use App\Domain\Fraud\Models\BehavioralProfile;
use App\Domain\Fraud\Services\BehavioralAnalysisService;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BehavioralAnalysisEnhancementsTest extends TestCase
{
    private BehavioralAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BehavioralAnalysisService();
    }

    private function createEstablishedProfile(array $overrides = []): BehavioralProfile
    {
        $user = User::factory()->create();

        return BehavioralProfile::create(array_merge([
            'user_id'                       => $user->id,
            'avg_transaction_amount'        => 1000,
            'transaction_amount_std_dev'    => 200,
            'avg_daily_transaction_count'   => 5,
            'avg_monthly_transaction_count' => 100,
            'max_daily_volume'              => 10000,
            'is_established'                => true,
            'total_transaction_count'       => 200,
            'days_since_first_transaction'  => 180,
            'typical_transaction_times'     => array_fill(0, 24, 4.17),
            'typical_transaction_days'      => array_fill(0, 7, 14.29),
            'common_locations'              => [],
            'location_history'              => [],
            'trusted_devices'               => [],
        ], $overrides));
    }

    #[Test]
    public function test_compute_adaptive_thresholds(): void
    {
        $profile = $this->createEstablishedProfile();

        $thresholds = $this->service->computeAdaptiveThresholds($profile);

        $this->assertArrayHasKey('amount_upper', $thresholds);
        $this->assertArrayHasKey('amount_lower', $thresholds);
        $this->assertArrayHasKey('daily_count_max', $thresholds);
        $this->assertArrayHasKey('daily_volume_max', $thresholds);

        // Upper should be mean + sensitivity * stddev = 1000 + 1.5 * 200 = 1300
        $this->assertGreaterThan(1000, $thresholds['amount_upper']);
        $this->assertLessThan(1000, $thresholds['amount_lower']);
        $this->assertGreaterThanOrEqual(0, $thresholds['amount_lower']);

        // Verify stored on profile
        $profile->refresh();
        $this->assertNotNull($profile->adaptive_thresholds);
    }

    #[Test]
    public function test_adaptive_thresholds_with_zero_std_dev(): void
    {
        $profile = $this->createEstablishedProfile([
            'transaction_amount_std_dev' => 0,
        ]);

        $thresholds = $this->service->computeAdaptiveThresholds($profile);

        // With zero stddev, thresholds should collapse to mean
        $this->assertEquals(1000, $thresholds['amount_upper']);
        $this->assertEquals(1000, $thresholds['amount_lower']);
    }

    #[Test]
    public function test_detect_drift_with_significant_shift(): void
    {
        $profile = $this->createEstablishedProfile();

        // Recent transactions with much higher amounts
        $recentTransactions = array_map(
            fn ($i) => ['amount' => 3000 + $i * 100],
            range(0, 9)
        );

        $result = $this->service->detectDrift($profile, $recentTransactions);

        $this->assertTrue($result['drifted']);
        $this->assertGreaterThan(0, $result['drift_score']);
        $this->assertArrayHasKey('baseline_mean', $result['details']);
        $this->assertArrayHasKey('recent_mean', $result['details']);

        // Verify stored
        $profile->refresh();
        $this->assertNotNull($profile->drift_metrics);
        $this->assertNotNull($profile->last_drift_check_at);
    }

    #[Test]
    public function test_detect_drift_with_no_shift(): void
    {
        $profile = $this->createEstablishedProfile();

        // 7-day window: avg_daily=5 -> expected 35 transactions
        // Amounts around baseline mean of 1000
        $recentTransactions = array_map(
            fn ($i) => ['amount' => 980 + ($i % 5) * 10],
            range(0, 34)
        );

        $result = $this->service->detectDrift($profile, $recentTransactions);

        $this->assertFalse($result['drifted']);
    }

    #[Test]
    public function test_detect_drift_with_empty_transactions(): void
    {
        $profile = $this->createEstablishedProfile();

        $result = $this->service->detectDrift($profile, []);

        $this->assertFalse($result['drifted']);
        $this->assertEquals(0.0, $result['drift_score']);
    }

    #[Test]
    public function test_classify_segment_new_account(): void
    {
        $profile = $this->createEstablishedProfile([
            'days_since_first_transaction' => 10,
            'is_established'               => false,
        ]);

        $segment = $this->service->classifySegment($profile);

        $this->assertEquals('new_account', $segment);
        $profile->refresh();
        $this->assertEquals('new_account', $profile->user_segment);
    }

    #[Test]
    public function test_classify_segment_high_value_trader(): void
    {
        $profile = $this->createEstablishedProfile([
            'avg_transaction_amount'        => 15000,
            'avg_monthly_transaction_count' => 50,
            'days_since_first_transaction'  => 365,
        ]);

        $segment = $this->service->classifySegment($profile);

        $this->assertEquals('high_value_trader', $segment);
    }

    #[Test]
    public function test_classify_segment_occasional_user(): void
    {
        $profile = $this->createEstablishedProfile([
            'avg_transaction_amount'        => 500,
            'avg_monthly_transaction_count' => 2,
            'days_since_first_transaction'  => 180,
        ]);

        $segment = $this->service->classifySegment($profile);

        $this->assertEquals('occasional_user', $segment);
    }

    #[Test]
    public function test_classify_segment_retail_consumer(): void
    {
        $profile = $this->createEstablishedProfile([
            'avg_transaction_amount'        => 500,
            'avg_monthly_transaction_count' => 15,
            'days_since_first_transaction'  => 180,
        ]);

        $segment = $this->service->classifySegment($profile);

        $this->assertEquals('retail_consumer', $segment);
    }

    #[Test]
    public function test_classify_segment_stores_segment_tags(): void
    {
        $profile = $this->createEstablishedProfile();

        $this->service->classifySegment($profile);

        $profile->refresh();
        $this->assertNotNull($profile->segment_tags);
        $this->assertContains($profile->user_segment, $profile->segment_tags);
    }
}
