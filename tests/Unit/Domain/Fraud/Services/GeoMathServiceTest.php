<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fraud\Services;

use App\Domain\Fraud\Services\GeoMathService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeoMathServiceTest extends TestCase
{
    private GeoMathService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeoMathService();
    }

    #[Test]
    public function haversine_distance_returns_zero_for_same_point(): void
    {
        $distance = $this->service->haversineDistance(40.7128, -74.0060, 40.7128, -74.0060);

        $this->assertEqualsWithDelta(0.0, $distance, 0.01);
    }

    #[Test]
    public function haversine_distance_calculates_known_distance(): void
    {
        // New York to London ≈ 5570 km
        $distance = $this->service->haversineDistance(40.7128, -74.0060, 51.5074, -0.1278);

        $this->assertEqualsWithDelta(5570, $distance, 50);
    }

    #[Test]
    public function haversine_distance_is_symmetric(): void
    {
        $d1 = $this->service->haversineDistance(40.7128, -74.0060, 51.5074, -0.1278);
        $d2 = $this->service->haversineDistance(51.5074, -0.1278, 40.7128, -74.0060);

        $this->assertEqualsWithDelta($d1, $d2, 0.01);
    }

    #[Test]
    public function impossible_travel_detects_physically_impossible_speed(): void
    {
        // New York to London in 1 hour = ~5570 km/h (impossible, max 900 km/h)
        $result = $this->service->isImpossibleTravel(40.7128, -74.0060, 51.5074, -0.1278, 3600);

        $this->assertTrue($result['impossible']);
        $this->assertGreaterThan(900, $result['required_speed_kmh']);
    }

    #[Test]
    public function impossible_travel_allows_plausible_travel(): void
    {
        // New York to London in 8 hours = ~696 km/h (plausible flight)
        $result = $this->service->isImpossibleTravel(40.7128, -74.0060, 51.5074, -0.1278, 28800);

        $this->assertFalse($result['impossible']);
        $this->assertLessThan(900, $result['required_speed_kmh']);
    }

    #[Test]
    public function impossible_travel_with_zero_time_and_distance(): void
    {
        // Same location, zero time - should NOT be impossible
        $result = $this->service->isImpossibleTravel(40.7128, -74.0060, 40.7128, -74.0060, 0);

        $this->assertFalse($result['impossible']);
    }

    #[Test]
    public function impossible_travel_with_zero_time_different_location(): void
    {
        // Different location, zero time - should be impossible
        $result = $this->service->isImpossibleTravel(40.7128, -74.0060, 51.5074, -0.1278, 0);

        $this->assertTrue($result['impossible']);
        $this->assertEquals(INF, $result['required_speed_kmh']);
    }

    #[Test]
    public function cluster_locations_groups_nearby_points(): void
    {
        $points = [
            // Cluster 1: NYC area
            ['lat' => 40.7128, 'lon' => -74.0060],
            ['lat' => 40.7580, 'lon' => -73.9855],
            ['lat' => 40.6892, 'lon' => -74.0445],
            ['lat' => 40.7282, 'lon' => -73.7949],
            // Cluster 2: London area
            ['lat' => 51.5074, 'lon' => -0.1278],
            ['lat' => 51.5155, 'lon' => -0.1419],
            ['lat' => 51.4816, 'lon' => -0.0090],
            ['lat' => 51.5033, 'lon' => -0.1195],
        ];

        $result = $this->service->clusterLocations($points);

        $this->assertArrayHasKey('clusters', $result);
        $this->assertArrayHasKey('noise', $result);
        $this->assertEquals(2, $result['cluster_count']);
    }

    #[Test]
    public function cluster_locations_identifies_noise(): void
    {
        $points = [
            ['lat' => 40.7128, 'lon' => -74.0060],
            ['lat' => 0.0, 'lon' => 0.0],       // Isolated point
            ['lat' => -33.8688, 'lon' => 151.2093], // Isolated point
        ];

        $result = $this->service->clusterLocations($points);

        // With default minPoints=3 and eps=50km, all points are noise
        $this->assertCount(0, $result['clusters']);
        $this->assertCount(3, $result['noise']);
    }

    #[Test]
    public function distance_to_nearest_cluster_finds_closest(): void
    {
        $clusters = [
            [
                ['lat' => 40.7128, 'lon' => -74.0060],
                ['lat' => 40.7580, 'lon' => -73.9855],
            ],
            [
                ['lat' => 51.5074, 'lon' => -0.1278],
                ['lat' => 51.5155, 'lon' => -0.1419],
            ],
        ];

        // Point near NYC should be closest to cluster 0
        $result = $this->service->distanceToNearestCluster(40.6892, -74.0445, $clusters);

        $this->assertEquals(0, $result['nearest_cluster_id']);
        $this->assertLessThan(10, $result['distance_km']);
    }

    #[Test]
    public function distance_to_nearest_cluster_flags_outside(): void
    {
        $clusters = [
            [
                ['lat' => 40.7128, 'lon' => -74.0060],
                ['lat' => 40.7580, 'lon' => -73.9855],
            ],
        ];

        // Tokyo is far from NYC
        $result = $this->service->distanceToNearestCluster(35.6762, 139.6503, $clusters);

        $this->assertTrue($result['outside_cluster']);
        $this->assertGreaterThan(500, $result['distance_km']);
    }

    #[Test]
    public function cluster_center_calculates_centroid(): void
    {
        $points = [
            ['lat' => 40.0, 'lon' => -74.0],
            ['lat' => 42.0, 'lon' => -72.0],
        ];

        $center = $this->service->clusterCenter($points);

        $this->assertEqualsWithDelta(41.0, $center['lat'], 0.01);
        $this->assertEqualsWithDelta(-73.0, $center['lon'], 0.01);
    }
}
