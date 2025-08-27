<?php

declare(strict_types=1);

namespace App\Domain\AI\ChildWorkflows\Trading;

use App\Domain\AI\Activities\Trading\GenerateMomentumStrategyActivity;
use App\Domain\AI\Events\Trading\StrategyGeneratedEvent;
use Generator;
use Workflow\Workflow;

/**
 * Strategy Generation Child Workflow.
 *
 * Generates trading strategies based on market analysis.
 */
class StrategyGenerationWorkflow extends Workflow
{
    /**
     * Execute strategy generation workflow.
     *
     * @param string $conversationId
     * @param array $marketAnalysis
     *
     * @return Generator
     */
    public function execute(
        string $conversationId,
        array $marketAnalysis
    ): Generator {
        $strategies = [];

        // Generate momentum strategy
        $momentumStrategy = yield app(GenerateMomentumStrategyActivity::class)->execute([
            'rsi'      => $marketAnalysis['indicators']['rsi']['value'] ?? 50,
            'macd'     => $marketAnalysis['indicators']['macd'] ?? [],
            'patterns' => $marketAnalysis['patterns']['patterns'] ?? [],
            'trend'    => $marketAnalysis['sentiment']['overall'] ?? 'neutral',
        ]);

        $strategies['momentum'] = array_merge($momentumStrategy, [
            'type' => 'momentum',
            'name' => 'Momentum Trading Strategy',
        ]);

        // Generate mean reversion strategy
        $meanReversionStrategy = $this->generateMeanReversionStrategy($marketAnalysis);
        $strategies['mean_reversion'] = $meanReversionStrategy;

        // Generate breakout strategy
        $breakoutStrategy = $this->generateBreakoutStrategy($marketAnalysis);
        $strategies['breakout'] = $breakoutStrategy;

        // Select best strategy
        $bestStrategy = $this->selectBestStrategy($strategies, $marketAnalysis);

        // Calculate risk parameters
        $riskParams = $this->calculateRiskParameters($bestStrategy, $marketAnalysis);

        $result = [
            'strategies'      => $strategies,
            'recommended'     => $bestStrategy,
            'risk_parameters' => $riskParams,
            'timestamp'       => now()->toIso8601String(),
        ];

        // Emit strategy generated event
        event(new StrategyGeneratedEvent(
            $conversationId,
            $bestStrategy['type'],
            $result
        ));

        return $result;
    }

    /**
     * Generate mean reversion strategy.
     */
    private function generateMeanReversionStrategy(array $marketAnalysis): array
    {
        $rsi = $marketAnalysis['indicators']['rsi']['value'] ?? 50;
        $sentiment = $marketAnalysis['sentiment']['overall'] ?? 'neutral';

        $action = 'hold';
        $confidence = 0.5;
        $size = 0.1;

        // Mean reversion logic - trade against extremes
        if ($rsi > 70 && str_contains($sentiment, 'bearish')) {
            $action = 'sell';
            $confidence = 0.7;
            $size = 0.15;
        } elseif ($rsi < 30 && str_contains($sentiment, 'bullish')) {
            $action = 'buy';
            $confidence = 0.7;
            $size = 0.15;
        } elseif ($rsi > 60 && $sentiment === 'neutral') {
            $action = 'sell';
            $confidence = 0.6;
            $size = 0.1;
        } elseif ($rsi < 40 && $sentiment === 'neutral') {
            $action = 'buy';
            $confidence = 0.6;
            $size = 0.1;
        }

        return [
            'type'       => 'mean_reversion',
            'name'       => 'Mean Reversion Strategy',
            'action'     => $action,
            'confidence' => $confidence,
            'size'       => $size,
            'reasoning'  => [
                'rsi'       => $rsi,
                'sentiment' => $sentiment,
                'logic'     => 'Trading against extremes expecting price to revert to mean',
            ],
        ];
    }

    /**
     * Generate breakout strategy.
     */
    private function generateBreakoutStrategy(array $marketAnalysis): array
    {
        $patterns = $marketAnalysis['patterns']['patterns'] ?? [];
        $macd = $marketAnalysis['indicators']['macd'] ?? [];
        $sentiment = $marketAnalysis['sentiment']['overall'] ?? 'neutral';

        $action = 'hold';
        $confidence = 0.5;
        $size = 0.1;

        // Look for breakout patterns
        $breakoutPatterns = ['ascending_triangle', 'descending_triangle', 'bullish_flag', 'bearish_flag'];
        $detectedBreakouts = array_intersect($patterns, $breakoutPatterns);

        if (! empty($detectedBreakouts)) {
            if (in_array('ascending_triangle', $detectedBreakouts) || in_array('bullish_flag', $detectedBreakouts)) {
                $action = 'buy';
                $confidence = 0.75;
                $size = 0.2;
            } elseif (in_array('descending_triangle', $detectedBreakouts) || in_array('bearish_flag', $detectedBreakouts)) {
                $action = 'sell';
                $confidence = 0.75;
                $size = 0.2;
            }

            // Confirm with MACD
            if (! empty($macd) && $macd['histogram'] > 0 && $action === 'buy') {
                $confidence += 0.1;
            } elseif (! empty($macd) && $macd['histogram'] < 0 && $action === 'sell') {
                $confidence += 0.1;
            }
        }

        return [
            'type'       => 'breakout',
            'name'       => 'Breakout Trading Strategy',
            'action'     => $action,
            'confidence' => min(1.0, $confidence),
            'size'       => $size,
            'reasoning'  => [
                'patterns'  => $detectedBreakouts,
                'sentiment' => $sentiment,
                'logic'     => 'Trading on price breakouts from consolidation patterns',
            ],
        ];
    }

    /**
     * Select best strategy based on market conditions.
     */
    private function selectBestStrategy(array $strategies, array $marketAnalysis): array
    {
        $bestStrategy = null;
        $highestScore = 0;

        foreach ($strategies as $strategy) {
            $score = $strategy['confidence'] ?? 0;

            // Adjust score based on market conditions
            $sentiment = $marketAnalysis['sentiment']['overall'] ?? 'neutral';

            if ($strategy['type'] === 'momentum' && str_contains($sentiment, 'bullish')) {
                $score *= 1.2;
            } elseif ($strategy['type'] === 'mean_reversion' && $sentiment === 'neutral') {
                $score *= 1.1;
            } elseif ($strategy['type'] === 'breakout' && ! empty($marketAnalysis['patterns']['patterns'])) {
                $score *= 1.15;
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestStrategy = $strategy;
            }
        }

        return $bestStrategy ?? $strategies['momentum'];
    }

    /**
     * Calculate risk parameters for the selected strategy.
     */
    private function calculateRiskParameters(array $strategy, array $marketAnalysis): array
    {
        $baseRisk = 0.02; // 2% base risk
        $confidence = $strategy['confidence'] ?? 0.5;

        // Adjust risk based on confidence
        $riskMultiplier = $confidence > 0.7 ? 1.5 : ($confidence < 0.5 ? 0.5 : 1.0);

        $stopLoss = $baseRisk * (2 - $confidence); // Higher confidence = tighter stop
        $takeProfit = $baseRisk * (1 + $confidence) * 2; // Higher confidence = higher target

        // Adjust for volatility
        $patterns = $marketAnalysis['patterns']['patterns'] ?? [];
        if (in_array('head_and_shoulders', $patterns) || in_array('double_top', $patterns)) {
            $stopLoss *= 1.2; // Wider stop for reversal patterns
        }

        return [
            'stop_loss'         => round($stopLoss, 4),
            'take_profit'       => round($takeProfit, 4),
            'position_size'     => $strategy['size'] ?? 0.1,
            'risk_reward_ratio' => round($takeProfit / $stopLoss, 2),
            'max_drawdown'      => round($stopLoss * 2, 4),
        ];
    }
}
