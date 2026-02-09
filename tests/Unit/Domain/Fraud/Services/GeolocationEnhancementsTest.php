<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fraud\Services;

use App\Domain\Fraud\Activities\GeolocationAnomalyActivity;
use App\Domain\Fraud\Services\DeviceFingerprintService;
use App\Domain\Fraud\Services\GeoMathService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class GeolocationEnhancementsTest extends TestCase
{
    #[Test]
    public function activity_detects_impossible_travel(): void
    {
        $this->app->bind(GeoMathService::class, function () {
            return new GeoMathService();
        });

        $mockDevice = $this->createPartialMock(DeviceFingerprintService::class, ['assessIpReputation']);
        $mockDevice->method('assessIpReputation')->willReturn([
            'risk_score' => 0.0,
            'flags'      => [],
            'details'    => [],
        ]);
        $this->app->instance(DeviceFingerprintService::class, $mockDevice);

        $activity = $this->createPartialMock(GeolocationAnomalyActivity::class, []);
        $ref = new ReflectionMethod($activity, 'execute');
        $result = $ref->invoke($activity, [
            'lat'               => 51.5074,
            'lon'               => -0.1278,
            'last_lat'          => 40.7128,
            'last_lon'          => -74.0060,
            'time_diff_seconds' => 3600, // 1 hour NYC->London = impossible
            'ip'                => '1.2.3.4',
        ]);

        $this->assertEquals('geolocation', $result['anomaly_type']);
        $this->assertGreaterThan(50, $result['highest_score']);
        $this->assertEquals('impossible_travel', $result['highest_method']);
    }

    #[Test]
    public function activity_detects_ip_reputation_risk(): void
    {
        $this->app->bind(GeoMathService::class, function () {
            return new GeoMathService();
        });

        $mockDevice = $this->createPartialMock(DeviceFingerprintService::class, ['assessIpReputation']);
        $mockDevice->method('assessIpReputation')->willReturn([
            'risk_score' => 75.0,
            'flags'      => ['tor_detected', 'proxy_detected'],
            'details'    => ['is_tor' => true, 'is_proxy' => true],
        ]);
        $this->app->instance(DeviceFingerprintService::class, $mockDevice);

        $activity = $this->createPartialMock(GeolocationAnomalyActivity::class, []);
        $ref = new ReflectionMethod($activity, 'execute');
        $result = $ref->invoke($activity, [
            'ip' => '10.0.0.1',
        ]);

        $this->assertEquals('geolocation', $result['anomaly_type']);
        $this->assertArrayHasKey('ip_reputation', $result['detections']);
        $this->assertEquals(75.0, $result['detections']['ip_reputation']['score']);
    }

    #[Test]
    public function activity_detects_geo_clustering_anomaly(): void
    {
        $this->app->bind(GeoMathService::class, function () {
            return new GeoMathService();
        });

        $mockDevice = $this->createPartialMock(DeviceFingerprintService::class, ['assessIpReputation']);
        $mockDevice->method('assessIpReputation')->willReturn([
            'risk_score' => 0.0,
            'flags'      => [],
            'details'    => [],
        ]);
        $this->app->instance(DeviceFingerprintService::class, $mockDevice);

        // Location history clustered around NYC, current location in Tokyo
        $history = [
            ['lat' => 40.7128, 'lon' => -74.0060],
            ['lat' => 40.7580, 'lon' => -73.9855],
            ['lat' => 40.6892, 'lon' => -74.0445],
            ['lat' => 40.7282, 'lon' => -73.7949],
        ];

        $activity = $this->createPartialMock(GeolocationAnomalyActivity::class, []);
        $ref = new ReflectionMethod($activity, 'execute');
        $result = $ref->invoke($activity, [
            'lat'              => 35.6762,
            'lon'              => 139.6503,
            'ip'               => '1.2.3.4',
            'location_history' => $history,
        ]);

        $this->assertArrayHasKey('geo_clustering', $result['detections']);
        $this->assertGreaterThan(0, $result['detections']['geo_clustering']['score']);
    }

    #[Test]
    public function activity_returns_empty_detections_without_context(): void
    {
        $this->app->bind(GeoMathService::class, function () {
            return new GeoMathService();
        });

        $mockDevice = $this->createPartialMock(DeviceFingerprintService::class, ['assessIpReputation']);
        $this->app->instance(DeviceFingerprintService::class, $mockDevice);

        $activity = $this->createPartialMock(GeolocationAnomalyActivity::class, []);
        $ref = new ReflectionMethod($activity, 'execute');
        $result = $ref->invoke($activity, []);

        $this->assertEquals('geolocation', $result['anomaly_type']);
        $this->assertEmpty($result['detections']);
        $this->assertEquals(0.0, $result['highest_score']);
        $this->assertNull($result['highest_method']);
    }

    #[Test]
    public function ip_reputation_flags_vpn(): void
    {
        Cache::flush();
        $service = $this->createPartialMock(DeviceFingerprintService::class, ['getIpData', 'countBlockedTransactionsForIp']);
        $service->method('getIpData')->willReturn([
            'country'    => 'US',
            'is_vpn'     => true,
            'is_proxy'   => false,
            'is_tor'     => false,
            'risk_score' => 20,
        ]);
        $service->method('countBlockedTransactionsForIp')->willReturn(0);

        $result = $service->assessIpReputation('1.2.3.4');

        $this->assertContains('vpn_detected', $result['flags']);
        $this->assertGreaterThan(0, $result['risk_score']);
    }

    #[Test]
    public function ip_reputation_flags_tor_with_high_score(): void
    {
        Cache::flush();
        $service = $this->createPartialMock(DeviceFingerprintService::class, ['getIpData', 'countBlockedTransactionsForIp']);
        $service->method('getIpData')->willReturn([
            'country'    => 'RU',
            'is_vpn'     => false,
            'is_proxy'   => true,
            'is_tor'     => true,
            'risk_score' => 80,
        ]);
        $service->method('countBlockedTransactionsForIp')->willReturn(5);

        $result = $service->assessIpReputation('10.0.0.1');

        $this->assertContains('tor_detected', $result['flags']);
        $this->assertContains('proxy_detected', $result['flags']);
        $this->assertContains('high_provider_risk', $result['flags']);
        $this->assertContains('associated_with_blocked_transactions', $result['flags']);
        // Score capped at 100
        $this->assertLessThanOrEqual(100, $result['risk_score']);
    }

    #[Test]
    public function ip_reputation_returns_zero_when_ip_data_unavailable(): void
    {
        Cache::flush();
        $service = $this->createPartialMock(DeviceFingerprintService::class, ['getIpData']);
        $service->method('getIpData')->willReturn(null);

        $result = $service->assessIpReputation('0.0.0.0');

        $this->assertEquals(0.0, $result['risk_score']);
        $this->assertEmpty($result['flags']);
    }
}
