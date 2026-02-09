<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Services;

use App\Domain\Fraud\Enums\DetectionMethod;
use App\Domain\Fraud\Models\BehavioralProfile;

class StatisticalAnalysisService
{
    /**
     * Run all statistical anomaly checks on a transaction context.
     *
     * @return array<string, array{detected: bool, score: float, confidence: float, details: array}>
     */
    public function analyze(array $context, ?BehavioralProfile $profile): array
    {
        $results = [];

        $results[DetectionMethod::ZScore->value] = $this->zScoreAnalysis($context, $profile);
        $results[DetectionMethod::IQR->value] = $this->iqrAnalysis($context, $profile);
        $results[DetectionMethod::IsolationForest->value] = $this->isolationForestAnalysis($context);
        $results[DetectionMethod::LOF->value] = $this->localOutlierFactorAnalysis($context, $profile);

        // Seasonal decomposition piggybacks on z-score
        $seasonal = $this->seasonalDecomposition($context, $profile);
        if ($seasonal['detected']) {
            $results['seasonal'] = $seasonal;
        }

        return $results;
    }

    /**
     * Multi-dimensional Z-score analysis.
     */
    public function zScoreAnalysis(array $context, ?BehavioralProfile $profile): array
    {
        $threshold = (float) config('fraud.statistical.z_score_threshold', 3.0);
        $amount = (float) ($context['amount'] ?? 0);
        $zScores = [];
        $detected = false;

        if ($profile && $profile->is_established) {
            $mean = (float) ($profile->avg_transaction_amount ?? 0);
            $stdDev = (float) ($profile->transaction_amount_std_dev ?? 0);

            // Amount Z-score
            if ($stdDev > 0) {
                $zScores['amount'] = ($amount - $mean) / $stdDev;
            }

            // Velocity Z-score (daily count vs average)
            $dailyCount = (int) ($context['daily_transaction_count'] ?? 0);
            $avgDailyCount = (int) ($profile->avg_daily_transaction_count ?? 0);
            if ($avgDailyCount > 0) {
                // Use Poisson approximation: stddev ~= sqrt(mean)
                $velocityStdDev = sqrt($avgDailyCount);
                $zScores['velocity'] = ($dailyCount - $avgDailyCount) / max($velocityStdDev, 0.01);
            }

            // Volume Z-score (daily volume vs max)
            $dailyVolume = (float) ($context['daily_transaction_volume'] ?? 0);
            $maxDaily = (float) ($profile->max_daily_volume ?? 0);
            if ($maxDaily > 0) {
                $zScores['volume'] = ($dailyVolume - $maxDaily * 0.5) / max($maxDaily * 0.25, 0.01);
            }

            $detected = collect($zScores)->contains(fn (float $z) => abs($z) > $threshold);
        }

        $maxZ = empty($zScores) ? 0.0 : max(array_map('abs', $zScores));
        $score = min(100.0, ($maxZ / $threshold) * 50.0);
        $confidence = $profile && $profile->is_established ? min(0.95, 0.5 + ($profile->total_transaction_count / 200)) : 0.3;

        return [
            'detected'   => $detected,
            'score'      => round($score, 2),
            'confidence' => round($confidence, 4),
            'details'    => [
                'z_scores'    => $zScores,
                'threshold'   => $threshold,
                'max_z_score' => round($maxZ, 4),
            ],
        ];
    }

    /**
     * IQR-based outlier detection on recent transaction amounts.
     */
    public function iqrAnalysis(array $context, ?BehavioralProfile $profile): array
    {
        $multiplier = (float) config('fraud.statistical.iqr_multiplier', 1.5);
        $amount = (float) ($context['amount'] ?? 0);
        $history = collect($context['transaction_history'] ?? [])
            ->pluck('amount')
            ->map(fn ($v) => (float) $v)
            ->sort()
            ->values();

        $minSamples = (int) config('fraud.statistical.min_samples', 10);

        if ($history->count() < $minSamples) {
            return [
                'detected'   => false,
                'score'      => 0.0,
                'confidence' => 0.1,
                'details'    => ['reason' => 'insufficient_history', 'count' => $history->count()],
            ];
        }

        $q1Index = (int) floor($history->count() * 0.25);
        $q3Index = (int) floor($history->count() * 0.75);
        $q1 = $history[$q1Index];
        $q3 = $history[$q3Index];
        $iqr = $q3 - $q1;

        $lowerBound = $q1 - $multiplier * $iqr;
        $upperBound = $q3 + $multiplier * $iqr;
        $detected = $amount < $lowerBound || $amount > $upperBound;

        $distance = $detected ? max(0, $amount > $upperBound ? $amount - $upperBound : $lowerBound - $amount) : 0;
        $score = $iqr > 0 ? min(100.0, ($distance / $iqr) * 40.0) : 0.0;
        $confidence = min(0.90, 0.4 + ($history->count() / 200));

        return [
            'detected'   => $detected,
            'score'      => round($score, 2),
            'confidence' => round($confidence, 4),
            'details'    => [
                'q1'          => round($q1, 2),
                'q3'          => round($q3, 2),
                'iqr'         => round($iqr, 2),
                'lower_bound' => round($lowerBound, 2),
                'upper_bound' => round($upperBound, 2),
                'amount'      => $amount,
            ],
        ];
    }

    /**
     * Isolation Forest simulation via path-length scoring.
     */
    public function isolationForestAnalysis(array $context): array
    {
        $contamination = (float) config('fraud.statistical.isolation_forest_contamination', 0.1);
        $features = $this->extractNumericFeatures($context);

        if (empty($features)) {
            return [
                'detected'   => false,
                'score'      => 0.0,
                'confidence' => 0.2,
                'details'    => ['reason' => 'no_features'],
            ];
        }

        // Simulate isolation path length: extreme values = short paths = anomalous
        $totalPathLength = 0.0;
        $numFeatures = count($features);

        foreach ($features as $name => $value) {
            // Normalize each feature to [0,1] range using sigmoid-like transform
            $absVal = abs($value);
            $normalized = 1.0 / (1.0 + exp(-($absVal - 1.0)));

            // Path length inversely correlated with feature extremity
            $pathLength = max(1, 10 - (int) ($normalized * 9));
            $totalPathLength += $pathLength;
        }

        $avgPathLength = $totalPathLength / $numFeatures;
        // Normalize: shorter average path = more anomalous
        $anomalyScore = max(0.0, min(1.0, 1.0 - ($avgPathLength / 10.0)));
        $detected = $anomalyScore > (1.0 - $contamination);
        $score = round($anomalyScore * 100.0, 2);
        $confidence = min(0.85, 0.3 + ($numFeatures / 30.0));

        return [
            'detected'   => $detected,
            'score'      => $score,
            'confidence' => round($confidence, 4),
            'details'    => [
                'avg_path_length' => round($avgPathLength, 4),
                'anomaly_score'   => round($anomalyScore, 4),
                'contamination'   => $contamination,
                'feature_count'   => $numFeatures,
            ],
        ];
    }

    /**
     * Local Outlier Factor (LOF) approximation.
     */
    public function localOutlierFactorAnalysis(array $context, ?BehavioralProfile $profile): array
    {
        $k = (int) config('fraud.statistical.lof_neighbors', 20);
        $amount = (float) ($context['amount'] ?? 0);
        $history = collect($context['transaction_history'] ?? [])
            ->pluck('amount')
            ->map(fn ($v) => (float) $v)
            ->sort()
            ->values();

        if ($history->count() < $k) {
            return [
                'detected'   => false,
                'score'      => 0.0,
                'confidence' => 0.1,
                'details'    => ['reason' => 'insufficient_neighbors', 'count' => $history->count()],
            ];
        }

        // Compute k-distance (distance to k-th nearest neighbor)
        $distances = $history->map(fn (float $v) => abs($amount - $v))->sort()->values();
        $kDistance = $distances[$k - 1] ?? $distances->last();

        // Approximate local reachability density
        $sumReachDist = $distances->take($k)->sum(fn (float $d) => max($d, $kDistance));
        $lrd = $sumReachDist > 0 ? $k / $sumReachDist : 1.0;

        // Average neighbor density (approximate using overall distribution)
        $mean = $history->average();
        $stdDev = $this->standardDeviation($history->all());
        $avgDensity = $stdDev > 0 ? 1.0 / $stdDev : 1.0;

        $lofScore = $avgDensity > 0 ? $lrd / $avgDensity : 1.0;
        // LOF > 1 = inlier, LOF << 1 = outlier (low density relative to neighbors)
        $detected = $lofScore < 0.5 || $lofScore > 2.0;
        $normalizedScore = abs(1.0 - $lofScore);
        $score = min(100.0, $normalizedScore * 60.0);
        $confidence = min(0.80, 0.3 + ($history->count() / 100.0));

        return [
            'detected'   => $detected,
            'score'      => round($score, 2),
            'confidence' => round($confidence, 4),
            'details'    => [
                'lof_score'            => round($lofScore, 4),
                'k_distance'           => round($kDistance, 2),
                'local_density'        => round($lrd, 6),
                'avg_neighbor_density' => round($avgDensity, 6),
            ],
        ];
    }

    /**
     * Seasonal decomposition: check if transaction time deviates from historical patterns.
     */
    public function seasonalDecomposition(array $context, ?BehavioralProfile $profile): array
    {
        if (! $profile || ! $profile->is_established) {
            return ['detected' => false, 'score' => 0.0, 'confidence' => 0.1, 'details' => []];
        }

        $hour = (int) ($context['hour_of_day'] ?? now()->hour);
        $day = (int) ($context['day_of_week'] ?? now()->dayOfWeek);

        $timeDistribution = $profile->typical_transaction_times ?? [];
        $dayDistribution = $profile->typical_transaction_days ?? [];

        $hourPct = $timeDistribution[$hour] ?? 0;
        $dayPct = $dayDistribution[$day] ?? 0;

        $timeScore = $hourPct < 2.0 ? 60.0 : ($hourPct < 5.0 ? 30.0 : 0.0);
        $dayScore = $dayPct < 5.0 ? 40.0 : ($dayPct < 10.0 ? 20.0 : 0.0);
        $combinedScore = min(100.0, $timeScore + $dayScore);
        $detected = $combinedScore >= 50.0;

        return [
            'detected'   => $detected,
            'score'      => round($combinedScore, 2),
            'confidence' => round(min(0.85, 0.4 + ($profile->total_transaction_count / 200)), 4),
            'details'    => [
                'hour'     => $hour,
                'hour_pct' => $hourPct,
                'day'      => $day,
                'day_pct'  => $dayPct,
            ],
        ];
    }

    /**
     * Extract numeric features for isolation forest from context.
     */
    private function extractNumericFeatures(array $context): array
    {
        $features = [];
        $amount = (float) ($context['amount'] ?? 0);

        if ($amount > 0) {
            $features['amount_log'] = log($amount + 1);
        }
        if (isset($context['daily_transaction_count'])) {
            $features['daily_count'] = (float) $context['daily_transaction_count'];
        }
        if (isset($context['daily_transaction_volume'])) {
            $features['daily_volume_log'] = log((float) $context['daily_transaction_volume'] + 1);
        }
        if (isset($context['hourly_transaction_count'])) {
            $features['hourly_count'] = (float) $context['hourly_transaction_count'];
        }
        if (isset($context['time_since_last_transaction'])) {
            $features['time_since_last_log'] = log((float) $context['time_since_last_transaction'] + 1);
        }
        if (isset($context['hour_of_day'])) {
            // Cyclical encoding
            $h = (float) $context['hour_of_day'];
            $features['hour_sin'] = sin(2 * M_PI * $h / 24);
            $features['hour_cos'] = cos(2 * M_PI * $h / 24);
        }

        return $features;
    }

    private function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / ($count - 1);

        return sqrt($variance);
    }
}
