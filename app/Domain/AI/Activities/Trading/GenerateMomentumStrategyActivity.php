<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities\Trading;

use Workflow\Activity;

/**
 * Generate Momentum Trading Strategy Activity.
 *
 * Atomic activity for generating momentum-based trading strategies.
 */
class GenerateMomentumStrategyActivity extends Activity
{
    /**
     * Execute momentum strategy generation.
     *
     * @param array{rsi: float, macd: array, patterns: array, trend: string} $input
     *
     * @return array{action: string, confidence: float, size: float, reasoning: array}
     */
    public function execute(array $input): array
    {
        $rsi = $input['rsi'] ?? 50;
        $macd = $input['macd'] ?? [];
        $patterns = $input['patterns'] ?? [];
        $trend = $input['trend'] ?? 'neutral';

        $signals = [];
        $confidence = 0.5;

        // RSI signals
        if ($rsi < 30) {
            $signals[] = ['type' => 'oversold', 'strength' => 0.8];
            $confidence += 0.1;
        } elseif ($rsi > 70) {
            $signals[] = ['type' => 'overbought', 'strength' => 0.8];
            $confidence -= 0.1;
        }

        // MACD signals
        if (! empty($macd)) {
            if ($macd['histogram'] > 0 && $macd['trend'] === 'bullish') {
                $signals[] = ['type' => 'macd_bullish', 'strength' => 0.7];
                $confidence += 0.15;
            } elseif ($macd['histogram'] < 0 && $macd['trend'] === 'bearish') {
                $signals[] = ['type' => 'macd_bearish', 'strength' => 0.7];
                $confidence -= 0.15;
            }
        }

        // Pattern signals
        $bullishPatterns = ['ascending_triangle', 'bullish_flag', 'double_bottom'];
        $bearishPatterns = ['descending_triangle', 'bearish_flag', 'double_top'];

        foreach ($patterns as $pattern) {
            if (in_array($pattern, $bullishPatterns)) {
                $signals[] = ['type' => 'bullish_pattern', 'pattern' => $pattern, 'strength' => 0.6];
                $confidence += 0.1;
            } elseif (in_array($pattern, $bearishPatterns)) {
                $signals[] = ['type' => 'bearish_pattern', 'pattern' => $pattern, 'strength' => 0.6];
                $confidence -= 0.1;
            }
        }

        // Determine action
        $action = $this->determineAction($signals, $confidence);
        $size = $this->calculatePositionSize($confidence, $signals);

        return [
            'action'     => $action,
            'confidence' => max(0, min(1, $confidence)),
            'size'       => $size,
            'reasoning'  => [
                'signals' => $signals,
                'trend'   => $trend,
                'rsi'     => $rsi,
            ],
        ];
    }

    private function determineAction(array $signals, float $confidence): string
    {
        $bullishSignals = array_filter($signals, fn ($s) => str_contains($s['type'], 'bullish') || $s['type'] === 'oversold');
        $bearishSignals = array_filter($signals, fn ($s) => str_contains($s['type'], 'bearish') || $s['type'] === 'overbought');

        if (count($bullishSignals) > count($bearishSignals) && $confidence > 0.6) {
            return 'buy';
        }

        if (count($bearishSignals) > count($bullishSignals) && $confidence < 0.4) {
            return 'sell';
        }

        if ($confidence > 0.5 && $confidence < 0.6) {
            return 'hold';
        }

        return 'wait';
    }

    private function calculatePositionSize(float $confidence, array $signals): float
    {
        $baseSize = 0.1; // 10% base position

        // Adjust based on confidence
        if ($confidence > 0.8) {
            $baseSize = 0.25;
        } elseif ($confidence > 0.7) {
            $baseSize = 0.2;
        } elseif ($confidence > 0.6) {
            $baseSize = 0.15;
        }

        // Adjust based on signal strength
        $avgStrength = array_sum(array_column($signals, 'strength')) / max(1, count($signals));
        $baseSize *= $avgStrength;

        return min(0.3, max(0.05, $baseSize)); // Between 5% and 30%
    }
}
