<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Services;

use App\Domain\Fraud\Enums\AnomalyStatus;
use App\Domain\Fraud\Enums\AnomalyType;
use App\Domain\Fraud\Enums\DetectionMethod;
use App\Domain\Fraud\Events\AnomalyDetected;
use App\Domain\Fraud\Models\AnomalyDetection;
use Exception;
use Illuminate\Support\Facades\Log;

class AnomalyDetectionOrchestrator
{
    private const SCORE_THRESHOLD = 40.0;

    public function __construct(
        private readonly StatisticalAnalysisService $statisticalService,
        private readonly BehavioralAnalysisService $behavioralService,
        private readonly RuleEngineService $ruleEngineService,
        private readonly DeviceFingerprintService $deviceService,
        private readonly GeoMathService $geoMathService,
    ) {
    }

    /**
     * Run all anomaly detection activities against the given context.
     *
     * @return array{anomalies: array, highest_score: float, has_critical: bool}
     */
    public function detectAnomalies(array $context, ?string $entityId = null, ?string $entityType = null, ?int $userId = null, ?string $fraudScoreId = null): array
    {
        if (! config('fraud.anomaly_detection.enabled', false)) {
            return [
                'anomalies'     => [],
                'highest_score' => 0.0,
                'has_critical'  => false,
            ];
        }

        $results = [];
        $highestScore = 0.0;
        $hasCritical = false;

        // 1. Statistical anomaly detection
        $statistical = $this->runStatisticalDetection($context);
        if ($statistical) {
            $results[] = $statistical;
            $highestScore = max($highestScore, $statistical['score']);
        }

        // 2. Behavioral anomaly detection
        $behavioral = $this->runBehavioralDetection($context);
        if ($behavioral) {
            $results[] = $behavioral;
            $highestScore = max($highestScore, $behavioral['score']);
        }

        // 3. Velocity anomaly detection
        $velocity = $this->runVelocityDetection($context);
        if ($velocity) {
            $results[] = $velocity;
            $highestScore = max($highestScore, $velocity['score']);
        }

        // 4. Geolocation anomaly detection
        $geolocation = $this->runGeolocationDetection($context);
        if ($geolocation) {
            $results[] = $geolocation;
            $highestScore = max($highestScore, $geolocation['score']);
        }

        // Persist detections that exceed threshold
        $persisted = [];
        foreach ($results as $result) {
            if ($result['score'] >= self::SCORE_THRESHOLD) {
                $detection = $this->persistDetection(
                    $result,
                    $context,
                    $entityId,
                    $entityType,
                    $userId,
                    $fraudScoreId,
                );

                if ($detection) {
                    $persisted[] = $detection;

                    if ($detection->severity === 'critical') {
                        $hasCritical = true;
                    }

                    AnomalyDetected::dispatch($detection);
                }
            }
        }

        return [
            'anomalies'     => $results,
            'highest_score' => round($highestScore, 2),
            'has_critical'  => $hasCritical,
            'persisted'     => count($persisted),
        ];
    }

    /**
     * Run statistical anomaly detection.
     */
    protected function runStatisticalDetection(array $context): ?array
    {
        try {
            $result = $this->statisticalService->analyze($context);
            $highestScore = 0.0;
            $highestMethod = null;

            foreach ($result as $method => $detection) {
                $score = $detection['score'] ?? $detection['anomaly_score'] ?? 0;
                if ($score > $highestScore) {
                    $highestScore = (float) $score;
                    $highestMethod = $method;
                }
            }

            if ($highestScore <= 0) {
                return null;
            }

            return [
                'anomaly_type'     => AnomalyType::Statistical,
                'detection_method' => $this->resolveStatisticalMethod($highestMethod),
                'score'            => round($highestScore, 2),
                'confidence'       => $this->calculateConfidence($highestScore, $result),
                'details'          => $result,
            ];
        } catch (Exception $e) {
            Log::warning('Statistical anomaly detection failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Run behavioral anomaly detection.
     */
    protected function runBehavioralDetection(array $context): ?array
    {
        try {
            $profile = $context['behavioral_profile'] ?? null;
            if (! $profile) {
                return null;
            }

            $adaptiveResult = $this->behavioralService->computeAdaptiveThresholds($profile, $context);
            $driftResult = $this->behavioralService->detectDrift($profile, $context);

            $adaptiveScore = 0.0;
            if (! empty($adaptiveResult['breaches'])) {
                $adaptiveScore = min(count($adaptiveResult['breaches']) * 25.0, 80.0);
            }

            $driftScore = ($driftResult['drift_score'] ?? 0) * 100;
            $highestScore = max($adaptiveScore, $driftScore);

            if ($highestScore <= 0) {
                return null;
            }

            $method = $adaptiveScore >= $driftScore
                ? DetectionMethod::AdaptiveThreshold
                : DetectionMethod::DriftDetection;

            return [
                'anomaly_type'     => AnomalyType::Behavioral,
                'detection_method' => $method,
                'score'            => round($highestScore, 2),
                'confidence'       => $this->calculateConfidence($highestScore, ['adaptive' => $adaptiveResult, 'drift' => $driftResult]),
                'details'          => [
                    'adaptive_thresholds' => $adaptiveResult,
                    'drift_detection'     => $driftResult,
                ],
            ];
        } catch (Exception $e) {
            Log::warning('Behavioral anomaly detection failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Run velocity anomaly detection.
     */
    protected function runVelocityDetection(array $context): ?array
    {
        try {
            $slidingWindows = $this->ruleEngineService->evaluateSlidingWindows($context);
            $burstResult = $this->ruleEngineService->detectBurst($context);

            $windowScore = 0.0;
            foreach ($slidingWindows['breaches'] ?? [] as $breach) {
                $windowScore = max($windowScore, ($breach['ratio'] ?? 1.0) * 40);
            }

            $burstScore = ($burstResult['is_burst'] ?? false) ? min(($burstResult['burst_ratio'] ?? 1.0) * 30, 80.0) : 0.0;
            $highestScore = max($windowScore, $burstScore);

            if ($highestScore <= 0) {
                return null;
            }

            $method = $windowScore >= $burstScore
                ? DetectionMethod::SlidingWindow
                : DetectionMethod::BurstDetection;

            return [
                'anomaly_type'     => AnomalyType::Velocity,
                'detection_method' => $method,
                'score'            => round(min($highestScore, 100.0), 2),
                'confidence'       => $this->calculateConfidence($highestScore, ['windows' => $slidingWindows, 'burst' => $burstResult]),
                'details'          => [
                    'sliding_windows' => $slidingWindows,
                    'burst_detection' => $burstResult,
                ],
            ];
        } catch (Exception $e) {
            Log::warning('Velocity anomaly detection failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Run geolocation anomaly detection.
     */
    protected function runGeolocationDetection(array $context): ?array
    {
        try {
            $highestScore = 0.0;
            $highestMethod = null;
            $details = [];

            // Impossible travel
            if (isset($context['lat'], $context['lon'], $context['last_lat'], $context['last_lon'], $context['time_diff_seconds'])) {
                $travelResult = $this->geoMathService->isImpossibleTravel(
                    $context['last_lat'],
                    $context['last_lon'],
                    $context['lat'],
                    $context['lon'],
                    $context['time_diff_seconds'],
                );

                $score = $travelResult['impossible'] ? 85.0 : 0.0;
                $details['impossible_travel'] = $travelResult;

                if ($score > $highestScore) {
                    $highestScore = $score;
                    $highestMethod = DetectionMethod::ImpossibleTravel;
                }
            }

            // IP reputation
            if (isset($context['ip'])) {
                $ipResult = $this->deviceService->assessIpReputation($context['ip']);
                $details['ip_reputation'] = $ipResult;

                if ($ipResult['risk_score'] > $highestScore) {
                    $highestScore = $ipResult['risk_score'];
                    $highestMethod = DetectionMethod::IpReputation;
                }
            }

            // Geo-clustering
            if (isset($context['lat'], $context['lon'], $context['location_history']) && count($context['location_history']) >= 3) {
                $clusterResult = $this->geoMathService->clusterLocations($context['location_history']);

                if (! empty($clusterResult['clusters'])) {
                    $distResult = $this->geoMathService->distanceToNearestCluster(
                        $context['lat'],
                        $context['lon'],
                        $clusterResult['clusters'],
                    );

                    $details['geo_clustering'] = array_merge($clusterResult, ['distance_check' => $distResult]);

                    if ($distResult['outside_cluster']) {
                        $clusterScore = round(min(($distResult['distance_km'] / 500.0) * 40, 80.0), 2);
                        if ($clusterScore > $highestScore) {
                            $highestScore = $clusterScore;
                            $highestMethod = DetectionMethod::GeoClustering;
                        }
                    }
                }
            }

            if ($highestScore <= 0 || ! $highestMethod) {
                return null;
            }

            return [
                'anomaly_type'     => AnomalyType::Geolocation,
                'detection_method' => $highestMethod,
                'score'            => round($highestScore, 2),
                'confidence'       => $this->calculateConfidence($highestScore, $details),
                'details'          => $details,
            ];
        } catch (Exception $e) {
            Log::warning('Geolocation anomaly detection failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Persist an anomaly detection record.
     */
    protected function persistDetection(
        array $result,
        array $context,
        ?string $entityId,
        ?string $entityType,
        ?int $userId,
        ?string $fraudScoreId,
    ): ?AnomalyDetection {
        try {
            $score = $result['score'];

            return AnomalyDetection::create([
                'entity_id'        => $entityId,
                'entity_type'      => $entityType,
                'user_id'          => $userId,
                'anomaly_type'     => $result['anomaly_type'],
                'detection_method' => $result['detection_method'],
                'status'           => AnomalyStatus::Detected,
                'anomaly_score'    => $score,
                'confidence'       => $result['confidence'],
                'severity'         => AnomalyDetection::calculateSeverity($score),
                'features'         => $result['details'] ?? [],
                'explanation'      => $this->generateExplanation($result),
                'context_snapshot' => array_intersect_key($context, array_flip([
                    'amount', 'type', 'ip', 'ip_country', 'lat', 'lon',
                    'daily_transaction_count', 'daily_transaction_volume',
                ])),
                'is_real_time'   => true,
                'fraud_score_id' => $fraudScoreId,
                'model_version'  => config('fraud.anomaly_detection.model_version', '1.0.0'),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to persist anomaly detection', [
                'error'  => $e->getMessage(),
                'result' => $result,
            ]);

            return null;
        }
    }

    /**
     * Calculate confidence based on score strength and supporting evidence.
     */
    protected function calculateConfidence(float $score, array $details): float
    {
        // Base confidence from score clarity
        $confidence = match (true) {
            $score >= 80 => 0.95,
            $score >= 60 => 0.85,
            $score >= 40 => 0.70,
            default      => 0.50,
        };

        // Boost confidence if multiple signals agree
        $signalCount = count(array_filter($details, fn ($d) => is_array($d) && ! empty($d)));
        if ($signalCount >= 3) {
            $confidence = min($confidence + 0.05, 1.0);
        }

        return round($confidence, 4);
    }

    /**
     * Generate a human-readable explanation for the detection.
     */
    protected function generateExplanation(array $result): array
    {
        $type = $result['anomaly_type']->label();
        $method = $result['detection_method']->value;
        $score = $result['score'];

        return [
            'summary' => "{$type} anomaly detected via {$method} with score {$score}",
            'type'    => $type,
            'method'  => $method,
            'score'   => $score,
        ];
    }

    /**
     * Resolve statistical method enum from string key.
     */
    protected function resolveStatisticalMethod(?string $method): DetectionMethod
    {
        return match ($method) {
            'z_score'          => DetectionMethod::ZScore,
            'iqr'              => DetectionMethod::IQR,
            'isolation_forest' => DetectionMethod::IsolationForest,
            'lof'              => DetectionMethod::LOF,
            default            => DetectionMethod::ZScore,
        };
    }
}
