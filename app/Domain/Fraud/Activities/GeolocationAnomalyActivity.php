<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Activities;

use App\Domain\Fraud\Services\DeviceFingerprintService;
use App\Domain\Fraud\Services\GeoMathService;
use Workflow\Activity;

class GeolocationAnomalyActivity extends Activity
{
    /**
     * Execute geolocation anomaly detection.
     *
     * @param  array{
     *     user_id?: string,
     *     lat?: float,
     *     lon?: float,
     *     ip?: string,
     *     last_lat?: float,
     *     last_lon?: float,
     *     time_diff_seconds?: int,
     *     location_history?: array<int, array{lat: float, lon: float}>,
     * }  $context
     * @return array{
     *     anomaly_type: string,
     *     detections: array,
     *     highest_score: float,
     *     highest_method: string|null,
     * }
     */
    public function execute(array $context = []): array
    {
        $geoMath = app(GeoMathService::class);
        $deviceService = app(DeviceFingerprintService::class);

        $detections = [];
        $highestScore = 0.0;
        $highestMethod = null;

        // 1. Impossible travel detection
        if (isset($context['lat'], $context['lon'], $context['last_lat'], $context['last_lon'], $context['time_diff_seconds'])) {
            $travelResult = $geoMath->isImpossibleTravel(
                $context['last_lat'],
                $context['last_lon'],
                $context['lat'],
                $context['lon'],
                $context['time_diff_seconds'],
            );

            $score = $travelResult['impossible'] ? 85.0 : 0.0;

            // Gradient scoring for near-impossible travel
            if (! $travelResult['impossible'] && $travelResult['required_speed_kmh'] !== INF) {
                $maxSpeed = $travelResult['max_speed_kmh'];
                $ratio = $travelResult['required_speed_kmh'] / $maxSpeed;
                if ($ratio > 0.7) {
                    $score = round(($ratio - 0.7) / 0.3 * 60, 2);
                }
            }

            $detections['impossible_travel'] = [
                'method'  => 'impossible_travel',
                'score'   => $score,
                'details' => $travelResult,
            ];

            if ($score > $highestScore) {
                $highestScore = $score;
                $highestMethod = 'impossible_travel';
            }
        }

        // 2. IP reputation check
        if (isset($context['ip'])) {
            $ipReputation = $deviceService->assessIpReputation($context['ip']);

            $detections['ip_reputation'] = [
                'method'  => 'ip_reputation',
                'score'   => $ipReputation['risk_score'],
                'details' => $ipReputation,
            ];

            if ($ipReputation['risk_score'] > $highestScore) {
                $highestScore = $ipReputation['risk_score'];
                $highestMethod = 'ip_reputation';
            }
        }

        // 3. Geo-clustering analysis
        if (isset($context['lat'], $context['lon'], $context['location_history']) && count($context['location_history']) >= 3) {
            $clusterResult = $geoMath->clusterLocations($context['location_history']);

            $clusterScore = 0.0;
            $clusterDetails = $clusterResult;

            if (! empty($clusterResult['clusters'])) {
                $distResult = $geoMath->distanceToNearestCluster(
                    $context['lat'],
                    $context['lon'],
                    $clusterResult['clusters'],
                );

                $clusterDetails['distance_check'] = $distResult;

                if ($distResult['outside_cluster']) {
                    $maxDist = (float) config('fraud.geolocation.geo_cluster.max_distance_from_cluster_km', 500.0);
                    $ratio = min($distResult['distance_km'] / $maxDist, 3.0);
                    $clusterScore = round(min($ratio * 40, 80.0), 2);
                }
            } elseif (! empty($clusterResult['noise']) && empty($clusterResult['clusters'])) {
                // All points are noise - no established pattern
                $clusterScore = 30.0;
            }

            $detections['geo_clustering'] = [
                'method'  => 'geo_clustering',
                'score'   => $clusterScore,
                'details' => $clusterDetails,
            ];

            if ($clusterScore > $highestScore) {
                $highestScore = $clusterScore;
                $highestMethod = 'geo_clustering';
            }
        }

        return [
            'anomaly_type'   => 'geolocation',
            'detections'     => $detections,
            'highest_score'  => round($highestScore, 2),
            'highest_method' => $highestMethod,
        ];
    }
}
