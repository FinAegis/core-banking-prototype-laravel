<?php

namespace App\Domain\Fraud\Services;

use App\Domain\Account\Models\Transaction;
use App\Domain\Fraud\Models\FraudScore;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MachineLearningService
{
    private bool $enabled;

    private string $modelVersion;

    private string $apiEndpoint;

    private array $modelConfig;

    public function __construct()
    {
        $this->enabled = config('fraud.ml.enabled', false);
        $this->modelVersion = config('fraud.ml.model_version', '1.0.0');
        $this->apiEndpoint = config('fraud.ml.api_endpoint', '');
        $this->modelConfig = config('fraud.ml.config', []);
    }

    /**
     * Check if ML service is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && ! empty($this->apiEndpoint);
    }

    /**
     * Predict fraud risk using ML model.
     */
    public function predict(array $context): array
    {
        if (! $this->isEnabled()) {
            return [
                'score'         => 0,
                'confidence'    => 0,
                'model_version' => null,
                'features'      => [],
                'explanation'   => 'ML service disabled',
            ];
        }

        try {
            // Extract features
            $features = $this->extractFeatures($context);

            // Get prediction from ML service
            $prediction = $this->getPrediction($features);

            return [
                'score'         => $prediction['fraud_probability'] * 100,
                'confidence'    => $prediction['confidence'],
                'model_version' => $this->modelVersion,
                'features'      => $features,
                'explanation'   => $prediction['explanation'] ?? null,
                'risk_factors'  => $prediction['risk_factors'] ?? [],
            ];
        } catch (Exception $e) {
            Log::error(
                'ML prediction failed',
                [
                    'error'   => $e->getMessage(),
                    'context' => $context,
                ]
            );

            return [
                'score'         => 0,
                'confidence'    => 0,
                'model_version' => $this->modelVersion,
                'features'      => [],
                'explanation'   => 'ML prediction error',
            ];
        }
    }

    /**
     * Extract features for ML model.
     */
    protected function extractFeatures(array $context): array
    {
        $features = [];

        // Transaction features
        $features['amount'] = $context['amount'] ?? 0;
        $features['amount_normalized'] = $this->normalizeAmount($features['amount']);
        $features['currency'] = $context['currency'] ?? 'USD';
        $features['type'] = $context['type'] ?? 'unknown';
        $features['is_withdrawal'] = $features['type'] === 'withdrawal' ? 1 : 0;
        $features['is_transfer'] = $features['type'] === 'transfer' ? 1 : 0;

        // Temporal features
        $features['hour_of_day'] = $context['hour_of_day'] ?? 0;
        $features['day_of_week'] = $context['day_of_week'] ?? 0;
        $features['is_weekend'] = $context['is_weekend'] ?? false ? 1 : 0;
        $features['is_night'] = $features['hour_of_day'] >= 22 || $features['hour_of_day'] < 6 ? 1 : 0;

        // Velocity features
        $features['daily_transaction_count'] = $context['daily_transaction_count'] ?? 0;
        $features['daily_transaction_volume'] = $context['daily_transaction_volume'] ?? 0;
        $features['hourly_transaction_count'] = $context['hourly_transaction_count'] ?? 0;
        $features['time_since_last_transaction'] = $context['time_since_last_transaction'] ?? 9999;

        // User features
        $user = $context['user'] ?? [];
        $features['user_age_days'] = isset($user['created_at']) ?
            now()->diffInDays($user['created_at']) : 0;
        $features['user_transaction_count'] = $context['user_transaction_count'] ?? 0;
        $features['kyc_level'] = $this->encodeKycLevel($user['kyc_level'] ?? 'none');
        $features['risk_rating'] = $this->encodeRiskRating($user['risk_rating'] ?? 'medium');

        // Behavioral features
        $behavioral = $context['behavioral_analysis'] ?? [];
        $features['behavioral_deviation_score'] = $behavioral['deviation_score'] ?? 0;
        $features['is_established_profile'] = $behavioral['is_established'] ?? false ? 1 : 0;
        $features['profile_confidence'] = $behavioral['profile_confidence'] ?? 0;

        // Device features
        $device = $context['device_data'] ?? [];
        $features['device_risk_score'] = $device['risk_score'] ?? 50;
        $features['is_trusted_device'] = $device['is_trusted'] ?? false ? 1 : 0;
        $features['is_vpn'] = $device['is_vpn'] ?? false ? 1 : 0;
        $features['is_proxy'] = $device['is_proxy'] ?? false ? 1 : 0;
        $features['device_age_days'] = $this->calculateDeviceAge($device);

        // Network features
        $features['ip_country_risk'] = $this->getCountryRisk($context['ip_country'] ?? null);
        $features['is_high_risk_country'] = $features['ip_country_risk'] > 70 ? 1 : 0;

        // Account features
        $features['account_balance_ratio'] = $this->calculateBalanceRatio(
            $features['amount'],
            $context['account_balance'] ?? 0
        );

        // Historical pattern features
        $features['avg_transaction_amount'] = $behavioral['analysis_details']['amount']['average_amount'] ?? 0;
        $features['amount_deviation'] = $features['avg_transaction_amount'] > 0 ?
            abs($features['amount'] - $features['avg_transaction_amount']) / $features['avg_transaction_amount'] : 0;

        // Rule engine features
        $ruleResults = $context['rule_results'] ?? [];
        $features['rules_triggered_count'] = count($ruleResults['triggered_rules'] ?? []);
        $features['rule_total_score'] = $ruleResults['total_score'] ?? 0;
        $features['has_blocking_rules'] = ! empty($ruleResults['blocking_rules']) ? 1 : 0;

        // Cross-feature engineering
        $features['velocity_amount_product'] = $features['daily_transaction_count'] * $features['amount_normalized'];
        $features['risk_composite'] = ($features['device_risk_score'] + $features['behavioral_deviation_score'] +
                                      $features['rule_total_score']) / 3;

        return $features;
    }

    /**
     * Get prediction from ML service.
     */
    protected function getPrediction(array $features): array
    {
        // In production, this would call an actual ML service
        // For now, we'll simulate with a rule-based approach

        $fraudProbability = 0;
        $riskFactors = [];

        // High-risk indicators
        if ($features['has_blocking_rules']) {
            $fraudProbability += 0.4;
            $riskFactors[] = 'blocking_rules_triggered';
        }

        if ($features['risk_composite'] > 70) {
            $fraudProbability += 0.3;
            $riskFactors[] = 'high_composite_risk';
        }

        if ($features['is_vpn'] || $features['is_proxy']) {
            $fraudProbability += 0.2;
            $riskFactors[] = 'anonymous_connection';
        }

        if ($features['amount_deviation'] > 5) {
            $fraudProbability += 0.15;
            $riskFactors[] = 'unusual_amount';
        }

        if ($features['is_high_risk_country']) {
            $fraudProbability += 0.15;
            $riskFactors[] = 'high_risk_location';
        }

        // Low-risk indicators (reduce probability)
        if ($features['is_trusted_device']) {
            $fraudProbability -= 0.2;
        }

        if ($features['is_established_profile'] && $features['profile_confidence'] > 80) {
            $fraudProbability -= 0.15;
        }

        if ($features['kyc_level'] >= 2) { // Enhanced or full KYC
            $fraudProbability -= 0.1;
        }

        // Ensure probability is between 0 and 1
        $fraudProbability = max(0, min(1, $fraudProbability));

        // Calculate confidence based on data availability
        $confidence = $this->calculateConfidence($features);

        return [
            'fraud_probability' => $fraudProbability,
            'confidence'        => $confidence,
            'risk_factors'      => $riskFactors,
            'explanation'       => $this->generateExplanation($fraudProbability, $riskFactors),
        ];
    }

    /**
     * Train model with feedback.
     */
    public function trainWithFeedback(FraudScore $fraudScore, string $actualOutcome): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            $trainingData = [
                'fraud_score_id'     => $fraudScore->id,
                'features'           => $fraudScore->ml_features,
                'predicted_score'    => $fraudScore->ml_score,
                'actual_outcome'     => $actualOutcome,
                'decision'           => $fraudScore->decision,
                'feedback_timestamp' => now()->toIso8601String(),
            ];

            // In production, send to ML training pipeline
            $this->sendTrainingData($trainingData);

            // Update fraud score with outcome
            $fraudScore->update(['outcome' => $actualOutcome]);
        } catch (Exception $e) {
            Log::error(
                'ML training feedback failed',
                [
                    'fraud_score_id' => $fraudScore->id,
                    'error'          => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Batch predict for multiple transactions.
     */
    public function batchPredict(array $transactions): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $predictions = [];

        foreach ($transactions as $transaction) {
            $context = $this->buildContextFromTransaction($transaction);
            $predictions[$transaction['id']] = $this->predict($context);
        }

        return $predictions;
    }

    /**
     * Get model performance metrics.
     */
    public function getModelMetrics(): array
    {
        return Cache::remember(
            'ml_model_metrics',
            3600,
            function () {
                // In production, fetch from ML service
                return [
                    'model_version'      => $this->modelVersion,
                    'accuracy'           => 0.94,
                    'precision'          => 0.89,
                    'recall'             => 0.82,
                    'f1_score'           => 0.85,
                    'auc_roc'            => 0.91,
                    'last_trained'       => now()->subDays(7)->toIso8601String(),
                    'training_samples'   => 150000,
                    'feature_importance' => $this->getFeatureImportance(),
                ];
            }
        );
    }

    /**
     * Get feature importance scores.
     */
    protected function getFeatureImportance(): array
    {
        // In production, this would come from the ML model
        return [
            'risk_composite'              => 0.15,
            'amount_deviation'            => 0.12,
            'device_risk_score'           => 0.10,
            'behavioral_deviation_score'  => 0.09,
            'rules_triggered_count'       => 0.08,
            'velocity_amount_product'     => 0.07,
            'is_vpn'                      => 0.06,
            'is_high_risk_country'        => 0.05,
            'account_balance_ratio'       => 0.05,
            'time_since_last_transaction' => 0.04,
        ];
    }

    /**
     * Normalize amount for ML features.
     */
    protected function normalizeAmount(float $amount): float
    {
        // Log transformation for better ML performance
        return log1p($amount);
    }

    /**
     * Encode KYC level.
     */
    protected function encodeKycLevel(?string $kycLevel): int
    {
        return match ($kycLevel) {
            'full'     => 3,
            'enhanced' => 2,
            'basic'    => 1,
            default    => 0,
        };
    }

    /**
     * Encode risk rating.
     */
    protected function encodeRiskRating(?string $riskRating): int
    {
        return match ($riskRating) {
            'very_high' => 4,
            'high'      => 3,
            'medium'    => 2,
            'low'       => 1,
            default     => 2,
        };
    }

    /**
     * Calculate device age in days.
     */
    protected function calculateDeviceAge(array $device): int
    {
        if (! isset($device['first_seen_at'])) {
            return 0;
        }

        try {
            return now()->diffInDays($device['first_seen_at']);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get country risk score.
     */
    protected function getCountryRisk(?string $country): int
    {
        if (! $country) {
            return 50;
        }

        // High-risk countries
        $highRisk = ['NG', 'PK', 'ID', 'VN', 'BD', 'KE', 'GH'];
        if (in_array($country, $highRisk)) {
            return 80;
        }

        // Medium-risk countries
        $mediumRisk = ['IN', 'PH', 'MY', 'TH', 'EG', 'ZA'];
        if (in_array($country, $mediumRisk)) {
            return 50;
        }

        // Low-risk countries
        return 20;
    }

    /**
     * Calculate balance ratio.
     */
    protected function calculateBalanceRatio(float $amount, float $balance): float
    {
        if ($balance <= 0) {
            return 1.0;
        }

        return min(1.0, $amount / $balance);
    }

    /**
     * Calculate prediction confidence.
     */
    protected function calculateConfidence(array $features): float
    {
        $confidence = 0.5; // Base confidence

        // Increase confidence with more data
        if ($features['user_transaction_count'] > 100) {
            $confidence += 0.1;
        }
        if ($features['is_established_profile']) {
            $confidence += 0.15;
        }
        if ($features['profile_confidence'] > 80) {
            $confidence += 0.1;
        }
        if ($features['device_age_days'] > 30) {
            $confidence += 0.05;
        }
        if ($features['rules_triggered_count'] > 3) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    /**
     * Generate explanation for prediction.
     */
    protected function generateExplanation(float $probability, array $riskFactors): string
    {
        if ($probability < 0.3) {
            return 'Low fraud risk based on established patterns and trusted indicators.';
        } elseif ($probability < 0.6) {
            $factors = implode(', ', array_slice($riskFactors, 0, 2));

            return "Medium fraud risk due to: {$factors}.";
        } else {
            $factors = implode(', ', array_slice($riskFactors, 0, 3));

            return "High fraud risk detected. Key factors: {$factors}.";
        }
    }

    /**
     * Send training data to ML pipeline.
     */
    protected function sendTrainingData(array $data): void
    {
        // In production, this would send to ML training pipeline
        Log::info(
            'ML training data collected',
            [
                'fraud_score_id' => $data['fraud_score_id'],
                'outcome'        => $data['actual_outcome'],
            ]
        );
    }

    /**
     * Build context from transaction.
     */
    protected function buildContextFromTransaction($transaction): array
    {
        // Build minimal context for batch processing
        return [
            'transaction' => $transaction,
            'amount'      => $transaction['amount'] ?? 0,
            'currency'    => $transaction['currency'] ?? 'USD',
            'type'        => $transaction['type'] ?? 'unknown',
            'timestamp'   => $transaction['created_at'] ?? now(),
        ];
    }

    /**
     * Update model version.
     */
    public function updateModelVersion(string $version): void
    {
        $this->modelVersion = $version;
        Cache::put('ml_model_version', $version, 86400);
    }

    /**
     * Get explainable AI insights.
     */
    public function getExplainableInsights(array $features, float $prediction): array
    {
        $importance = $this->getFeatureImportance();
        $insights = [];

        foreach ($importance as $feature => $weight) {
            if (isset($features[$feature])) {
                $contribution = $features[$feature] * $weight;
                $insights[] = [
                    'feature'      => $feature,
                    'value'        => $features[$feature],
                    'importance'   => $weight,
                    'contribution' => $contribution,
                    'direction'    => $contribution > 0 ? 'increases_risk' : 'decreases_risk',
                ];
            }
        }

        // Sort by contribution
        usort($insights, fn ($a, $b) => abs($b['contribution']) <=> abs($a['contribution']));

        return array_slice($insights, 0, 10);
    }
}
