<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Trading;

use Workflow\Activity;

/**
 * Identify Chart Patterns Activity.
 *
 * Atomic activity for identifying trading patterns in price data.
 */
class IdentifyPatternsActivity extends Activity
{
    /**
     * Execute pattern identification.
     *
     * @param array{prices: array<float>, volumes: array<float>, timeframe: string} $input
     *
     * @return array{patterns: array<string>, strength: float, recommendation: string}
     */
    public function execute(array $input): array
    {
        $prices = $input['prices'] ?? [];
        $volumes = $input['volumes'] ?? [];

        if (count($prices) < 20) {
            return [
                'patterns'       => [],
                'strength'       => 0.0,
                'recommendation' => 'insufficient_data',
            ];
        }

        $patterns = [];

        // Check for various patterns
        if ($this->isHeadAndShoulders($prices)) {
            $patterns[] = 'head_and_shoulders';
        }

        if ($this->isDoubleTop($prices)) {
            $patterns[] = 'double_top';
        }

        if ($this->isDoubleBottom($prices)) {
            $patterns[] = 'double_bottom';
        }

        if ($this->isAscendingTriangle($prices)) {
            $patterns[] = 'ascending_triangle';
        }

        if ($this->isDescendingTriangle($prices)) {
            $patterns[] = 'descending_triangle';
        }

        if ($this->isBullishFlag($prices, $volumes)) {
            $patterns[] = 'bullish_flag';
        }

        if ($this->isBearishFlag($prices, $volumes)) {
            $patterns[] = 'bearish_flag';
        }

        $strength = $this->calculatePatternStrength($patterns, $prices, $volumes);
        $recommendation = $this->generateRecommendation($patterns, $strength);

        return [
            'patterns'       => $patterns,
            'strength'       => round($strength, 2),
            'recommendation' => $recommendation,
        ];
    }

    private function isHeadAndShoulders(array $prices): bool
    {
        if (count($prices) < 5) {
            return false;
        }

        $recent = array_slice($prices, -5);

        // Simple head and shoulders pattern check
        return $recent[1] > $recent[0] && // Left shoulder
               $recent[2] > $recent[1] && // Head
               $recent[3] < $recent[2] && // Right shoulder
               $recent[3] > $recent[0] && // Right shoulder similar to left
               $recent[4] < $recent[3]; // Breakdown
    }

    private function isDoubleTop(array $prices): bool
    {
        if (count($prices) < 5) {
            return false;
        }

        $recent = array_slice($prices, -5);
        $slice1 = array_slice($recent, 0, 2);
        $slice2 = array_slice($recent, 3, 2);
        $slice3 = array_slice($recent, 1, 2);

        if (empty($slice1) || empty($slice2) || empty($slice3)) {
            return false;
        }

        $peak1 = max($slice1);
        $peak2 = max($slice2);
        $valley = min($slice3);

        return abs($peak1 - $peak2) / $peak1 < 0.03 && // Peaks within 3%
               $valley < $peak1 * 0.95; // Valley at least 5% below peaks
    }

    private function isDoubleBottom(array $prices): bool
    {
        if (count($prices) < 5) {
            return false;
        }

        $recent = array_slice($prices, -5);
        $slice1 = array_slice($recent, 0, 2);
        $slice2 = array_slice($recent, 3, 2);
        $slice3 = array_slice($recent, 1, 2);

        if (empty($slice1) || empty($slice2) || empty($slice3)) {
            return false;
        }

        $trough1 = min($slice1);
        $trough2 = min($slice2);
        $peak = max($slice3);

        return abs($trough1 - $trough2) / $trough1 < 0.03 && // Troughs within 3%
               $peak > $trough1 * 1.05; // Peak at least 5% above troughs
    }

    private function isAscendingTriangle(array $prices): bool
    {
        if (count($prices) < 10) {
            return false;
        }

        $highs = [];
        $lows = [];

        for ($i = 0; $i < count($prices) - 1; $i += 2) {
            $highs[] = max($prices[$i], $prices[$i + 1]);
            $lows[] = min($prices[$i], $prices[$i + 1]);
        }

        // Check if highs are relatively flat and lows are ascending
        $highsFlat = max($highs) - min($highs) < (max($highs) * 0.02);
        $lowsAscending = end($lows) > $lows[0];

        return $highsFlat && $lowsAscending;
    }

    private function isDescendingTriangle(array $prices): bool
    {
        if (count($prices) < 10) {
            return false;
        }

        $highs = [];
        $lows = [];

        for ($i = 0; $i < count($prices) - 1; $i += 2) {
            $highs[] = max($prices[$i], $prices[$i + 1]);
            $lows[] = min($prices[$i], $prices[$i + 1]);
        }

        // Check if lows are relatively flat and highs are descending
        $lowsFlat = max($lows) - min($lows) < (max($lows) * 0.02);
        $highsDescending = end($highs) < $highs[0];

        return $lowsFlat && $highsDescending;
    }

    private function isBullishFlag(array $prices, array $volumes): bool
    {
        if (count($prices) < 10 || count($volumes) < 10) {
            return false;
        }

        // Check for strong upward move followed by consolidation
        $initialMove = array_slice($prices, 0, 5);
        $consolidation = array_slice($prices, 5, 5);

        $strongUp = end($initialMove) > $initialMove[0] * 1.1;

        if (empty($consolidation)) {
            return false;
        }

        $maxConsolidation = max($consolidation);
        $minConsolidation = min($consolidation);
        $consolidating = $maxConsolidation - $minConsolidation < ($maxConsolidation * 0.05);
        $volumeDecreasing = array_sum(array_slice($volumes, -5)) < array_sum(array_slice($volumes, 0, 5)) * 0.7;

        return $strongUp && $consolidating && $volumeDecreasing;
    }

    private function isBearishFlag(array $prices, array $volumes): bool
    {
        if (count($prices) < 10 || count($volumes) < 10) {
            return false;
        }

        // Check for strong downward move followed by consolidation
        $initialMove = array_slice($prices, 0, 5);
        $consolidation = array_slice($prices, 5, 5);

        $strongDown = end($initialMove) < $initialMove[0] * 0.9;

        if (empty($consolidation)) {
            return false;
        }

        $maxConsolidation = max($consolidation);
        $minConsolidation = min($consolidation);
        $consolidating = $maxConsolidation - $minConsolidation < ($maxConsolidation * 0.05);
        $volumeDecreasing = array_sum(array_slice($volumes, -5)) < array_sum(array_slice($volumes, 0, 5)) * 0.7;

        return $strongDown && $consolidating && $volumeDecreasing;
    }

    private function calculatePatternStrength(array $patterns, array $prices, array $volumes): float
    {
        if (empty($patterns)) {
            return 0.0;
        }

        $strength = count($patterns) * 0.2;

        // Add volume confirmation
        if (! empty($volumes)) {
            $avgVolume = array_sum($volumes) / count($volumes);
            $recentVolume = array_sum(array_slice($volumes, -5)) / 5;
            if ($recentVolume > $avgVolume * 1.2) {
                $strength += 0.2;
            }
        }

        // Add trend confirmation
        $trend = end($prices) > $prices[0] ? 'up' : 'down';
        $bullishPatterns = ['double_bottom', 'ascending_triangle', 'bullish_flag'];
        $bearishPatterns = ['head_and_shoulders', 'double_top', 'descending_triangle', 'bearish_flag'];

        if ($trend === 'up' && array_intersect($patterns, $bullishPatterns)) {
            $strength += 0.2;
        } elseif ($trend === 'down' && array_intersect($patterns, $bearishPatterns)) {
            $strength += 0.2;
        }

        return min(1.0, $strength);
    }

    private function generateRecommendation(array $patterns, float $strength): string
    {
        if (empty($patterns)) {
            return 'no_clear_pattern';
        }

        $bullishPatterns = ['double_bottom', 'ascending_triangle', 'bullish_flag'];
        $bearishPatterns = ['head_and_shoulders', 'double_top', 'descending_triangle', 'bearish_flag'];

        $bullishCount = count(array_intersect($patterns, $bullishPatterns));
        $bearishCount = count(array_intersect($patterns, $bearishPatterns));

        if ($bullishCount > $bearishCount && $strength > 0.6) {
            return 'strong_buy';
        } elseif ($bullishCount > $bearishCount) {
            return 'buy';
        } elseif ($bearishCount > $bullishCount && $strength > 0.6) {
            return 'strong_sell';
        } elseif ($bearishCount > $bullishCount) {
            return 'sell';
        }

        return 'hold';
    }
}
