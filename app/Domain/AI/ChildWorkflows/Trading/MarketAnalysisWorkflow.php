<?php

declare(strict_types=1);

namespace App\Domain\AI\ChildWorkflows\Trading;

use App\Domain\AI\Activities\Trading\CalculateMACDActivity;
use App\Domain\AI\Activities\Trading\CalculateRSIActivity;
use App\Domain\AI\Activities\Trading\IdentifyPatternsActivity;
use App\Domain\AI\Events\Trading\MarketAnalyzedEvent;
use Workflow\Workflow;

/**
 * Market Analysis Child Workflow.
 *
 * Performs technical analysis and pattern identification for trading decisions.
 */
class MarketAnalysisWorkflow extends Workflow
{
    /**
     * Execute market analysis workflow.
     *
     * @param string $conversationId
     * @param string $symbol
     * @param array{prices: array, volumes: array, timeframe: string} $marketData
     *
     * @return \Generator
     */
    public function execute(
        string $conversationId,
        string $symbol,
        array $marketData
    ): \Generator {
        // Calculate RSI
        $rsiResult = yield app(CalculateRSIActivity::class)->execute([
            'prices' => $marketData['prices'] ?? [],
            'period' => 14,
        ]);

        // Calculate MACD
        $macdResult = yield app(CalculateMACDActivity::class)->execute([
            'prices' => $marketData['prices'] ?? [],
            'fast'   => 12,
            'slow'   => 26,
            'signal' => 9,
        ]);

        // Identify patterns
        $patterns = yield app(IdentifyPatternsActivity::class)->execute([
            'prices'    => $marketData['prices'] ?? [],
            'volumes'   => $marketData['volumes'] ?? [],
            'timeframe' => $marketData['timeframe'] ?? '1h',
        ]);

        // Analyze market sentiment
        $sentiment = $this->analyzeMarketSentiment($rsiResult, $macdResult, $patterns);

        // Compile analysis results
        $analysisResult = [
            'symbol'     => $symbol,
            'indicators' => [
                'rsi'  => $rsiResult,
                'macd' => $macdResult,
            ],
            'patterns'  => $patterns,
            'sentiment' => $sentiment,
            'timestamp' => now()->toIso8601String(),
        ];

        // Emit market analyzed event
        event(new MarketAnalyzedEvent(
            $conversationId,
            $symbol,
            $analysisResult
        ));

        return $analysisResult;
    }

    /**
     * Analyze overall market sentiment.
     */
    private function analyzeMarketSentiment(array $rsi, array $macd, array $patterns): array
    {
        $bullishScore = 0;
        $bearishScore = 0;

        // RSI sentiment
        if ($rsi['signal'] === 'oversold') {
            $bullishScore += 2;
        } elseif ($rsi['signal'] === 'overbought') {
            $bearishScore += 2;
        } elseif ($rsi['signal'] === 'bullish') {
            $bullishScore += 1;
        } elseif ($rsi['signal'] === 'bearish') {
            $bearishScore += 1;
        }

        // MACD sentiment
        if (str_contains($macd['trend'], 'bullish')) {
            $bullishScore += $macd['trend'] === 'strong_bullish' ? 2 : 1;
        } elseif (str_contains($macd['trend'], 'bearish')) {
            $bearishScore += $macd['trend'] === 'strong_bearish' ? 2 : 1;
        }

        // Pattern sentiment
        if ($patterns['recommendation'] === 'strong_buy') {
            $bullishScore += 2;
        } elseif ($patterns['recommendation'] === 'buy') {
            $bullishScore += 1;
        } elseif ($patterns['recommendation'] === 'strong_sell') {
            $bearishScore += 2;
        } elseif ($patterns['recommendation'] === 'sell') {
            $bearishScore += 1;
        }

        // Calculate overall sentiment
        $totalScore = $bullishScore + $bearishScore;
        $sentiment = 'neutral';
        $confidence = 0.5;

        if ($totalScore > 0) {
            if ($bullishScore > $bearishScore) {
                $sentiment = $bullishScore >= 4 ? 'very_bullish' : 'bullish';
                $confidence = $bullishScore / $totalScore;
            } elseif ($bearishScore > $bullishScore) {
                $sentiment = $bearishScore >= 4 ? 'very_bearish' : 'bearish';
                $confidence = $bearishScore / $totalScore;
            }
        }

        return [
            'overall'       => $sentiment,
            'confidence'    => round($confidence, 2),
            'bullish_score' => $bullishScore,
            'bearish_score' => $bearishScore,
        ];
    }
}
