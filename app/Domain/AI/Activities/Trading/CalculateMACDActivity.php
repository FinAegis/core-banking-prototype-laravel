<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Trading;

use Workflow\Activity;

/**
 * Calculate MACD (Moving Average Convergence Divergence) Activity.
 *
 * Atomic activity for calculating MACD technical indicator.
 */
class CalculateMACDActivity extends Activity
{
    /**
     * Execute MACD calculation.
     *
     * @param array{prices: array<float>, fast: int, slow: int, signal: int} $input
     *
     * @return array{macd: float, signal: float, histogram: float, trend: string}
     */
    public function execute(array $input): array
    {
        $prices = $input['prices'] ?? [];
        $fastPeriod = $input['fast'] ?? 12;
        $slowPeriod = $input['slow'] ?? 26;
        $signalPeriod = $input['signal'] ?? 9;

        if (count($prices) < $slowPeriod + $signalPeriod) {
            return [
                'macd'      => 0.0,
                'signal'    => 0.0,
                'histogram' => 0.0,
                'trend'     => 'neutral',
            ];
        }

        // Calculate EMAs
        $emaFast = $this->calculateEMA($prices, $fastPeriod);
        $emaSlow = $this->calculateEMA($prices, $slowPeriod);

        // Calculate MACD line
        $macdLine = [];
        for ($i = 0; $i < count($emaFast); $i++) {
            if (isset($emaSlow[$i])) {
                $macdLine[] = $emaFast[$i] - $emaSlow[$i];
            }
        }

        // Calculate signal line (EMA of MACD)
        $signalLine = $this->calculateEMA($macdLine, $signalPeriod);

        // Get latest values
        $currentMacd = end($macdLine);
        $currentSignal = end($signalLine);

        // Handle case where EMA calculation might return false
        if ($currentMacd === false || $currentSignal === false) {
            return [
                'macd'      => 0.0,
                'signal'    => 0.0,
                'histogram' => 0.0,
                'trend'     => 'neutral',
            ];
        }

        $histogram = $currentMacd - $currentSignal;

        return [
            'macd'      => round($currentMacd, 4),
            'signal'    => round($currentSignal, 4),
            'histogram' => round($histogram, 4),
            'trend'     => $this->determineTrend((float) $currentMacd, (float) $currentSignal, $histogram),
        ];
    }

    /**
     * Calculate Exponential Moving Average.
     *
     * @param array<float> $data
     */
    private function calculateEMA(array $data, int $period): array
    {
        if (count($data) < $period) {
            return [];
        }

        $multiplier = 2 / ($period + 1);
        $ema = [];

        // Start with SMA for first value
        $ema[] = array_sum(array_slice($data, 0, $period)) / $period;

        // Calculate EMA for remaining values
        for ($i = $period; $i < count($data); $i++) {
            $ema[] = ($data[$i] * $multiplier) + ($ema[count($ema) - 1] * (1 - $multiplier));
        }

        return $ema;
    }

    /**
     * Determine trend based on MACD values.
     */
    private function determineTrend(float $macd, float $signal, float $histogram): string
    {
        if ($histogram > 0 && $macd > 0) {
            return 'strong_bullish';
        }

        if ($histogram > 0) {
            return 'bullish';
        }

        if ($histogram < 0 && $macd < 0) {
            return 'strong_bearish';
        }

        if ($histogram < 0) {
            return 'bearish';
        }

        return 'neutral';
    }
}
