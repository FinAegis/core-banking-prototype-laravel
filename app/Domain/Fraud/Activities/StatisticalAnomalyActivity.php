<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Activities;

use App\Domain\Fraud\Enums\AnomalyType;
use App\Domain\Fraud\Models\BehavioralProfile;
use App\Domain\Fraud\Services\StatisticalAnalysisService;
use Workflow\Activity;

class StatisticalAnomalyActivity extends Activity
{
    /**
     * Execute statistical anomaly detection.
     *
     * @param  array{context: array, profile_id: ?string}  $input
     * @return array{anomaly_type: string, detected: bool, score: float, confidence: float, results: array}
     */
    public function execute(array $input): array
    {
        $context = $input['context'] ?? [];
        $profileId = $input['profile_id'] ?? null;

        $profile = $profileId ? BehavioralProfile::find($profileId) : null;

        $service = app(StatisticalAnalysisService::class);
        $results = $service->analyze($context, $profile);

        // Aggregate: take highest-scoring detection
        $highestScore = 0.0;
        $highestConfidence = 0.0;
        $detected = false;

        foreach ($results as $result) {
            if ($result['score'] > $highestScore) {
                $highestScore = $result['score'];
                $highestConfidence = $result['confidence'];
            }
            if ($result['detected']) {
                $detected = true;
            }
        }

        return [
            'anomaly_type' => AnomalyType::Statistical->value,
            'detected'     => $detected,
            'score'        => round($highestScore, 2),
            'confidence'   => round($highestConfidence, 4),
            'results'      => $results,
        ];
    }
}
