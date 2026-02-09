<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Activities;

use App\Domain\Fraud\Enums\AnomalyType;
use App\Domain\Fraud\Enums\DetectionMethod;
use App\Domain\Fraud\Models\BehavioralProfile;
use App\Domain\Fraud\Services\BehavioralAnalysisService;
use Workflow\Activity;

class BehavioralProfileActivity extends Activity
{
    /**
     * Execute behavioral anomaly detection.
     *
     * @param  array{context: array, profile_id: ?string, recent_transactions: array}  $input
     * @return array{anomaly_type: string, detected: bool, score: float, confidence: float, results: array}
     */
    public function execute(array $input): array
    {
        $context = $input['context'] ?? [];
        $profileId = $input['profile_id'] ?? null;
        $recentTransactions = $input['recent_transactions'] ?? [];

        $profile = $profileId ? BehavioralProfile::find($profileId) : null;

        if (! $profile || ! $profile->is_established) {
            return [
                'anomaly_type' => AnomalyType::Behavioral->value,
                'detected'     => false,
                'score'        => 0.0,
                'confidence'   => 0.2,
                'results'      => ['reason' => 'profile_not_established'],
            ];
        }

        $service = app(BehavioralAnalysisService::class);
        $results = [];
        $detected = false;
        $highestScore = 0.0;
        $highestConfidence = 0.0;

        // Adaptive threshold check
        $thresholds = $service->computeAdaptiveThresholds($profile);
        $amount = (float) ($context['amount'] ?? 0);
        $thresholdExceeded = $amount > $thresholds['amount_upper'] || $amount < $thresholds['amount_lower'];

        $thresholdScore = $thresholdExceeded ? min(100.0, (abs($amount - ($thresholds['amount_upper'] + $thresholds['amount_lower']) / 2) / max(1, $thresholds['amount_upper'] - $thresholds['amount_lower'])) * 80) : 0.0;

        $results[DetectionMethod::AdaptiveThreshold->value] = [
            'detected'   => $thresholdExceeded,
            'score'      => round($thresholdScore, 2),
            'confidence' => 0.85,
            'details'    => ['thresholds' => $thresholds, 'amount' => $amount],
        ];

        if ($thresholdExceeded) {
            $detected = true;
        }
        if ($thresholdScore > $highestScore) {
            $highestScore = $thresholdScore;
            $highestConfidence = 0.85;
        }

        // Drift detection
        $driftResult = $service->detectDrift($profile, $recentTransactions);
        $driftScore = $driftResult['drifted'] ? min(100.0, $driftResult['drift_score'] * 100) : 0.0;

        $results[DetectionMethod::DriftDetection->value] = [
            'detected'   => $driftResult['drifted'],
            'score'      => round($driftScore, 2),
            'confidence' => 0.75,
            'details'    => $driftResult['details'],
        ];

        if ($driftResult['drifted']) {
            $detected = true;
        }
        if ($driftScore > $highestScore) {
            $highestScore = $driftScore;
            $highestConfidence = 0.75;
        }

        // Segment classification (informational, not a detection)
        $segment = $service->classifySegment($profile);
        $results['segment'] = $segment;

        return [
            'anomaly_type' => AnomalyType::Behavioral->value,
            'detected'     => $detected,
            'score'        => round($highestScore, 2),
            'confidence'   => round($highestConfidence, 4),
            'results'      => $results,
        ];
    }
}
