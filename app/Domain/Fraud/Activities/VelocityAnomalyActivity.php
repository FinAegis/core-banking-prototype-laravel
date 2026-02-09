<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Activities;

use App\Domain\Fraud\Enums\AnomalyType;
use App\Domain\Fraud\Enums\DetectionMethod;
use App\Domain\Fraud\Services\RuleEngineService;
use Workflow\Activity;

class VelocityAnomalyActivity extends Activity
{
    /**
     * Execute velocity anomaly detection.
     *
     * @param  array{context: array}  $input
     * @return array{anomaly_type: string, detected: bool, score: float, confidence: float, results: array}
     */
    public function execute(array $input): array
    {
        $context = $input['context'] ?? [];

        $service = app(RuleEngineService::class);
        $results = [];
        $detected = false;
        $highestScore = 0.0;

        // Sliding window analysis
        $windowResults = $service->evaluateSlidingWindows($context);
        $exceededWindows = collect($windowResults)->filter(fn ($r) => $r['exceeded']);
        $windowScore = min(100.0, $exceededWindows->count() * 25.0);

        $results[DetectionMethod::SlidingWindow->value] = [
            'detected'   => $exceededWindows->isNotEmpty(),
            'score'      => round($windowScore, 2),
            'confidence' => 0.9,
            'details'    => $windowResults,
        ];

        if ($exceededWindows->isNotEmpty()) {
            $detected = true;
            $highestScore = max($highestScore, $windowScore);
        }

        // Burst detection
        $burstResult = $service->detectBurst($context);
        $burstScore = $burstResult['burst_detected'] ? min(100.0, $burstResult['burst_ratio'] * 20.0) : 0.0;

        $results[DetectionMethod::BurstDetection->value] = [
            'detected'   => $burstResult['burst_detected'],
            'score'      => round($burstScore, 2),
            'confidence' => 0.85,
            'details'    => $burstResult['details'],
        ];

        if ($burstResult['burst_detected']) {
            $detected = true;
            $highestScore = max($highestScore, $burstScore);
        }

        // Cross-account correlation
        $crossAccount = $service->detectCrossAccountActivity($context);
        if ($crossAccount['detected']) {
            $detected = true;
            $crossScore = 70.0;
            $highestScore = max($highestScore, $crossScore);
            $results['cross_account'] = [
                'detected'   => true,
                'score'      => $crossScore,
                'confidence' => 0.8,
                'details'    => $crossAccount['details'],
            ];
        }

        return [
            'anomaly_type' => AnomalyType::Velocity->value,
            'detected'     => $detected,
            'score'        => round($highestScore, 2),
            'confidence'   => $detected ? 0.85 : 0.5,
            'results'      => $results,
        ];
    }
}
