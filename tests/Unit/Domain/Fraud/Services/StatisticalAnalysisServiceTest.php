<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fraud\Services;

use App\Domain\Fraud\Models\BehavioralProfile;
use App\Domain\Fraud\Services\StatisticalAnalysisService;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatisticalAnalysisServiceTest extends TestCase
{
    private StatisticalAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StatisticalAnalysisService();
    }

    #[Test]
    public function test_z_score_detects_high_amount_deviation(): void
    {
        $user = User::factory()->create();
        $profile = BehavioralProfile::create([
            'user_id'                     => $user->id,
            'avg_transaction_amount'      => 1000,
            'transaction_amount_std_dev'  => 200,
            'is_established'              => true,
            'total_transaction_count'     => 100,
            'avg_daily_transaction_count' => 5,
            'max_daily_volume'            => 10000,
            'typical_transaction_times'   => array_fill(0, 24, 4.17),
            'typical_transaction_days'    => array_fill(0, 7, 14.29),
            'common_locations'            => [],
            'location_history'            => [],
            'trusted_devices'             => [],
        ]);

        $context = ['amount' => 2500]; // 7.5 sigma away

        $result = $this->service->zScoreAnalysis($context, $profile);

        $this->assertTrue($result['detected']);
        $this->assertGreaterThan(0, $result['score']);
        $this->assertArrayHasKey('z_scores', $result['details']);
        $this->assertGreaterThan(3.0, abs($result['details']['z_scores']['amount']));
    }

    #[Test]
    public function test_z_score_passes_normal_amount(): void
    {
        $user = User::factory()->create();
        $profile = BehavioralProfile::create([
            'user_id'                     => $user->id,
            'avg_transaction_amount'      => 1000,
            'transaction_amount_std_dev'  => 200,
            'is_established'              => true,
            'total_transaction_count'     => 50,
            'avg_daily_transaction_count' => 5,
            'max_daily_volume'            => 10000,
            'typical_transaction_times'   => array_fill(0, 24, 4.17),
            'typical_transaction_days'    => array_fill(0, 7, 14.29),
            'common_locations'            => [],
            'location_history'            => [],
            'trusted_devices'             => [],
        ]);

        $context = ['amount' => 1100]; // 0.5 sigma

        $result = $this->service->zScoreAnalysis($context, $profile);

        $this->assertFalse($result['detected']);
    }

    #[Test]
    public function test_z_score_no_profile_returns_no_detection(): void
    {
        $result = $this->service->zScoreAnalysis(['amount' => 5000], null);

        $this->assertFalse($result['detected']);
        $this->assertEquals(0.0, $result['score']);
    }

    #[Test]
    public function test_iqr_detects_outlier(): void
    {
        $history = array_map(fn ($i) => ['amount' => 500 + $i * 10], range(0, 29));
        $context = [
            'amount'              => 5000,
            'transaction_history' => $history,
        ];

        $result = $this->service->iqrAnalysis($context, null);

        $this->assertTrue($result['detected']);
        $this->assertGreaterThan(0, $result['score']);
        $this->assertArrayHasKey('q1', $result['details']);
        $this->assertArrayHasKey('q3', $result['details']);
        $this->assertArrayHasKey('iqr', $result['details']);
    }

    #[Test]
    public function test_iqr_passes_normal_value(): void
    {
        $history = array_map(fn ($i) => ['amount' => 500 + $i * 10], range(0, 29));
        $context = [
            'amount'              => 650, // within Q1-Q3 range
            'transaction_history' => $history,
        ];

        $result = $this->service->iqrAnalysis($context, null);

        $this->assertFalse($result['detected']);
    }

    #[Test]
    public function test_iqr_returns_insufficient_with_few_samples(): void
    {
        $context = [
            'amount'              => 1000,
            'transaction_history' => [['amount' => 500]],
        ];

        $result = $this->service->iqrAnalysis($context, null);

        $this->assertFalse($result['detected']);
        $this->assertEquals('insufficient_history', $result['details']['reason']);
    }

    #[Test]
    public function test_isolation_forest_returns_score(): void
    {
        $context = [
            'amount'                      => 50000,
            'daily_transaction_count'     => 50,
            'daily_transaction_volume'    => 200000,
            'hourly_transaction_count'    => 15,
            'time_since_last_transaction' => 5,
            'hour_of_day'                 => 3,
        ];

        $result = $this->service->isolationForestAnalysis($context);

        $this->assertIsFloat($result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        $this->assertArrayHasKey('avg_path_length', $result['details']);
        $this->assertArrayHasKey('feature_count', $result['details']);
    }

    #[Test]
    public function test_isolation_forest_no_features_returns_zero(): void
    {
        $result = $this->service->isolationForestAnalysis([]);

        $this->assertFalse($result['detected']);
        $this->assertEquals(0.0, $result['score']);
    }

    #[Test]
    public function test_lof_detects_outlier(): void
    {
        // Normal amounts clustered around 500
        $history = array_map(fn ($i) => ['amount' => 480 + mt_rand(0, 40)], range(0, 29));
        $context = [
            'amount'              => 10000, // Far from cluster
            'transaction_history' => $history,
        ];

        $result = $this->service->localOutlierFactorAnalysis($context, null);

        $this->assertIsFloat($result['score']);
        $this->assertArrayHasKey('lof_score', $result['details']);
        $this->assertArrayHasKey('k_distance', $result['details']);
    }

    #[Test]
    public function test_lof_insufficient_neighbors(): void
    {
        $context = [
            'amount'              => 1000,
            'transaction_history' => [['amount' => 500]],
        ];

        $result = $this->service->localOutlierFactorAnalysis($context, null);

        $this->assertFalse($result['detected']);
        $this->assertEquals('insufficient_neighbors', $result['details']['reason']);
    }

    #[Test]
    public function test_seasonal_decomposition_detects_unusual_time(): void
    {
        $user = User::factory()->create();
        $timeDistribution = array_fill(0, 24, 0.0);
        // Concentrate activity in business hours (9-17)
        for ($h = 9; $h <= 17; $h++) {
            $timeDistribution[$h] = 11.11;
        }

        $profile = BehavioralProfile::create([
            'user_id'                   => $user->id,
            'is_established'            => true,
            'total_transaction_count'   => 100,
            'typical_transaction_times' => $timeDistribution,
            'typical_transaction_days'  => array_fill(0, 7, 14.29),
            'common_locations'          => [],
            'location_history'          => [],
            'trusted_devices'           => [],
        ]);

        $context = ['hour_of_day' => 3, 'day_of_week' => 1]; // 3am Monday

        $result = $this->service->seasonalDecomposition($context, $profile);

        $this->assertTrue($result['detected']);
        $this->assertGreaterThanOrEqual(50, $result['score']);
    }

    #[Test]
    public function test_analyze_returns_all_method_results(): void
    {
        $history = array_map(fn ($i) => ['amount' => 500 + $i * 10], range(0, 29));
        $context = [
            'amount'                  => 5000,
            'transaction_history'     => $history,
            'daily_transaction_count' => 5,
            'hour_of_day'             => 14,
        ];

        $results = $this->service->analyze($context, null);

        $this->assertArrayHasKey('z_score', $results);
        $this->assertArrayHasKey('iqr', $results);
        $this->assertArrayHasKey('isolation_forest', $results);
        $this->assertArrayHasKey('lof', $results);
    }
}
