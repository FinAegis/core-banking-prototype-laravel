<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Streaming;

use App\Domain\Account\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Advanced pattern detection engine for compliance monitoring.
 * Uses machine learning-inspired algorithms for real-time pattern detection.
 */
class PatternDetectionEngine
{
    private const MIN_TRANSACTIONS_FOR_ANALYSIS = 3;

    private const CONFIDENCE_THRESHOLD = 0.6;

    // Pattern types
    private const PATTERN_STRUCTURING = 'structuring';

    private const PATTERN_LAYERING = 'layering';

    private const PATTERN_SMURFING = 'smurfing';

    private const PATTERN_RAPID_MOVEMENT = 'rapid_movement';

    private const PATTERN_ROUND_TRIPPING = 'round_tripping';

    private const PATTERN_PUMP_AND_DUMP = 'pump_and_dump';

    private const PATTERN_WASH_TRADING = 'wash_trading';

    /**
     * Analyze transaction stream for patterns.
     */
    public function analyzeStream(array $buffer, Transaction $currentTransaction): array
    {
        $patterns = [];

        if (count($buffer) < self::MIN_TRANSACTIONS_FOR_ANALYSIS) {
            return $patterns;
        }

        // Run pattern detection algorithms
        $detectors = [
            'detectStructuring'   => self::PATTERN_STRUCTURING,
            'detectLayering'      => self::PATTERN_LAYERING,
            'detectSmurfing'      => self::PATTERN_SMURFING,
            'detectRapidMovement' => self::PATTERN_RAPID_MOVEMENT,
            'detectRoundTripping' => self::PATTERN_ROUND_TRIPPING,
            'detectPumpAndDump'   => self::PATTERN_PUMP_AND_DUMP,
            'detectWashTrading'   => self::PATTERN_WASH_TRADING,
        ];

        foreach ($detectors as $method => $patternType) {
            $result = $this->$method($buffer, $currentTransaction);
            if ($result !== null && $result['confidence'] >= self::CONFIDENCE_THRESHOLD) {
                $result['type'] = $patternType;
                $patterns[] = $result;
            }
        }

        // Apply ensemble scoring
        $patterns = $this->applyEnsembleScoring($patterns, $buffer);

        return $patterns;
    }

    /**
     * Analyze batch of transactions for patterns.
     */
    public function analyzeBatch(Collection $transactions): array
    {
        $patterns = [];

        if ($transactions->count() < self::MIN_TRANSACTIONS_FOR_ANALYSIS) {
            return $patterns;
        }

        // Convert to buffer format
        $buffer = $transactions->map(function ($transaction) {
            $createdAt = is_string($transaction->created_at)
                ? \Carbon\Carbon::parse($transaction->created_at)
                : $transaction->created_at;

            return [
                'id'        => $transaction->id,
                'amount'    => $transaction->event_properties['amount'] ?? 0,
                'type'      => $transaction->event_properties['type'] ?? 'unknown',
                'timestamp' => $createdAt->timestamp,
                'metadata'  => $transaction->event_properties['metadata'] ?? [],
            ];
        })->toArray();

        // Network analysis for complex patterns
        $networkPatterns = $this->performNetworkAnalysis($buffer);
        $patterns = array_merge($patterns, $networkPatterns);

        // Time series analysis
        $timeSeriesPatterns = $this->performTimeSeriesAnalysis($buffer);
        $patterns = array_merge($patterns, $timeSeriesPatterns);

        // Statistical anomaly detection
        $anomalies = $this->detectStatisticalAnomalies($buffer);
        $patterns = array_merge($patterns, $anomalies);

        return $patterns;
    }

    /**
     * Detect structuring pattern (avoiding reporting thresholds).
     */
    private function detectStructuring(array $buffer, Transaction $currentTransaction): ?array
    {
        $reportingThreshold = 10000;
        $marginPercentage = 0.15;
        $lowerBound = $reportingThreshold * (1 - $marginPercentage);

        // Count transactions near threshold
        $nearThresholdTransactions = array_filter($buffer, function ($t) use ($lowerBound, $reportingThreshold) {
            return $t['amount'] >= $lowerBound && $t['amount'] < $reportingThreshold;
        });

        if (count($nearThresholdTransactions) < 3) {
            return null;
        }

        // Calculate pattern metrics
        $amounts = array_column($nearThresholdTransactions, 'amount');
        $avgAmount = array_sum($amounts) / count($amounts);
        $variance = $this->calculateVariance($amounts);
        $timeDiffs = $this->calculateTimeDifferences($nearThresholdTransactions);

        // Score the pattern - adjusted for better detection
        // Base score for having multiple near-threshold transactions (we know it's >= 3)
        $confidence = 0.25;

        // Similar amounts (relaxed variance check for realistic scenarios)
        $coefficientOfVariation = $avgAmount > 0 ? sqrt($variance) / $avgAmount : 0;
        if ($coefficientOfVariation < 0.2) { // Within 20% variation
            $confidence += 0.25;
        }

        // Regular intervals or clustered in time
        if ($this->hasRegularIntervals($timeDiffs) || $this->isClusteredInTime($nearThresholdTransactions)) {
            $confidence += 0.2;
        }

        // Multiple transactions bonus (stronger indicator)
        if (count($nearThresholdTransactions) >= 4) {
            $confidence += 0.15;
        }
        if (count($nearThresholdTransactions) >= 5) {
            $confidence += 0.1; // Additional bonus for 5+
        }

        // High concentration of near-threshold transactions
        $percentBelowThreshold = count($nearThresholdTransactions) / count($buffer);
        if ($percentBelowThreshold > 0.6) {
            $confidence += 0.15;
        } elseif ($percentBelowThreshold > 0.4) {
            $confidence += 0.1;
        }

        // Very close to threshold (highly suspicious)
        $veryClose = array_filter($amounts, fn ($a) => $a >= 9000 && $a < 10000);
        if (count($veryClose) >= 3) {
            $confidence += 0.1;
        }

        return [
            'confidence'  => min($confidence, 1.0),
            'description' => 'Potential structuring: Multiple transactions just below reporting threshold',
            'risk_score'  => min($confidence * 80, 100),
            'evidence'    => [
                'transaction_count' => count($nearThresholdTransactions),
                'average_amount'    => $avgAmount,
                'variance'          => $variance,
                'transactions'      => array_column($nearThresholdTransactions, 'id'),
            ],
        ];
    }

    /**
     * Detect layering pattern (complex fund movement).
     */
    private function detectLayering(array $buffer, Transaction $currentTransaction): ?array
    {
        // Look for rapid in/out patterns
        $deposits = array_filter($buffer, fn ($t) => $t['type'] === 'deposit');
        $withdrawals = array_filter($buffer, fn ($t) => $t['type'] === 'withdrawal');
        $transfers = array_filter($buffer, fn ($t) => $t['type'] === 'transfer');

        if (count($deposits) < 2 || count($withdrawals) < 2) {
            return null;
        }

        // Calculate flow metrics
        $totalIn = array_sum(array_column($deposits, 'amount'));
        $totalOut = array_sum(array_column($withdrawals, 'amount'));
        $totalTransfers = array_sum(array_column($transfers, 'amount'));

        $confidence = 0;

        // Balanced in/out (characteristic of layering)
        $flowBalance = abs($totalIn - $totalOut) / max($totalIn, $totalOut);
        if ($flowBalance < 0.1) {
            $confidence += 0.35;
        }

        // High transfer activity
        if ($totalTransfers > ($totalIn * 0.5)) {
            $confidence += 0.25;
        }

        // Multiple counterparties
        $counterparties = $this->extractCounterparties($buffer);
        if (count($counterparties) >= 4) {
            $confidence += 0.2;
        }

        // Complex routing
        if ($this->hasComplexRouting($buffer)) {
            $confidence += 0.2;
        }

        return [
            'confidence'  => $confidence,
            'description' => 'Potential layering: Complex fund movement patterns detected',
            'risk_score'  => $confidence * 85,
            'evidence'    => [
                'total_in'          => $totalIn,
                'total_out'         => $totalOut,
                'flow_balance'      => $flowBalance,
                'counterparties'    => count($counterparties),
                'transaction_types' => [
                    'deposits'    => count($deposits),
                    'withdrawals' => count($withdrawals),
                    'transfers'   => count($transfers),
                ],
            ],
        ];
    }

    /**
     * Detect smurfing pattern (multiple small transactions).
     */
    private function detectSmurfing(array $buffer, Transaction $currentTransaction): ?array
    {
        $smurfingThreshold = 3000;

        // Filter small transactions
        $smallTransactions = array_filter($buffer, fn ($t) => $t['amount'] < $smurfingThreshold && $t['amount'] > 100);

        if (count($smallTransactions) < 5) {
            return null;
        }

        $amounts = array_column($smallTransactions, 'amount');
        $avgAmount = array_sum($amounts) / count($amounts);
        $variance = $this->calculateVariance($amounts);

        $confidence = 0;

        // Many small transactions
        $ratio = count($smallTransactions) / count($buffer);
        if ($ratio > 0.7) {
            $confidence += 0.3;
        }

        // Similar amounts
        if ($variance < ($avgAmount * 0.15)) {
            $confidence += 0.3;
        }

        // Rapid succession
        $timeDiffs = $this->calculateTimeDifferences($smallTransactions);
        if (! empty($timeDiffs) && max($timeDiffs) < 3600) { // All within an hour
            $confidence += 0.2;
        }

        // Total exceeds reporting threshold
        $total = array_sum($amounts);
        if ($total > 10000) {
            $confidence += 0.2;
        }

        return [
            'confidence'  => $confidence,
            'description' => 'Potential smurfing: Multiple small structured transactions',
            'risk_score'  => $confidence * 75,
            'evidence'    => [
                'small_transaction_count' => count($smallTransactions),
                'average_amount'          => $avgAmount,
                'total_amount'            => $total,
                'time_span'               => empty($timeDiffs) ? 0 : array_sum($timeDiffs),
            ],
        ];
    }

    /**
     * Detect rapid movement pattern.
     */
    private function detectRapidMovement(array $buffer, Transaction $currentTransaction): ?array
    {
        // Find deposit-withdrawal pairs
        $deposits = array_filter($buffer, fn ($t) => $t['type'] === 'deposit');
        $withdrawals = array_filter($buffer, fn ($t) => $t['type'] === 'withdrawal');

        if (empty($deposits) || empty($withdrawals)) {
            return null;
        }

        $rapidPairs = [];
        foreach ($deposits as $deposit) {
            foreach ($withdrawals as $withdrawal) {
                if ($withdrawal['timestamp'] > $deposit['timestamp']) {
                    $timeDiff = $withdrawal['timestamp'] - $deposit['timestamp'];
                    if ($timeDiff < 86400) { // Within 24 hours
                        $amountMatch = abs($deposit['amount'] - $withdrawal['amount']) / $deposit['amount'];
                        if ($amountMatch < 0.1) { // Within 10% of deposit
                            $rapidPairs[] = [
                                'deposit'      => $deposit,
                                'withdrawal'   => $withdrawal,
                                'time_diff'    => $timeDiff,
                                'amount_match' => $amountMatch,
                            ];
                        }
                    }
                }
            }
        }

        if (empty($rapidPairs)) {
            return null;
        }

        $confidence = min(0.2 * count($rapidPairs), 0.6);

        // Very rapid movement increases confidence
        $veryRapid = array_filter($rapidPairs, fn ($p) => $p['time_diff'] < 3600);
        if (! empty($veryRapid)) {
            $confidence += 0.3;
        }

        return [
            'confidence'  => $confidence,
            'description' => 'Rapid fund movement detected: Funds deposited and withdrawn quickly',
            'risk_score'  => $confidence * 70,
            'evidence'    => [
                'pair_count'   => count($rapidPairs),
                'average_time' => array_sum(array_column($rapidPairs, 'time_diff')) / count($rapidPairs),
                'pairs'        => array_map(function ($p) {
                    return [
                        'deposit_id'        => $p['deposit']['id'],
                        'withdrawal_id'     => $p['withdrawal']['id'],
                        'time_diff_seconds' => $p['time_diff'],
                    ];
                }, $rapidPairs),
            ],
        ];
    }

    /**
     * Detect round tripping pattern.
     */
    private function detectRoundTripping(array $buffer, Transaction $currentTransaction): ?array
    {
        // Look for circular fund flows
        $transfers = array_filter($buffer, fn ($t) => $t['type'] === 'transfer');

        if (count($transfers) < 3) {
            return null;
        }

        // Build transaction graph
        $graph = $this->buildTransactionGraph($transfers);
        $cycles = $this->findCycles($graph);

        if (empty($cycles)) {
            return null;
        }

        $confidence = min(0.3 * count($cycles), 0.6);

        // Short cycles increase confidence
        $shortCycles = array_filter($cycles, fn ($c) => count($c) <= 4);
        if (! empty($shortCycles)) {
            $confidence += 0.2;
        }

        // Amount preservation in cycles
        foreach ($cycles as $cycle) {
            if ($this->hasAmountPreservation($cycle, $transfers)) {
                $confidence += 0.1;
            }
        }

        return [
            'confidence'  => min($confidence, 1.0),
            'description' => 'Round tripping detected: Circular fund movement pattern',
            'risk_score'  => $confidence * 80,
            'evidence'    => [
                'cycle_count'    => count($cycles),
                'cycles'         => $cycles,
                'transfer_count' => count($transfers),
            ],
        ];
    }

    /**
     * Detect pump and dump pattern (for trading).
     */
    private function detectPumpAndDump(array $buffer, Transaction $currentTransaction): ?array
    {
        // Look for buy accumulation followed by sells
        $buys = array_filter($buffer, fn ($t) => in_array($t['type'], ['buy', 'exchange_buy']));
        $sells = array_filter($buffer, fn ($t) => in_array($t['type'], ['sell', 'exchange_sell']));

        if (count($buys) < 3 || count($sells) < 2) {
            return null;
        }

        // Check temporal ordering
        $lastBuyTime = max(array_column($buys, 'timestamp'));
        $firstSellTime = min(array_column($sells, 'timestamp'));

        if ($firstSellTime < $lastBuyTime) {
            return null; // Sells before accumulation complete
        }

        $confidence = 0;

        // Volume analysis
        $buyVolume = array_sum(array_column($buys, 'amount'));
        $sellVolume = array_sum(array_column($sells, 'amount'));

        if ($sellVolume > ($buyVolume * 0.8)) {
            $confidence += 0.4;
        }

        // Price impact (if available in metadata)
        if ($this->detectPriceImpact($buys, $sells)) {
            $confidence += 0.3;
        }

        // Concentration of activity
        $timeSpan = $firstSellTime - min(array_column($buys, 'timestamp'));
        if ($timeSpan < 86400) { // Within 24 hours
            $confidence += 0.3;
        }

        return [
            'confidence'  => $confidence,
            'description' => 'Potential pump and dump: Accumulation followed by distribution',
            'risk_score'  => $confidence * 90,
            'evidence'    => [
                'buy_count'         => count($buys),
                'sell_count'        => count($sells),
                'buy_volume'        => $buyVolume,
                'sell_volume'       => $sellVolume,
                'time_span_seconds' => $timeSpan,
            ],
        ];
    }

    /**
     * Detect wash trading pattern.
     */
    private function detectWashTrading(array $buffer, Transaction $currentTransaction): ?array
    {
        $trades = array_filter($buffer, fn ($t) => in_array($t['type'], ['buy', 'sell', 'exchange_buy', 'exchange_sell']));

        if (count($trades) < 4) {
            return null;
        }

        // Look for self-dealing indicators
        $confidence = 0;

        // Paired trades (buy/sell at similar times)
        $pairedTrades = $this->findPairedTrades($trades);
        if (count($pairedTrades) >= 2) {
            $confidence += 0.4;
        }

        // No economic purpose (minimal price change)
        if ($this->hasMinimalPriceImpact($trades)) {
            $confidence += 0.3;
        }

        // Regular pattern
        $intervals = $this->calculateTimeDifferences($trades);
        if ($this->hasRegularIntervals($intervals)) {
            $confidence += 0.3;
        }

        return [
            'confidence'  => $confidence,
            'description' => 'Potential wash trading: Artificial trading activity detected',
            'risk_score'  => $confidence * 85,
            'evidence'    => [
                'trade_count'   => count($trades),
                'paired_trades' => count($pairedTrades),
                'trades'        => array_column($trades, 'id'),
            ],
        ];
    }

    /**
     * Perform network analysis on transactions.
     */
    private function performNetworkAnalysis(array $buffer): array
    {
        $patterns = [];

        // Build network graph
        $graph = $this->buildTransactionGraph($buffer);

        // Detect hub nodes (concentration risk)
        $hubNodes = $this->detectHubNodes($graph);
        if (! empty($hubNodes)) {
            $patterns[] = [
                'type'        => 'network_concentration',
                'confidence'  => 0.8,
                'description' => 'High concentration of transactions through specific nodes',
                'risk_score'  => 70,
                'evidence'    => ['hub_nodes' => $hubNodes],
            ];
        }

        // Detect isolated subgraphs (compartmentalization)
        $subgraphs = $this->findIsolatedSubgraphs($graph);
        if (count($subgraphs) > 1) {
            $patterns[] = [
                'type'        => 'compartmentalization',
                'confidence'  => 0.7,
                'description' => 'Isolated transaction groups detected',
                'risk_score'  => 60,
                'evidence'    => ['subgraph_count' => count($subgraphs)],
            ];
        }

        return $patterns;
    }

    /**
     * Perform time series analysis on transactions.
     */
    private function performTimeSeriesAnalysis(array $buffer): array
    {
        $patterns = [];

        // Sort by timestamp
        usort($buffer, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        // Detect periodicity
        if ($this->hasPeriodicPattern($buffer)) {
            $patterns[] = [
                'type'        => 'periodic_activity',
                'confidence'  => 0.75,
                'description' => 'Regular periodic transaction pattern detected',
                'risk_score'  => 55,
                'evidence'    => ['period_detected' => true],
            ];
        }

        // Detect bursts
        $bursts = $this->detectActivityBursts($buffer);
        if (! empty($bursts)) {
            $patterns[] = [
                'type'        => 'activity_burst',
                'confidence'  => 0.8,
                'description' => 'Unusual burst of activity detected',
                'risk_score'  => 65,
                'evidence'    => ['burst_count' => count($bursts)],
            ];
        }

        return $patterns;
    }

    /**
     * Detect statistical anomalies in transactions.
     */
    private function detectStatisticalAnomalies(array $buffer): array
    {
        $patterns = [];
        $amounts = array_column($buffer, 'amount');

        if (count($amounts) < 5) {
            return $patterns;
        }

        // Calculate statistics
        $mean = array_sum($amounts) / count($amounts);
        $stdDev = $this->calculateStandardDeviation($amounts);

        // Find outliers (3-sigma rule)
        // Find outliers (3-sigma rule)
        $outliers = [];
        if ($stdDev > 0) { // Prevent division by zero
            foreach ($buffer as $transaction) {
                $zScore = abs(($transaction['amount'] - $mean) / $stdDev);
                if ($zScore > 3) {
                    $outliers[] = $transaction;
                }
            }
        }

        if (! empty($outliers)) {
            $patterns[] = [
                'type'        => 'statistical_anomaly',
                'confidence'  => min(0.6 + (0.1 * count($outliers)), 0.9),
                'description' => 'Statistical outliers detected in transaction amounts',
                'risk_score'  => 60,
                'evidence'    => [
                    'outlier_count' => count($outliers),
                    'outliers'      => array_column($outliers, 'id'),
                    'mean'          => $mean,
                    'std_dev'       => $stdDev,
                ],
            ];
        }

        // Detect distribution anomalies
        if ($this->hasAbnormalDistribution($amounts)) {
            $patterns[] = [
                'type'        => 'distribution_anomaly',
                'confidence'  => 0.7,
                'description' => 'Abnormal distribution of transaction amounts',
                'risk_score'  => 50,
                'evidence'    => ['distribution_type' => 'non_normal'],
            ];
        }

        return $patterns;
    }

    /**
     * Apply ensemble scoring to detected patterns.
     */
    private function applyEnsembleScoring(array $patterns, array $buffer): array
    {
        if (count($patterns) <= 1) {
            return $patterns;
        }

        // Boost confidence when multiple patterns detected
        $patternCount = count($patterns);
        $boost = min(0.1 * ($patternCount - 1), 0.3);

        foreach ($patterns as &$pattern) {
            $pattern['confidence'] = min($pattern['confidence'] + $boost, 1.0);
            $pattern['ensemble_boost'] = $boost;
            $pattern['correlated_patterns'] = count($patterns) - 1;
        }

        return $patterns;
    }

    // Utility methods

    private function calculateVariance(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn ($v) => pow($v - $mean, 2), $values);

        return array_sum($squaredDiffs) / count($values);
    }

    private function calculateStandardDeviation(array $values): float
    {
        return sqrt($this->calculateVariance($values));
    }

    private function calculateTimeDifferences(array $transactions): array
    {
        if (count($transactions) < 2) {
            return [];
        }

        usort($transactions, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        $diffs = [];
        for ($i = 1; $i < count($transactions); $i++) {
            $diffs[] = $transactions[$i]['timestamp'] - $transactions[$i - 1]['timestamp'];
        }

        return $diffs;
    }

    private function hasRegularIntervals(array $intervals): bool
    {
        if (count($intervals) < 2) {
            return false;
        }

        $variance = $this->calculateVariance($intervals);
        $mean = array_sum($intervals) / count($intervals);

        // Regular if variance is less than 20% of mean
        return $mean > 0 && ($variance / $mean) < 0.2;
    }

    private function isClusteredInTime(array $transactions): bool
    {
        if (count($transactions) < 3) {
            return false;
        }

        // Sort by timestamp
        usort($transactions, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        // Get time span
        $firstTime = $transactions[0]['timestamp'];
        $lastTime = $transactions[count($transactions) - 1]['timestamp'];
        $timeSpan = $lastTime - $firstTime;

        // Clustered if all transactions within 24 hours
        if ($timeSpan <= 86400) { // 24 hours
            return true;
        }

        // Or if average interval is less than 6 hours
        $avgInterval = $timeSpan / (count($transactions) - 1);

        return $avgInterval <= 21600; // 6 hours
    }

    private function extractCounterparties(array $buffer): array
    {
        $counterparties = [];

        foreach ($buffer as $transaction) {
            if (isset($transaction['metadata']['counterparty'])) {
                $counterparties[] = $transaction['metadata']['counterparty'];
            }
            if (isset($transaction['metadata']['destination_account'])) {
                $counterparties[] = $transaction['metadata']['destination_account'];
            }
        }

        return array_unique($counterparties);
    }

    private function hasComplexRouting(array $buffer): bool
    {
        $routingPaths = [];

        foreach ($buffer as $transaction) {
            if (isset($transaction['metadata']['routing_path'])) {
                $routingPaths[] = $transaction['metadata']['routing_path'];
            }
        }

        // Complex if multiple different routing paths
        return count(array_unique($routingPaths)) >= 3;
    }

    private function buildTransactionGraph(array $transactions): array
    {
        $graph = [];

        foreach ($transactions as $transaction) {
            $from = $transaction['metadata']['from'] ?? 'self';
            $to = $transaction['metadata']['to'] ?? 'self';

            if (! isset($graph[$from])) {
                $graph[$from] = [];
            }

            $graph[$from][] = [
                'to'     => $to,
                'amount' => $transaction['amount'],
                'id'     => $transaction['id'],
            ];
        }

        return $graph;
    }

    private function findCycles(array $graph): array
    {
        // Simplified cycle detection
        $cycles = [];

        foreach ($graph as $node => $edges) {
            $visited = [];
            $path = [];
            $this->dfs($node, $graph, $visited, $path, $cycles);
        }

        return $cycles;
    }

    private function dfs($node, $graph, &$visited, &$path, &$cycles): void
    {
        if (in_array($node, $path)) {
            // Found cycle
            $cycleStart = array_search($node, $path);
            $cycles[] = array_slice($path, $cycleStart);

            return;
        }

        if (in_array($node, $visited)) {
            return;
        }

        $visited[] = $node;
        $path[] = $node;

        if (isset($graph[$node])) {
            foreach ($graph[$node] as $edge) {
                $this->dfs($edge['to'], $graph, $visited, $path, $cycles);
            }
        }

        array_pop($path);
    }

    private function hasAmountPreservation(array $cycle, array $transactions): bool
    {
        // Check if amounts are preserved through the cycle
        $amounts = [];

        foreach ($cycle as $node) {
            foreach ($transactions as $t) {
                if (isset($t['metadata']['from']) && $t['metadata']['from'] === $node) {
                    $amounts[] = $t['amount'];
                }
            }
        }

        if (empty($amounts)) {
            return false;
        }

        $variance = $this->calculateVariance($amounts);
        $mean = array_sum($amounts) / count($amounts);

        return $mean > 0 && ($variance / $mean) < 0.1;
    }

    private function detectPriceImpact(array $buys, array $sells): bool
    {
        // Check if price increased during buys and decreased during sells
        $buyPrices = [];
        $sellPrices = [];

        foreach ($buys as $buy) {
            if (isset($buy['metadata']['price'])) {
                $buyPrices[] = $buy['metadata']['price'];
            }
        }

        foreach ($sells as $sell) {
            if (isset($sell['metadata']['price'])) {
                $sellPrices[] = $sell['metadata']['price'];
            }
        }

        if (empty($buyPrices) || empty($sellPrices)) {
            return false;
        }

        // Check if average sell price > average buy price
        $avgBuyPrice = array_sum($buyPrices) / count($buyPrices);
        $avgSellPrice = array_sum($sellPrices) / count($sellPrices);

        return $avgSellPrice > ($avgBuyPrice * 1.1); // 10% profit
    }

    private function findPairedTrades(array $trades): array
    {
        $pairs = [];

        for ($i = 0; $i < count($trades) - 1; $i++) {
            for ($j = $i + 1; $j < count($trades); $j++) {
                $timeDiff = abs($trades[$i]['timestamp'] - $trades[$j]['timestamp']);
                if ($timeDiff < 300) { // Within 5 minutes
                    if ($trades[$i]['type'] !== $trades[$j]['type']) { // Opposite types
                        $pairs[] = [$trades[$i], $trades[$j]];
                    }
                }
            }
        }

        return $pairs;
    }

    private function hasMinimalPriceImpact(array $trades): bool
    {
        $prices = [];

        foreach ($trades as $trade) {
            if (isset($trade['metadata']['price'])) {
                $prices[] = $trade['metadata']['price'];
            }
        }

        if (count($prices) < 2) {
            return false;
        }

        $variance = $this->calculateVariance($prices);
        $mean = array_sum($prices) / count($prices);

        // Minimal impact if variance is less than 2% of mean
        return $mean > 0 && ($variance / $mean) < 0.02;
    }

    private function detectHubNodes(array $graph): array
    {
        $nodeDegrees = [];

        foreach ($graph as $node => $edges) {
            $nodeDegrees[$node] = count($edges);
        }

        // Find nodes with high degree
        $avgDegree = array_sum($nodeDegrees) / count($nodeDegrees);
        $hubs = [];

        foreach ($nodeDegrees as $node => $degree) {
            if ($degree > ($avgDegree * 3)) { // 3x average
                $hubs[] = ['node' => $node, 'degree' => $degree];
            }
        }

        return $hubs;
    }

    private function findIsolatedSubgraphs(array $graph): array
    {
        // Simplified subgraph detection
        $visited = [];
        $subgraphs = [];

        foreach ($graph as $node => $edges) {
            if (! in_array($node, $visited)) {
                $subgraph = [];
                $this->exploreSubgraph($node, $graph, $visited, $subgraph);
                $subgraphs[] = $subgraph;
            }
        }

        return $subgraphs;
    }

    private function exploreSubgraph($node, $graph, &$visited, &$subgraph): void
    {
        if (in_array($node, $visited)) {
            return;
        }

        $visited[] = $node;
        $subgraph[] = $node;

        if (isset($graph[$node])) {
            foreach ($graph[$node] as $edge) {
                $this->exploreSubgraph($edge['to'], $graph, $visited, $subgraph);
            }
        }
    }

    private function hasPeriodicPattern(array $buffer): bool
    {
        if (count($buffer) < 10) {
            return false;
        }

        $intervals = $this->calculateTimeDifferences($buffer);

        // Use FFT or autocorrelation for real implementation
        // Simplified: check if intervals are regular
        return $this->hasRegularIntervals($intervals);
    }

    private function detectActivityBursts(array $buffer): array
    {
        $bursts = [];
        $windowSize = 3600; // 1 hour windows

        // Group by time windows
        $windows = [];
        foreach ($buffer as $transaction) {
            $window = floor($transaction['timestamp'] / $windowSize);
            if (! isset($windows[$window])) {
                $windows[$window] = [];
            }
            $windows[$window][] = $transaction;
        }

        // Find windows with unusually high activity
        $counts = array_map('count', $windows);
        $avgCount = array_sum($counts) / count($counts);

        foreach ($windows as $window => $transactions) {
            if (count($transactions) > ($avgCount * 3)) {
                $bursts[] = [
                    'window'            => $window * $windowSize,
                    'transaction_count' => count($transactions),
                    'transactions'      => array_column($transactions, 'id'),
                ];
            }
        }

        return $bursts;
    }

    private function hasAbnormalDistribution(array $amounts): bool
    {
        // Simplified normality test
        // Real implementation would use Shapiro-Wilk or Kolmogorov-Smirnov

        $mean = array_sum($amounts) / count($amounts);
        $median = $this->calculateMedian($amounts);

        // Check skewness (simplified)
        $skewness = abs($mean - $median) / max($mean, $median);

        return $skewness > 0.3; // Significantly skewed
    }

    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        }

        return $values[(int) floor($count / 2)];
    }
}
