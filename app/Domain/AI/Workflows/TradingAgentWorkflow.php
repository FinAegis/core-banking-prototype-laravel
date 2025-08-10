<?php

declare(strict_types=1);

namespace App\Domain\AI\Workflows;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\ChildWorkflows\Trading\MarketAnalysisWorkflow;
use App\Domain\AI\ChildWorkflows\Trading\StrategyGenerationWorkflow;
use App\Domain\AI\Sagas\TradingExecutionSaga;
use Workflow\Workflow;

/**
 * Trading Agent Workflow.
 *
 * Orchestrates AI-powered trading operations using child workflows and sagas.
 * Refactored to follow single responsibility principle with focused components.
 *
 * Components:
 * - MarketAnalysisWorkflow: Technical analysis and pattern identification
 * - StrategyGenerationWorkflow: Trading strategy creation
 * - TradingExecutionSaga: Order execution with compensation
 */
class TradingAgentWorkflow extends Workflow
{
    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    /**
     * Execute trading agent workflow.
     *
     * Orchestrates market analysis, strategy generation, and trade execution
     * using child workflows and sagas for clean separation of concerns.
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
        // Initialize context
        $this->context = [
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'operation'       => $operation,
            'parameters'      => $parameters,
            'started_at'      => now()->toIso8601String(),
        ];

        // Record workflow started
        $aggregate = AIInteractionAggregate::retrieve($conversationId);
        $aggregate->makeDecision(
            decision: 'workflow_started',
            reasoning: ['workflow' => 'trading_agent', 'context' => $this->context],
            confidence: 1.0
        );
        $aggregate->persist();

        try {
            // Step 1: Market Analysis (Child Workflow)
            $marketData = $this->prepareMarketData($parameters);
            $marketAnalysis = yield from app(MarketAnalysisWorkflow::class)->execute(
                $conversationId,
                $parameters['symbol'] ?? 'BTC/USD',
                $marketData
            );

            // Step 2: Strategy Generation (Child Workflow)
            $strategies = yield from app(StrategyGenerationWorkflow::class)->execute(
                $conversationId,
                $marketAnalysis
            );

            // Step 3: Execute Trade if confidence is high (Saga)
            $executionResult = null;
            if ($this->shouldExecuteTrade($strategies)) {
                /** @var array{action: string, size: float, symbol: string, risk_parameters: array} $tradeStrategy */
                $tradeStrategy = array_merge(
                    $strategies['recommended'],
                    [
                        'action'          => $strategies['recommended']['action'] ?? 'hold',
                        'size'            => $strategies['recommended']['size'] ?? 0.1,
                        'symbol'          => $parameters['symbol'] ?? 'BTC/USD',
                        'risk_parameters' => $strategies['risk_parameters'] ?? [],
                    ]
                );

                $executionResult = yield from app(TradingExecutionSaga::class)->execute(
                    $conversationId,
                    $userId,
                    $tradeStrategy
                );
            }

            // Compile final result
            $result = $this->compileFinalResult(
                $marketAnalysis,
                $strategies,
                $executionResult
            );

            // Record workflow completed
            $aggregate->makeDecision(
                decision: 'workflow_completed',
                reasoning: ['workflow' => 'trading_agent', 'result' => $result],
                confidence: 1.0
            );
            $aggregate->persist();

            return $result;
        } catch (\Exception $e) {
            // Record workflow failed
            $aggregate->makeDecision(
                decision: 'workflow_failed',
                reasoning: ['workflow' => 'trading_agent', 'error' => $e->getMessage()],
                confidence: 0.0
            );
            $aggregate->persist();

            throw $e;
        }
    }

    /**
     * Prepare market data for analysis.
     *
     * @return array{prices: array, volumes: array, timeframe: string}
     */
    private function prepareMarketData(array $parameters): array
    {
        // Fetch real market data or use provided data
        return [
            'prices'    => $parameters['prices'] ?? $this->generateSimulatedPrices(),
            'volumes'   => $parameters['volumes'] ?? $this->generateSimulatedVolumes(),
            'timeframe' => $parameters['timeframe'] ?? '1h',
        ];
    }

    /**
     * Determine if trade should be executed.
     */
    private function shouldExecuteTrade(array $strategies): bool
    {
        $recommended = $strategies['recommended'] ?? [];
        $confidence = $recommended['confidence'] ?? 0;
        $action = $recommended['action'] ?? 'hold';

        // Execute if confidence is high and action is not hold/wait
        return $confidence >= 0.7 && ! in_array($action, ['hold', 'wait']);
    }

    /**
     * Compile final result from all workflow steps.
     */
    private function compileFinalResult(
        array $marketAnalysis,
        array $strategies,
        ?array $executionResult
    ): array {
        return [
            'success'  => true,
            'analysis' => [
                'market'     => $marketAnalysis,
                'strategies' => $strategies,
            ],
            'recommendation' => $strategies['recommended'] ?? [],
            'execution'      => $executionResult,
            'confidence'     => $strategies['recommended']['confidence'] ?? 0,
            'timestamp'      => now()->toIso8601String(),
        ];
    }

    /**
     * Generate simulated price data.
     */
    private function generateSimulatedPrices(): array
    {
        $prices = [];
        $basePrice = 50000; // Starting price

        for ($i = 0; $i < 100; $i++) {
            // Random walk with slight upward bias
            $change = (mt_rand(-100, 110) / 100) * 0.01;
            $basePrice *= (1 + $change);
            $prices[] = $basePrice;
        }

        return $prices;
    }

    /**
     * Generate simulated volume data.
     */
    private function generateSimulatedVolumes(): array
    {
        $volumes = [];

        for ($i = 0; $i < 100; $i++) {
            $volumes[] = mt_rand(1000000, 5000000); // Random volume
        }

        return $volumes;
    }
}
