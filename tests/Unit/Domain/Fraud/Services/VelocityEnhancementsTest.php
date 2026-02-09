<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fraud\Services;

use App\Domain\Fraud\Services\RuleEngineService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VelocityEnhancementsTest extends TestCase
{
    private RuleEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new RuleEngineService();
    }

    #[Test]
    public function test_evaluate_sliding_windows_returns_all_configured_windows(): void
    {
        $context = [
            'user'                     => ['id' => null],
            'daily_transaction_volume' => 5000,
        ];

        $results = $this->service->evaluateSlidingWindows($context);

        $this->assertArrayHasKey('5m', $results);
        $this->assertArrayHasKey('15m', $results);
        $this->assertArrayHasKey('1h', $results);
        $this->assertArrayHasKey('6h', $results);
        $this->assertArrayHasKey('24h', $results);
        $this->assertArrayHasKey('7d', $results);
    }

    #[Test]
    public function test_sliding_window_result_structure(): void
    {
        $context = [
            'user'                     => ['id' => null],
            'daily_transaction_volume' => 100,
        ];

        $results = $this->service->evaluateSlidingWindows($context);

        foreach ($results as $label => $result) {
            $this->assertArrayHasKey('exceeded', $result, "Missing 'exceeded' for window {$label}");
            $this->assertArrayHasKey('count', $result, "Missing 'count' for window {$label}");
            $this->assertArrayHasKey('volume', $result, "Missing 'volume' for window {$label}");
            $this->assertArrayHasKey('max_count', $result, "Missing 'max_count' for window {$label}");
            $this->assertArrayHasKey('max_volume', $result, "Missing 'max_volume' for window {$label}");
        }
    }

    #[Test]
    public function test_sliding_windows_not_exceeded_for_low_volume(): void
    {
        $context = [
            'user'                     => ['id' => null],
            'daily_transaction_volume' => 100,
        ];

        $results = $this->service->evaluateSlidingWindows($context);

        foreach ($results as $result) {
            $this->assertFalse($result['exceeded']);
        }
    }

    #[Test]
    public function test_detect_burst_with_high_rate(): void
    {
        $context = [
            'hourly_transaction_count'    => 20,
            'avg_daily_transaction_count' => 10,
        ];

        $result = $this->service->detectBurst($context);

        $this->assertTrue($result['burst_detected']);
        // 20 / (10/24) = 48, which is >> 3.0 threshold
        $this->assertGreaterThan(3.0, $result['burst_ratio']);
    }

    #[Test]
    public function test_detect_burst_with_normal_rate(): void
    {
        $context = [
            'hourly_transaction_count'    => 1,
            'avg_daily_transaction_count' => 24,
        ];

        $result = $this->service->detectBurst($context);

        $this->assertFalse($result['burst_detected']);
        // 1 / (24/24) = 1.0
        $this->assertLessThanOrEqual(3.0, $result['burst_ratio']);
    }

    #[Test]
    public function test_detect_burst_with_no_baseline(): void
    {
        $context = [
            'hourly_transaction_count'    => 5,
            'avg_daily_transaction_count' => 0,
        ];

        $result = $this->service->detectBurst($context);

        $this->assertFalse($result['burst_detected']);
        $this->assertEquals('no_baseline', $result['details']['reason']);
    }

    #[Test]
    public function test_cross_account_disabled_returns_not_detected(): void
    {
        config(['fraud.velocity.cross_account.enabled' => false]);

        $result = $this->service->detectCrossAccountActivity([]);

        $this->assertFalse($result['detected']);
        $this->assertEquals('disabled', $result['details']['reason']);
    }

    #[Test]
    public function test_cross_account_with_no_shared_devices(): void
    {
        config(['fraud.velocity.cross_account.enabled' => true]);

        $context = [
            'user'        => ['id' => 1],
            'device_data' => ['fingerprint' => 'unique_device_123', 'ip' => '192.168.1.1'],
        ];

        $result = $this->service->detectCrossAccountActivity($context);

        // No shared devices in DB means 0 count
        $this->assertFalse($result['detected']);
        $this->assertArrayHasKey('shared_device_users', $result['details']);
        $this->assertArrayHasKey('shared_ip_users', $result['details']);
    }

    #[Test]
    public function test_burst_detection_ratio_calculation(): void
    {
        $context = [
            'hourly_transaction_count'    => 6,
            'avg_daily_transaction_count' => 48, // baseline hourly = 2
        ];

        $result = $this->service->detectBurst($context);

        // ratio = 6 / 2 = 3.0, not > 3.0
        $this->assertFalse($result['burst_detected']);
        $this->assertEquals(3.0, $result['burst_ratio']);
    }

    #[Test]
    public function test_cross_account_result_includes_thresholds(): void
    {
        config(['fraud.velocity.cross_account.enabled' => true]);

        $context = [
            'user'        => ['id' => 1],
            'device_data' => ['fingerprint' => 'test', 'ip' => '10.0.0.1'],
        ];

        $result = $this->service->detectCrossAccountActivity($context);

        $this->assertArrayHasKey('device_threshold', $result['details']);
        $this->assertArrayHasKey('ip_threshold', $result['details']);
    }
}
