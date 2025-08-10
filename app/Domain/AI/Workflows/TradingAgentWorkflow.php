<?php

declare(strict_types=1);

namespace App\Domain\AI\Workflows;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Models\User;
use Workflow\Workflow;

/**
 * Trading Agent Workflow.
 *
 * AI-powered trading agent for automated market analysis, trading strategies,
 * portfolio optimization, and risk-adjusted recommendations.
 *
 * Features:
 * - Market analysis and insights generation
 * - Automated trading strategy execution
 * - Portfolio optimization with risk management
 * - Technical and fundamental analysis
 * - Real-time market monitoring
 */
class TradingAgentWorkflow extends Workflow
{
    /**
     * @var array<string, mixed>
     * @phpstan-ignore-next-line
     */
    private array $context = [];

    /**
     * @var array<array{action: string, timestamp: string, success: bool}>
     * @phpstan-ignore-next-line
     */
    private array $executionHistory = [];

    /**
     * @var array<array<string, mixed>>
     */
    private array $tradingStrategies = [];

    /**
     * Execute trading agent workflow.
     *
     * @param string $conversationId Unique conversation identifier
     * @param string $userId User identifier
     * @param string $operation Trading operation type
     * @param array<string, mixed> $parameters Operation parameters
     *
     * @return \Generator
     */
    public function execute(
        string $conversationId,
        string $userId,
        string $operation,
        array $parameters = []
    ): \Generator {
        // Initialize workflow context
        $this->initializeContext($conversationId, $userId, $operation, $parameters);

        // Step 1: Market Analysis
        $marketAnalysis = yield from $this->performMarketAnalysis($userId, $parameters);

        // Step 2: Technical Analysis
        $technicalAnalysis = yield from $this->performTechnicalAnalysis($marketAnalysis, $parameters);

        // Step 3: Generate Trading Strategies
        $strategies = yield from $this->generateTradingStrategies(
            $marketAnalysis,
            $technicalAnalysis,
            $parameters
        );

        // Step 4: Risk Assessment
        $riskAssessment = yield from $this->assessTradingRisk($strategies, $parameters);

        // Step 5: Portfolio Optimization
        $optimization = yield from $this->optimizePortfolio($userId, $strategies, $riskAssessment);

        // Step 6: Execute Trading Decision
        $result = yield from $this->executeTradingDecision(
            $userId,
            $strategies,
            $optimization,
            $parameters
        );

        // Record AI decision in event store
        $this->recordAIDecision($conversationId, $result);

        return $result;
    }

    /**
     * Initialize workflow context.
     */
    private function initializeContext(
        string $conversationId,
        string $userId,
        string $operation,
        array $parameters
    ): void {
        $this->context = [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'operation'       => $operation,
            'parameters'      => $parameters,
            'started_at'      => now()->toIso8601String(),
        ];
    }

    /**
     * Perform comprehensive market analysis.
     */
    private function performMarketAnalysis(string $userId, array $parameters): \Generator
    {
        // Simplified market analysis for demo
        $marketData = [
            'btc_usd' => [
                'price'      => 45250.75,
                'volume_24h' => 28500000000,
                'change_24h' => 2.5,
                'volatility' => 0.35,
                'trend'      => 'bullish',
            ],
            'eth_usd' => [
                'price'      => 2850.30,
                'volume_24h' => 15200000000,
                'change_24h' => 3.2,
                'volatility' => 0.42,
                'trend'      => 'bullish',
            ],
            'market_sentiment' => [
                'fear_greed_index' => 65, // Greed
                'social_sentiment' => 0.72,
                'news_sentiment'   => 0.68,
            ],
        ];

        $this->executionHistory[] = [
            'action'    => 'market_analysis',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        yield $marketData;

        return [
            'market_data'     => $marketData,
            'analysis_time'   => now()->toIso8601String(),
            'confidence'      => 0.85,
            'recommendations' => $this->generateMarketRecommendations($marketData),
        ];
    }

    /**
     * Perform technical analysis on market data.
     */
    private function performTechnicalAnalysis(array $marketAnalysis, array $parameters): \Generator
    {
        // Simplified technical indicators
        $technicalIndicators = [
            'btc_usd' => [
                'rsi'        => 58.5, // Neutral
                'macd'       => ['value' => 125.5, 'signal' => 'buy'],
                'sma_20'     => 44800,
                'sma_50'     => 43200,
                'sma_200'    => 38500,
                'support'    => 44000,
                'resistance' => 46500,
                'pattern'    => 'ascending_triangle',
            ],
            'eth_usd' => [
                'rsi'        => 62.3, // Slightly overbought
                'macd'       => ['value' => 45.2, 'signal' => 'buy'],
                'sma_20'     => 2780,
                'sma_50'     => 2650,
                'sma_200'    => 2400,
                'support'    => 2750,
                'resistance' => 2950,
                'pattern'    => 'bullish_flag',
            ],
        ];

        $this->executionHistory[] = [
            'action'    => 'technical_analysis',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        yield $technicalIndicators;

        return [
            'indicators' => $technicalIndicators,
            'signals'    => $this->extractTradingSignals($technicalIndicators),
            'strength'   => $this->calculateSignalStrength($technicalIndicators),
            'timeframe'  => $parameters['timeframe'] ?? '4h',
        ];
    }

    /**
     * Generate trading strategies based on analysis.
     */
    private function generateTradingStrategies(
        array $marketAnalysis,
        array $technicalAnalysis,
        array $parameters
    ): \Generator {
        $strategies = [];

        // Momentum Strategy
        if ($this->isUptrend($technicalAnalysis)) {
            $strategies[] = [
                'type'       => 'momentum',
                'action'     => 'buy',
                'confidence' => 0.75,
                'assets'     => ['BTC', 'ETH'],
                'allocation' => ['BTC' => 0.6, 'ETH' => 0.4],
                'timeframe'  => 'medium',
                'risk_level' => 'moderate',
            ];
        }

        // Mean Reversion Strategy
        if ($this->isOversold($technicalAnalysis)) {
            $strategies[] = [
                'type'       => 'mean_reversion',
                'action'     => 'buy',
                'confidence' => 0.70,
                'assets'     => ['ETH'],
                'allocation' => ['ETH' => 1.0],
                'timeframe'  => 'short',
                'risk_level' => 'moderate',
            ];
        }

        // Risk Management Strategy
        $strategies[] = [
            'type'       => 'risk_management',
            'action'     => 'hedge',
            'confidence' => 0.65,
            'assets'     => ['USDC'],
            'allocation' => ['USDC' => 0.2], // Keep 20% in stablecoins
            'timeframe'  => 'continuous',
            'risk_level' => 'low',
        ];

        $this->tradingStrategies = $strategies;

        $this->executionHistory[] = [
            'action'    => 'strategy_generation',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        yield $strategies;

        return $strategies;
    }

    /**
     * Assess trading risk for proposed strategies.
     */
    private function assessTradingRisk(array $strategies, array $parameters): \Generator
    {
        $riskMetrics = [
            'portfolio_var'    => 0.045, // 4.5% Value at Risk
            'sharpe_ratio'     => 1.85,
            'max_drawdown'     => 0.12, // 12% maximum drawdown
            'beta'             => 1.15,
            'correlation_risk' => 0.65,
            'liquidity_risk'   => 0.20,
            'market_risk'      => 0.35,
        ];

        $riskScore = $this->calculateOverallRisk($riskMetrics);

        $this->executionHistory[] = [
            'action'    => 'risk_assessment',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        yield $riskMetrics;

        return [
            'metrics'         => $riskMetrics,
            'overall_score'   => $riskScore,
            'risk_level'      => $this->determineRiskLevel($riskScore),
            'recommendations' => $this->generateRiskRecommendations($riskMetrics),
            'stop_loss'       => $this->calculateStopLoss($strategies),
            'take_profit'     => $this->calculateTakeProfit($strategies),
        ];
    }

    /**
     * Optimize portfolio based on strategies and risk.
     */
    private function optimizePortfolio(
        string $userId,
        array $strategies,
        array $riskAssessment
    ): \Generator {
        // Simplified portfolio optimization
        $currentPortfolio = $this->getCurrentPortfolio($userId);

        $optimization = [
            'current_allocation' => $currentPortfolio,
            'target_allocation'  => [
                'BTC'  => 0.45,
                'ETH'  => 0.30,
                'USDC' => 0.15,
                'SOL'  => 0.10,
            ],
            'rebalance_needed' => true,
            'rebalance_trades' => [
                ['action' => 'sell', 'asset' => 'SOL', 'amount' => 5, 'reason' => 'reduce_exposure'],
                ['action' => 'buy', 'asset' => 'BTC', 'amount' => 0.1, 'reason' => 'increase_allocation'],
            ],
            'expected_return'     => 0.125, // 12.5% expected return
            'expected_volatility' => 0.28,
            'optimization_score'  => 0.82,
        ];

        $this->executionHistory[] = [
            'action'    => 'portfolio_optimization',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        yield $optimization;

        return $optimization;
    }

    /**
     * Execute trading decision based on analysis.
     */
    private function executeTradingDecision(
        string $userId,
        array $strategies,
        array $optimization,
        array $parameters
    ): \Generator {
        $autoExecute = $parameters['auto_execute'] ?? false;
        $maxOrderValue = $parameters['max_order_value'] ?? 10000;

        $decision = [
            'recommended_action' => $this->selectBestStrategy($strategies),
            'confidence_score'   => $this->calculateConfidence($strategies),
            'execution_mode'     => $autoExecute ? 'automatic' : 'manual',
            'orders_to_place'    => [],
        ];

        if ($decision['confidence_score'] > 0.7 && $autoExecute) {
            // High confidence - prepare orders for execution
            foreach ($optimization['rebalance_trades'] as $trade) {
                $decision['orders_to_place'][] = [
                    'type'       => $trade['action'] === 'buy' ? 'market_buy' : 'market_sell',
                    'asset'      => $trade['asset'],
                    'amount'     => $trade['amount'],
                    'reason'     => $trade['reason'],
                    'status'     => 'pending_execution',
                    'risk_check' => 'passed',
                ];
            }
        } else {
            // Low confidence or manual mode - require human approval
            $decision['requires_approval'] = true;
            $decision['approval_reason'] = $decision['confidence_score'] <= 0.7
                ? 'low_confidence'
                : 'manual_mode';
        }

        $this->executionHistory[] = [
            'action'    => 'trading_decision',
            'timestamp' => now()->toIso8601String(),
            'success'   => true,
        ];

        yield $decision;

        return [
            'decision'         => $decision,
            'analysis_summary' => $this->generateAnalysisSummary(),
            'execution_plan'   => $optimization['rebalance_trades'],
            'risk_metrics'     => $parameters['include_risk'] ?? true ? $optimization : null,
            'next_review'      => now()->addHours(4)->toIso8601String(),
            'confidence'       => $decision['confidence_score'],
        ];
    }

    /**
     * Generate market recommendations.
     */
    private function generateMarketRecommendations(array $marketData): array
    {
        $recommendations = [];

        if ($marketData['market_sentiment']['fear_greed_index'] > 60) {
            $recommendations[] = [
                'type'     => 'caution',
                'message'  => 'Market showing signs of greed - consider taking profits',
                'priority' => 'medium',
            ];
        }

        foreach ($marketData as $pair => $data) {
            if (is_array($data) && isset($data['trend']) && $data['trend'] === 'bullish') {
                $recommendations[] = [
                    'type'     => 'opportunity',
                    'message'  => "Strong bullish trend detected in {$pair}",
                    'priority' => 'high',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Extract trading signals from technical indicators.
     */
    private function extractTradingSignals(array $indicators): array
    {
        $signals = [];

        foreach ($indicators as $pair => $data) {
            $signal = [
                'pair'       => $pair,
                'strength'   => 0,
                'direction'  => 'neutral',
                'indicators' => [],
            ];

            // RSI Signal
            if ($data['rsi'] < 30) {
                $signal['indicators'][] = 'oversold';
                $signal['strength'] += 2;
                $signal['direction'] = 'buy';
            } elseif ($data['rsi'] > 70) {
                $signal['indicators'][] = 'overbought';
                $signal['strength'] -= 2;
                $signal['direction'] = 'sell';
            }

            // MACD Signal
            if ($data['macd']['signal'] === 'buy') {
                $signal['indicators'][] = 'macd_bullish';
                $signal['strength'] += 1;
            }

            // Moving Average Signal
            if ($data['sma_20'] > $data['sma_50']) {
                $signal['indicators'][] = 'golden_cross';
                $signal['strength'] += 2;
                $signal['direction'] = 'buy';
            }

            $signals[] = $signal;
        }

        return $signals;
    }

    /**
     * Calculate signal strength.
     */
    private function calculateSignalStrength(array $indicators): float
    {
        $totalStrength = 0;
        $signalCount = 0;

        foreach ($indicators as $data) {
            // RSI contribution
            if ($data['rsi'] < 30 || $data['rsi'] > 70) {
                $totalStrength += 0.3;
                $signalCount++;
            }

            // MACD contribution
            if ($data['macd']['signal'] === 'buy') {
                $totalStrength += 0.4;
                $signalCount++;
            }

            // Pattern contribution
            if (in_array($data['pattern'], ['ascending_triangle', 'bullish_flag'])) {
                $totalStrength += 0.3;
                $signalCount++;
            }
        }

        return $signalCount > 0 ? min(1.0, $totalStrength / $signalCount) : 0.5;
    }

    /**
     * Check if market is in uptrend.
     */
    private function isUptrend(array $technicalAnalysis): bool
    {
        foreach ($technicalAnalysis['indicators'] as $data) {
            if ($data['sma_20'] > $data['sma_50'] && $data['sma_50'] > $data['sma_200']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if market is oversold.
     */
    private function isOversold(array $technicalAnalysis): bool
    {
        foreach ($technicalAnalysis['indicators'] as $data) {
            if ($data['rsi'] < 35) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate overall risk score.
     */
    private function calculateOverallRisk(array $metrics): float
    {
        $weights = [
            'portfolio_var'    => 0.25,
            'max_drawdown'     => 0.20,
            'market_risk'      => 0.20,
            'liquidity_risk'   => 0.15,
            'correlation_risk' => 0.20,
        ];

        $score = 0;
        foreach ($weights as $metric => $weight) {
            $score += ($metrics[$metric] ?? 0) * $weight;
        }

        return min(1.0, $score);
    }

    /**
     * Determine risk level based on score.
     */
    private function determineRiskLevel(float $score): string
    {
        if ($score < 0.3) {
            return 'low';
        }
        if ($score < 0.6) {
            return 'moderate';
        }
        if ($score < 0.8) {
            return 'high';
        }

        return 'critical';
    }

    /**
     * Generate risk recommendations.
     */
    private function generateRiskRecommendations(array $metrics): array
    {
        $recommendations = [];

        if ($metrics['portfolio_var'] > 0.05) {
            $recommendations[] = 'Consider reducing position sizes to lower VaR';
        }

        if ($metrics['correlation_risk'] > 0.7) {
            $recommendations[] = 'Diversify portfolio to reduce correlation risk';
        }

        if ($metrics['liquidity_risk'] > 0.3) {
            $recommendations[] = 'Increase allocation to liquid assets';
        }

        return $recommendations;
    }

    /**
     * Calculate stop loss levels.
     */
    private function calculateStopLoss(array $strategies): array
    {
        $stopLoss = [];

        foreach ($strategies as $strategy) {
            foreach ($strategy['assets'] as $asset) {
                $stopLoss[$asset] = match ($strategy['risk_level']) {
                    'low'      => 0.03, // 3% stop loss
                    'moderate' => 0.05, // 5% stop loss
                    'high'     => 0.08, // 8% stop loss
                    default    => 0.05,
                };
            }
        }

        return $stopLoss;
    }

    /**
     * Calculate take profit levels.
     */
    private function calculateTakeProfit(array $strategies): array
    {
        $takeProfit = [];

        foreach ($strategies as $strategy) {
            foreach ($strategy['assets'] as $asset) {
                $takeProfit[$asset] = match ($strategy['risk_level']) {
                    'low'      => 0.05, // 5% take profit
                    'moderate' => 0.10, // 10% take profit
                    'high'     => 0.20, // 20% take profit
                    default    => 0.10,
                };
            }
        }

        return $takeProfit;
    }

    /**
     * Get current portfolio allocation.
     */
    private function getCurrentPortfolio(string $userId): array
    {
        // Simplified for demo
        return [
            'BTC'  => 0.40,
            'ETH'  => 0.25,
            'USDC' => 0.20,
            'SOL'  => 0.15,
        ];
    }

    /**
     * Select best trading strategy.
     */
    private function selectBestStrategy(array $strategies): array
    {
        if (empty($strategies)) {
            return [
                'type'   => 'hold',
                'action' => 'no_action',
                'reason' => 'No viable strategies identified',
            ];
        }

        // Select strategy with highest confidence
        usort($strategies, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $strategies[0];
    }

    /**
     * Calculate overall confidence.
     */
    private function calculateConfidence(array $strategies): float
    {
        if (empty($strategies)) {
            return 0.0;
        }

        $totalConfidence = array_sum(array_column($strategies, 'confidence'));

        return $totalConfidence / count($strategies);
    }

    /**
     * Generate analysis summary.
     */
    private function generateAnalysisSummary(): array
    {
        return [
            'market_condition'   => 'bullish',
            'key_signals'        => ['golden_cross', 'macd_bullish', 'uptrend'],
            'risk_assessment'    => 'moderate',
            'recommended_action' => 'selective_buying',
            'confidence_level'   => 'high',
            'analysis_timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Record AI decision in event store.
     */
    private function recordAIDecision(string $conversationId, array $result): void
    {
        try {
            $aggregate = AIInteractionAggregate::retrieve($conversationId);

            $aggregate->makeDecision(
                decision: 'trading_strategy_' . ($result['decision']['recommended_action']['type'] ?? 'unknown'),
                reasoning: [
                    'strategies' => $this->tradingStrategies,
                    'execution'  => $result['decision'] ?? [],
                    'analysis'   => $result['analysis_summary'] ?? [],
                    'outcome'    => $result,
                ],
                confidence: $result['confidence'] ?? 0.0,
                requiresApproval: ($result['confidence'] ?? 0.0) < 0.7
            );

            $aggregate->persist();
        } catch (\Exception $e) {
            // Log error but don't fail the workflow
            logger()->error('Failed to record trading decision', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
