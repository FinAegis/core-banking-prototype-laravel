<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Services;

class GeoMathService
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Calculate distance between two points using Haversine formula.
     */
    public function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Check for impossible travel between two locations given a time interval.
     */
    public function isImpossibleTravel(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        int $timeDiffSeconds
    ): array {
        $maxSpeed = (float) config('fraud.geolocation.impossible_travel_max_speed_kmh', 900.0);
        $distance = $this->haversineDistance($lat1, $lon1, $lat2, $lon2);

        if ($timeDiffSeconds <= 0) {
            return [
                'impossible'         => $distance > 1.0,
                'distance_km'        => round($distance, 2),
                'required_speed_kmh' => INF,
                'max_speed_kmh'      => $maxSpeed,
            ];
        }

        $timeDiffHours = $timeDiffSeconds / 3600.0;
        $requiredSpeed = $distance / $timeDiffHours;
        $impossible = $requiredSpeed > $maxSpeed;

        return [
            'impossible'         => $impossible,
            'distance_km'        => round($distance, 2),
            'required_speed_kmh' => round($requiredSpeed, 2),
            'max_speed_kmh'      => $maxSpeed,
            'time_diff_minutes'  => round($timeDiffSeconds / 60, 1),
        ];
    }

    /**
     * DBSCAN-style clustering of location points.
     *
     * @param  array<int, array{lat: float, lon: float}>  $points
     * @return array{clusters: array, noise: array}
     */
    public function clusterLocations(array $points): array
    {
        $epsKm = (float) config('fraud.geolocation.geo_cluster.eps_km', 50.0);
        $minPoints = (int) config('fraud.geolocation.geo_cluster.min_points', 3);

        $labels = array_fill(0, count($points), -1); // -1 = unvisited
        $clusterId = 0;

        for ($i = 0; $i < count($points); $i++) {
            if ($labels[$i] !== -1) {
                continue;
            }

            $neighbors = $this->regionQuery($points, $i, $epsKm);

            if (count($neighbors) < $minPoints) {
                $labels[$i] = 0; // noise
                continue;
            }

            $clusterId++;
            $labels[$i] = $clusterId;

            $seeds = $neighbors;
            $seedIndex = 0;

            while ($seedIndex < count($seeds)) {
                $q = $seeds[$seedIndex];
                $seedIndex++;

                if ($labels[$q] === 0) {
                    $labels[$q] = $clusterId;
                }

                if ($labels[$q] !== -1) {
                    continue;
                }

                $labels[$q] = $clusterId;
                $qNeighbors = $this->regionQuery($points, $q, $epsKm);

                if (count($qNeighbors) >= $minPoints) {
                    $seeds = array_unique(array_merge($seeds, $qNeighbors));
                }
            }
        }

        // Group results
        $clusters = [];
        $noise = [];

        for ($i = 0; $i < count($points); $i++) {
            if ($labels[$i] === 0) {
                $noise[] = $points[$i];
            } else {
                $clusters[$labels[$i]][] = $points[$i];
            }
        }

        return [
            'clusters'      => array_values($clusters),
            'noise'         => $noise,
            'cluster_count' => $clusterId,
        ];
    }

    /**
     * Find the nearest cluster center and distance to it.
     */
    public function distanceToNearestCluster(float $lat, float $lon, array $clusters): array
    {
        $minDistance = INF;
        $nearestClusterId = -1;

        foreach ($clusters as $id => $cluster) {
            $center = $this->clusterCenter($cluster);
            $distance = $this->haversineDistance($lat, $lon, $center['lat'], $center['lon']);

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestClusterId = $id;
            }
        }

        $maxDistFromCluster = (float) config('fraud.geolocation.geo_cluster.max_distance_from_cluster_km', 500.0);

        return [
            'distance_km'        => round($minDistance, 2),
            'nearest_cluster_id' => $nearestClusterId,
            'outside_cluster'    => $minDistance > $maxDistFromCluster,
        ];
    }

    /**
     * Calculate cluster centroid.
     */
    public function clusterCenter(array $points): array
    {
        $count = count($points);
        if ($count === 0) {
            return ['lat' => 0, 'lon' => 0];
        }

        $sumLat = array_sum(array_column($points, 'lat'));
        $sumLon = array_sum(array_column($points, 'lon'));

        return [
            'lat' => $sumLat / $count,
            'lon' => $sumLon / $count,
        ];
    }

    /**
     * Find all points within eps distance of point[index].
     */
    private function regionQuery(array $points, int $index, float $epsKm): array
    {
        $neighbors = [];
        $point = $points[$index];

        for ($i = 0; $i < count($points); $i++) {
            if ($i === $index) {
                continue;
            }

            $dist = $this->haversineDistance($point['lat'], $point['lon'], $points[$i]['lat'], $points[$i]['lon']);
            if ($dist <= $epsKm) {
                $neighbors[] = $i;
            }
        }

        return $neighbors;
    }
}
