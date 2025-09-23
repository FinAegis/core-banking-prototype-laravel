<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Models\Agent;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FraudDetectionService
{
    private const RISK_THRESHOLDS = [
        'low'      => 30.0,
        'medium'   => 60.0,
        'high'     => 80.0,
        'critical' => 95.0,
    ];

    private const FRAUD_PATTERNS = [
        'velocity_check'     => ['weight' => 0.25, 'enabled' => true],
        'amount_anomaly'     => ['weight' => 0.20, 'enabled' => true],
        'reputation_check'   => ['weight' => 0.20, 'enabled' => true],
        'pattern_matching'   => ['weight' => 0.15, 'enabled' => true],
        'geographic_anomaly' => ['weight' => 0.10, 'enabled' => true],
        'time_anomaly'       => ['weight' => 0.10, 'enabled' => true],
    ];

    public function __construct()
    {
    }

    /**
     * Analyze transaction for fraud risk.
     */
    public function analyzeTransaction(
        string $transactionId,
        string $agentId,
        float $amount,
        array $metadata = []
    ): array {
        $riskFactors = [];
        $totalRiskScore = 0.0;

        // Check velocity (transaction frequency)
        if (self::FRAUD_PATTERNS['velocity_check']['enabled']) {
            $velocityRisk = $this->checkVelocity($agentId, $amount);
            $riskFactors['velocity'] = $velocityRisk;
            $totalRiskScore += $velocityRisk['score'] * self::FRAUD_PATTERNS['velocity_check']['weight'];
        }

        // Check amount anomaly
        if (self::FRAUD_PATTERNS['amount_anomaly']['enabled']) {
            $amountRisk = $this->checkAmountAnomaly($agentId, $amount);
            $riskFactors['amount'] = $amountRisk;
            $totalRiskScore += $amountRisk['score'] * self::FRAUD_PATTERNS['amount_anomaly']['weight'];
        }

        // Check agent reputation
        if (self::FRAUD_PATTERNS['reputation_check']['enabled']) {
            $reputationRisk = $this->checkReputation($agentId);
            $riskFactors['reputation'] = $reputationRisk;
            $totalRiskScore += $reputationRisk['score'] * self::FRAUD_PATTERNS['reputation_check']['weight'];
        }

        // Pattern matching
        if (self::FRAUD_PATTERNS['pattern_matching']['enabled']) {
            $patternRisk = $this->checkPatterns($agentId, $amount, $metadata);
            $riskFactors['patterns'] = $patternRisk;
            $totalRiskScore += $patternRisk['score'] * self::FRAUD_PATTERNS['pattern_matching']['weight'];
        }

        // Geographic anomaly
        if (self::FRAUD_PATTERNS['geographic_anomaly']['enabled'] && isset($metadata['location'])) {
            $geoRisk = $this->checkGeographicAnomaly($agentId, $metadata['location']);
            $riskFactors['geographic'] = $geoRisk;
            $totalRiskScore += $geoRisk['score'] * self::FRAUD_PATTERNS['geographic_anomaly']['weight'];
        }

        // Time-based anomaly
        if (self::FRAUD_PATTERNS['time_anomaly']['enabled']) {
            $timeRisk = $this->checkTimeAnomaly($agentId);
            $riskFactors['time'] = $timeRisk;
            $totalRiskScore += $timeRisk['score'] * self::FRAUD_PATTERNS['time_anomaly']['weight'];
        }

        $decision = $this->makeDecision($totalRiskScore);

        $analysis = [
            'transaction_id'  => $transactionId,
            'agent_id'        => $agentId,
            'risk_score'      => round($totalRiskScore, 2),
            'risk_level'      => $this->getRiskLevel($totalRiskScore),
            'risk_factors'    => $riskFactors,
            'decision'        => $decision,
            'requires_review' => $decision === 'review',
            'timestamp'       => now()->toIso8601String(),
        ];

        // Cache the analysis
        $this->cacheAnalysis($transactionId, $analysis);

        // Log high-risk transactions
        if ($totalRiskScore >= self::RISK_THRESHOLDS['high']) {
            Log::warning('High-risk transaction detected', $analysis);
        }

        return $analysis;
    }

    /**
     * Check transaction velocity.
     */
    private function checkVelocity(string $agentId, float $amount): array
    {
        $recentTransactions = $this->getRecentTransactionCount($agentId, 60); // Last hour
        $dailyTransactions = $this->getRecentTransactionCount($agentId, 1440); // Last 24 hours

        $hourlyLimit = 10;
        $dailyLimit = 50;

        $hourlyRisk = min(100, ($recentTransactions / $hourlyLimit) * 100);
        $dailyRisk = min(100, ($dailyTransactions / $dailyLimit) * 100);

        $velocityScore = max($hourlyRisk, $dailyRisk * 0.7);

        return [
            'score'          => $velocityScore,
            'hourly_count'   => $recentTransactions,
            'daily_count'    => $dailyTransactions,
            'hourly_risk'    => $hourlyRisk,
            'daily_risk'     => $dailyRisk,
            'exceeds_limits' => $recentTransactions > $hourlyLimit || $dailyTransactions > $dailyLimit,
        ];
    }

    /**
     * Check for amount anomalies.
     */
    private function checkAmountAnomaly(string $agentId, float $amount): array
    {
        $stats = $this->getTransactionStats($agentId);

        if ($stats['count'] === 0) {
            // New agent, moderate risk
            return [
                'score'      => 50.0,
                'is_anomaly' => false,
                'reason'     => 'new_agent',
            ];
        }

        $mean = $stats['avg'];
        $stdDev = $stats['std_dev'];

        // Calculate z-score
        $zScore = $stdDev > 0 ? abs($amount - $mean) / $stdDev : 0;

        // Risk increases with deviation from normal
        $anomalyScore = min(100, $zScore * 20);

        return [
            'score'       => $anomalyScore,
            'is_anomaly'  => $zScore > 3,
            'z_score'     => round($zScore, 2),
            'mean_amount' => round($mean, 2),
            'std_dev'     => round($stdDev, 2),
            'amount'      => $amount,
        ];
    }

    /**
     * Check agent reputation.
     */
    private function checkReputation(string $agentId): array
    {
        try {
            // For now, get reputation from Agent model directly to avoid event sourcing issues
            $agent = Agent::where('agent_id', $agentId)->first();

            if (! $agent) {
                // Unknown agent, high risk
                return [
                    'score'            => 80.0,
                    'reputation_score' => 0,
                    'trust_level'      => 'unknown',
                    'is_trusted'       => false,
                ];
            }

            $reputationScore = $agent->reputation_score ?? 50.0;

            // Invert reputation score for risk (low reputation = high risk)
            $riskScore = 100 - $reputationScore;

            $trustLevel = match (true) {
                $reputationScore >= 90 => 'excellent',
                $reputationScore >= 75 => 'good',
                $reputationScore >= 50 => 'neutral',
                $reputationScore >= 25 => 'low',
                default                => 'poor',
            };

            return [
                'score'            => $riskScore,
                'reputation_score' => $reputationScore,
                'trust_level'      => $trustLevel,
                'is_trusted'       => $reputationScore >= 70,
            ];
        } catch (Exception $e) {
            // Unknown agent, high risk
            return [
                'score'            => 80.0,
                'reputation_score' => 0,
                'trust_level'      => 'unknown',
                'is_trusted'       => false,
            ];
        }
    }

    /**
     * Check for known fraud patterns.
     */
    private function checkPatterns(string $agentId, float $amount, array $metadata): array
    {
        $patterns = [];
        $patternScore = 0;

        // Round amount fraud (amounts ending in .00)
        if ($amount == floor($amount) && $amount > 1000) {
            $patterns[] = 'round_amount';
            $patternScore += 20;
        }

        // Rapid small transactions (structuring)
        $smallTxCount = $this->getSmallTransactionCount($agentId, 60);
        if ($smallTxCount > 5) {
            $patterns[] = 'structuring';
            $patternScore += 40;
        }

        // Sequential transaction amounts
        if ($this->hasSequentialAmounts($agentId)) {
            $patterns[] = 'sequential_amounts';
            $patternScore += 30;
        }

        // Known fraud indicators in metadata
        if (isset($metadata['user_agent']) && $this->isSuspiciousUserAgent($metadata['user_agent'])) {
            $patterns[] = 'suspicious_user_agent';
            $patternScore += 25;
        }

        return [
            'score'             => min(100, $patternScore),
            'patterns_detected' => $patterns,
            'pattern_count'     => count($patterns),
        ];
    }

    /**
     * Check for geographic anomalies.
     */
    private function checkGeographicAnomaly(string $agentId, array $location): array
    {
        $previousLocations = $this->getRecentLocations($agentId);

        if (empty($previousLocations)) {
            return [
                'score'      => 0,
                'is_anomaly' => false,
                'reason'     => 'no_history',
            ];
        }

        $currentCountry = $location['country'] ?? 'unknown';
        $isNewCountry = ! in_array($currentCountry, $previousLocations);

        // Check for impossible travel
        $impossibleTravel = $this->checkImpossibleTravel($agentId, $location);

        $geoScore = 0;
        if ($isNewCountry) {
            $geoScore += 40;
        }
        if ($impossibleTravel) {
            $geoScore += 60;
        }

        return [
            'score'             => min(100, $geoScore),
            'is_anomaly'        => $isNewCountry || $impossibleTravel,
            'new_country'       => $isNewCountry,
            'impossible_travel' => $impossibleTravel,
            'country'           => $currentCountry,
        ];
    }

    /**
     * Check for time-based anomalies.
     */
    private function checkTimeAnomaly(string $agentId): array
    {
        $currentHour = now()->hour;
        $isWeekend = now()->isWeekend();

        $typicalHours = $this->getTypicalActivityHours($agentId);

        $isUnusualTime = ! in_array($currentHour, $typicalHours);
        $isNightTime = $currentHour >= 2 && $currentHour <= 5;

        $timeScore = 0;
        if ($isUnusualTime) {
            $timeScore += 30;
        }
        if ($isNightTime) {
            $timeScore += 20;
        }
        if ($isWeekend && ! $this->hasWeekendActivity($agentId)) {
            $timeScore += 15;
        }

        return [
            'score'         => min(100, $timeScore),
            'is_anomaly'    => $isUnusualTime || $isNightTime,
            'current_hour'  => $currentHour,
            'is_weekend'    => $isWeekend,
            'is_night_time' => $isNightTime,
            'typical_hours' => $typicalHours,
        ];
    }

    /**
     * Make a decision based on risk score.
     */
    private function makeDecision(float $riskScore): string
    {
        if ($riskScore < self::RISK_THRESHOLDS['low']) {
            return 'approve';
        } elseif ($riskScore < self::RISK_THRESHOLDS['high']) {
            return 'review';
        } else {
            return 'reject';
        }
    }

    /**
     * Get risk level from score.
     */
    private function getRiskLevel(float $riskScore): string
    {
        return match (true) {
            $riskScore >= self::RISK_THRESHOLDS['critical'] => 'critical',
            $riskScore >= self::RISK_THRESHOLDS['high']     => 'high',
            $riskScore >= self::RISK_THRESHOLDS['medium']   => 'medium',
            default                                         => 'low',
        };
    }

    // Helper methods
    private function getRecentTransactionCount(string $agentId, int $minutes): int
    {
        return DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->where('created_at', '>', now()->subMinutes($minutes))
            ->count();
    }

    private function getTransactionStats(string $agentId): array
    {
        $transactions = DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->pluck('amount')
            ->toArray();

        $count = count($transactions);
        $avg = $count > 0 ? array_sum($transactions) / $count : 0;

        // Calculate standard deviation manually for database compatibility
        $stdDev = 0;
        if ($count > 1) {
            $variance = 0;
            foreach ($transactions as $amount) {
                $variance += pow($amount - $avg, 2);
            }
            $variance /= ($count - 1);
            $stdDev = sqrt($variance);
        }

        return [
            'count'   => $count,
            'avg'     => $avg,
            'std_dev' => $stdDev,
        ];
    }

    private function getSmallTransactionCount(string $agentId, int $minutes): int
    {
        return DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->where('amount', '<', 100)
            ->where('created_at', '>', now()->subMinutes($minutes))
            ->count();
    }

    private function hasSequentialAmounts(string $agentId): bool
    {
        $recentAmounts = DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->where('created_at', '>', now()->subHours(1))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->pluck('amount')
            ->toArray();

        if (count($recentAmounts) < 3) {
            return false;
        }

        // Check for arithmetic sequence
        $differences = [];
        for ($i = 1; $i < count($recentAmounts); $i++) {
            $differences[] = $recentAmounts[$i] - $recentAmounts[$i - 1];
        }

        $uniqueDiffs = array_unique($differences);

        return count($uniqueDiffs) === 1 && $uniqueDiffs[0] != 0;
    }

    private function isSuspiciousUserAgent(string $userAgent): bool
    {
        $suspiciousPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python',
        ];

        $userAgentLower = strtolower($userAgent);
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function getRecentLocations(string $agentId): array
    {
        return DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->where('created_at', '>', now()->subDays(30))
            ->whereNotNull('metadata->country')
            ->distinct('metadata->country')
            ->pluck('metadata->country')
            ->toArray();
    }

    private function checkImpossibleTravel(string $agentId, array $location): bool
    {
        // Simplified check - in production, calculate actual distance and time
        $lastTransaction = DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->where('created_at', '>', now()->subHours(2))
            ->whereNotNull('metadata->country')
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastTransaction) {
            return false;
        }

        // If countries are different and less than 2 hours apart
        $lastCountry = json_decode($lastTransaction->metadata, true)['country'] ?? null;

        return $lastCountry !== ($location['country'] ?? null);
    }

    private function getTypicalActivityHours(string $agentId): array
    {
        $transactions = DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->get();

        $hourCounts = [];
        foreach ($transactions as $transaction) {
            $hour = (int) date('H', strtotime($transaction->created_at));
            if (! isset($hourCounts[$hour])) {
                $hourCounts[$hour] = 0;
            }
            $hourCounts[$hour]++;
        }

        $hours = [];
        foreach ($hourCounts as $hour => $count) {
            if ($count > 2) {
                $hours[] = $hour;
            }
        }

        return $hours ?: range(9, 17); // Default business hours
    }

    private function hasWeekendActivity(string $agentId): bool
    {
        $transactions = DB::table('agent_transactions')
            ->where('from_agent_id', $agentId)
            ->get();

        foreach ($transactions as $transaction) {
            $dayOfWeek = date('w', strtotime($transaction->created_at));
            // 0 = Sunday, 6 = Saturday in PHP's date('w')
            if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                return true;
            }
        }

        return false;
    }

    private function cacheAnalysis(string $transactionId, array $analysis): void
    {
        Cache::put(
            "fraud_analysis:{$transactionId}",
            $analysis,
            now()->addDays(30)
        );
    }
}
