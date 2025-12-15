<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\DataObjects\ReputationScore;
use App\Domain\AgentProtocol\Models\AgentIdentity;
use App\Domain\AgentProtocol\Models\AgentTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

/**
 * Activity that generates comprehensive reputation reports for agents.
 */
class GenerateReputationReportActivity extends Activity
{
    public function execute(
        string $agentId,
        ReputationScore $currentScore,
        array $options = []
    ): array {
        $period = $options['period'] ?? 'month';
        $startDate = $this->getStartDate($period);

        $agent = AgentIdentity::where('did', $agentId)->first();

        if (! $agent) {
            Log::warning('Agent not found for report generation', ['agent_id' => $agentId]);

            return ['error' => 'Agent not found'];
        }

        // Gather transaction statistics
        $transactionStats = $this->getTransactionStatistics($agentId, $startDate);

        // Gather reputation history
        $reputationHistory = $this->getReputationHistory($agentId, $startDate);

        // Calculate metrics
        $metrics = $this->calculateMetrics($transactionStats, $currentScore);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($metrics, $currentScore);

        // Build the report
        $report = [
            'report_id'    => 'rep_' . uniqid(),
            'generated_at' => now()->toIso8601String(),
            'period'       => [
                'type'       => $period,
                'start_date' => $startDate->toIso8601String(),
                'end_date'   => now()->toIso8601String(),
            ],
            'agent' => [
                'did'           => $agent->did,
                'display_name'  => $agent->display_name,
                'registered_at' => $agent->created_at?->toIso8601String(),
            ],
            'current_reputation' => [
                'score'       => $currentScore->score,
                'trust_level' => $currentScore->trustLevel,
                'percentile'  => $this->calculatePercentile($currentScore->score),
            ],
            'transaction_summary' => $transactionStats,
            'reputation_history'  => $reputationHistory,
            'metrics'             => $metrics,
            'recommendations'     => $recommendations,
            'compliance'          => $this->getComplianceStatus($agent),
        ];

        Log::info('Reputation report generated', [
            'agent_id'  => $agentId,
            'report_id' => $report['report_id'],
            'period'    => $period,
        ]);

        return $report;
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'week'    => now()->subWeek(),
            'month'   => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year'    => now()->subYear(),
            default   => now()->subMonth(),
        };
    }

    private function getTransactionStatistics(string $agentId, Carbon $startDate): array
    {
        $transactions = AgentTransaction::where(function ($query) use ($agentId) {
            $query->where('from_agent_id', $agentId)
                ->orWhere('to_agent_id', $agentId);
        })
            ->where('created_at', '>=', $startDate)
            ->get();

        $total = $transactions->count();
        $completed = $transactions->where('status', 'completed')->count();
        $failed = $transactions->where('status', 'failed')->count();
        $disputed = $transactions->where('status', 'disputed')->count();

        $totalVolume = $transactions->where('status', 'completed')->sum('amount');
        $avgTransactionValue = $completed > 0 ? $totalVolume / $completed : 0;

        return [
            'total_transactions'        => $total,
            'completed'                 => $completed,
            'failed'                    => $failed,
            'disputed'                  => $disputed,
            'success_rate'              => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'dispute_rate'              => $total > 0 ? round(($disputed / $total) * 100, 2) : 0,
            'total_volume'              => round($totalVolume, 2),
            'average_transaction_value' => round($avgTransactionValue, 2),
            'currencies'                => $transactions->pluck('currency')->unique()->values()->toArray(),
        ];
    }

    private function getReputationHistory(string $agentId, Carbon $startDate): array
    {
        // This would typically query from a reputation_history table
        // For now, we'll generate sample data points

        $dataPoints = [];
        $currentDate = $startDate->copy();
        $score = 50.0; // Starting score

        while ($currentDate <= now()) {
            // Simulate score fluctuation
            $change = (mt_rand(-10, 15) / 10);
            $score = max(0, min(100, $score + $change));

            $dataPoints[] = [
                'date'  => $currentDate->toDateString(),
                'score' => round($score, 1),
            ];

            $currentDate->addDay();
        }

        return [
            'data_points' => $dataPoints,
            'trend'       => $this->calculateTrend($dataPoints),
            'volatility'  => $this->calculateVolatility($dataPoints),
        ];
    }

    private function calculateTrend(array $dataPoints): string
    {
        if (count($dataPoints) < 2) {
            return 'stable';
        }

        $firstScore = $dataPoints[0]['score'];
        $lastScore = end($dataPoints)['score'];
        $difference = $lastScore - $firstScore;

        if ($difference > 5) {
            return 'improving';
        }

        if ($difference < -5) {
            return 'declining';
        }

        return 'stable';
    }

    private function calculateVolatility(array $dataPoints): float
    {
        if (count($dataPoints) < 2) {
            return 0.0;
        }

        $scores = array_column($dataPoints, 'score');
        $mean = array_sum($scores) / count($scores);

        $squaredDiffs = array_map(fn ($score) => pow($score - $mean, 2), $scores);
        $variance = array_sum($squaredDiffs) / count($scores);

        return round(sqrt($variance), 2);
    }

    private function calculateMetrics(array $transactionStats, ReputationScore $currentScore): array
    {
        return [
            'reliability_score' => min(100, $transactionStats['success_rate'] * 1.1),
            'activity_score'    => min(100, $transactionStats['total_transactions'] * 2),
            'trust_score'       => $currentScore->score,
            'dispute_impact'    => max(0, 100 - ($transactionStats['dispute_rate'] * 5)),
            'overall_health'    => $this->calculateOverallHealth($transactionStats, $currentScore),
        ];
    }

    private function calculateOverallHealth(array $stats, ReputationScore $score): string
    {
        $healthScore = ($stats['success_rate'] * 0.4) +
                       ($score->score * 0.4) +
                       ((100 - $stats['dispute_rate']) * 0.2);

        if ($healthScore >= 80) {
            return 'excellent';
        }

        if ($healthScore >= 60) {
            return 'good';
        }

        if ($healthScore >= 40) {
            return 'fair';
        }

        return 'needs_improvement';
    }

    private function calculatePercentile(float $score): int
    {
        // Simplified percentile calculation
        // In production, this would compare against all agents
        return min(99, max(1, (int) $score));
    }

    private function generateRecommendations(array $metrics, ReputationScore $score): array
    {
        $recommendations = [];

        if ($metrics['reliability_score'] < 70) {
            $recommendations[] = [
                'priority'        => 'high',
                'category'        => 'reliability',
                'recommendation'  => 'Improve transaction success rate by ensuring adequate balance before transactions',
                'expected_impact' => '+5-10 reputation points',
            ];
        }

        if ($metrics['activity_score'] < 30) {
            $recommendations[] = [
                'priority'        => 'medium',
                'category'        => 'activity',
                'recommendation'  => 'Increase transaction activity to build reputation faster',
                'expected_impact' => '+2-5 reputation points per successful transaction',
            ];
        }

        if ($metrics['dispute_impact'] < 80) {
            $recommendations[] = [
                'priority'        => 'high',
                'category'        => 'disputes',
                'recommendation'  => 'Focus on dispute prevention through clear communication and service delivery',
                'expected_impact' => 'Prevent reputation loss from disputes',
            ];
        }

        if ($score->score < 60) {
            $recommendations[] = [
                'priority'        => 'critical',
                'category'        => 'trust',
                'recommendation'  => 'Complete more transactions successfully to rebuild trust',
                'expected_impact' => 'Unlock higher transaction limits and features',
            ];
        }

        if ($score->score >= 80) {
            $recommendations[] = [
                'priority'        => 'low',
                'category'        => 'maintenance',
                'recommendation'  => 'Maintain current service quality to preserve trusted status',
                'expected_impact' => 'Retain premium features and higher limits',
            ];
        }

        return $recommendations;
    }

    private function getComplianceStatus(AgentIdentity $agent): array
    {
        $metadata = $agent->metadata ?? [];

        return [
            'kyc_verified'        => ($metadata['kyc_status'] ?? null) === 'verified',
            'kyc_level'           => $metadata['kyc_level'] ?? 'none',
            'last_review_date'    => $metadata['last_compliance_review'] ?? null,
            'sanctions_clear'     => true, // Would be checked against actual sanctions list
            'active_restrictions' => [],
        ];
    }
}
