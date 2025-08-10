<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Trading;

use Workflow\Activity;

/**
 * Calculate Relative Strength Index (RSI) Activity.
 *
 * Atomic activity for calculating RSI technical indicator.
 */
class CalculateRSIActivity extends Activity
{
    /**
     * Execute RSI calculation.
     *
     * @param array{prices: array<float>, period: int} $input
     *
     * @return array{value: float, signal: string, strength: float}
     */
    public function execute(array $input): array
    {
        $prices = $input['prices'] ?? [];
        $period = $input['period'] ?? 14;

        if (count($prices) < $period + 1) {
            return [
                'value'    => 50.0,
                'signal'   => 'neutral',
                'strength' => 0.0,
            ];
        }

        // Calculate price changes
        $gains = [];
        $losses = [];

        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            $gains[] = max(0, $change);
            $losses[] = abs(min(0, $change));
        }

        // Calculate average gain and loss
        $avgGain = array_sum(array_slice($gains, -$period)) / $period;
        $avgLoss = array_sum(array_slice($losses, -$period)) / $period;

        // Calculate RSI
        $rs = $avgLoss > 0 ? $avgGain / $avgLoss : 100;
        $rsi = 100 - (100 / (1 + $rs));

        return [
            'value'    => round($rsi, 2),
            'signal'   => $this->interpretRSI($rsi),
            'strength' => $this->calculateStrength($rsi),
        ];
    }

    /**
     * Interpret RSI value.
     */
    private function interpretRSI(float $rsi): string
    {
        return match (true) {
            $rsi >= 70 => 'overbought',
            $rsi <= 30 => 'oversold',
            $rsi >= 60 => 'bullish',
            $rsi <= 40 => 'bearish',
            default    => 'neutral',
        };
    }

    /**
     * Calculate signal strength.
     */
    private function calculateStrength(float $rsi): float
    {
        if ($rsi >= 70 || $rsi <= 30) {
            return min(1.0, abs($rsi - 50) / 30);
        }

        return abs($rsi - 50) / 50;
    }
}
