<?php

declare(strict_types=1);

namespace App\Domain\Banking\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IntelligentRoutingService
{
    /** @var array<string, array{instant: bool, currencies: string[], min: float, max: float, baseCost: float}> */
    private const RAIL_CONFIG = [
        'FEDNOW' => [
            'instant'    => true,
            'currencies' => ['USD'],
            'min'        => 0.01,
            'max'        => 500000.0,
            'baseCost'   => 0.045,
        ],
        'RTP' => [
            'instant'    => true,
            'currencies' => ['USD'],
            'min'        => 0.01,
            'max'        => 1000000.0,
            'baseCost'   => 0.045,
        ],
        'FEDWIRE' => [
            'instant'    => false,
            'currencies' => ['USD'],
            'min'        => 1.0,
            'max'        => 9999999999.0,
            'baseCost'   => 25.0,
        ],
        'ACH' => [
            'instant'    => false,
            'currencies' => ['USD'],
            'min'        => 0.01,
            'max'        => 25000000.0,
            'baseCost'   => 0.25,
        ],
        'SEPA_INSTANT' => [
            'instant'    => true,
            'currencies' => ['EUR'],
            'min'        => 0.01,
            'max'        => 100000.0,
            'baseCost'   => 0.20,
        ],
        'SEPA' => [
            'instant'    => false,
            'currencies' => ['EUR'],
            'min'        => 0.01,
            'max'        => 999999999.0,
            'baseCost'   => 0.50,
        ],
        'SWIFT' => [
            'instant'    => false,
            'currencies' => ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD'],
            'min'        => 1.0,
            'max'        => 9999999999.0,
            'baseCost'   => 15.0,
        ],
    ];

    /** @var array<string, string[]> */
    private const FAILOVER_CHAINS = [
        'FEDNOW'       => ['RTP', 'FEDWIRE', 'ACH'],
        'RTP'          => ['FEDNOW', 'FEDWIRE', 'ACH'],
        'FEDWIRE'      => ['ACH', 'SWIFT'],
        'ACH'          => ['FEDWIRE', 'SWIFT'],
        'SEPA_INSTANT' => ['SEPA', 'SWIFT'],
        'SEPA'         => ['SWIFT'],
        'SWIFT'        => [],
    ];

    /**
     * Operating hours for rails that are not 24/7.
     * `start` and `end` are UTC hours (0–23). When `overnight` is true the window
     * wraps midnight, i.e. the rail is open from `start` through midnight and again
     * from 00:00 until `end`.
     *
     * @var array<string, array{start: int, end: int, overnight: bool, days: int[]}>
     */
    private const OPERATING_HOURS = [
        // Fedwire: Mon–Fri 09:00–21:00 ET = 14:00–02:00 UTC (overnight window)
        'FEDWIRE' => ['start' => 14, 'end' => 2, 'overnight' => true,  'days' => [1, 2, 3, 4, 5]],
        // ACH: Mon–Fri all day
        'ACH' => ['start' => 0,  'end' => 23, 'overnight' => false, 'days' => [1, 2, 3, 4, 5]],
    ];

    /** Maximum cost used for cost score normalisation */
    private const MAX_COST = 30.0;

    /**
     * Select the optimal payment rail for the given parameters.
     *
     * @return array{recommended_rail: string, score: float, alternatives: array<int, array{rail: string, score: float}>, decision_factors: array<string, mixed>}
     */
    public function selectOptimalRail(
        string $amount,
        string $currency,
        string $country,
        string $urgency = 'normal',
    ): array {
        $amountFloat = (float) $amount;
        $scores = [];

        foreach (array_keys(self::RAIL_CONFIG) as $rail) {
            $config = self::RAIL_CONFIG[$rail];

            // Skip rails that do not support the currency
            if (! in_array($currency, $config['currencies'], true)) {
                continue;
            }

            // Skip rails outside amount limits
            if ($amountFloat < $config['min'] || $amountFloat > $config['max']) {
                continue;
            }

            $successRate = $this->getSuccessRate($rail);
            $latencyMs = $this->getLatencyPercentile($rail, 95);
            $cost = $this->getCostEstimate($rail, $amount, $currency);
            $availabilityScore = $this->isWithinOperatingHours($rail) ? 1.0 : 0.0;

            // Latency score: lower is better, 30 s ceiling
            $latencyScore = max(0.0, 1.0 - ($latencyMs / 30000));

            // Cost score: lower is better
            $costScore = max(0.0, 1.0 - ($cost / self::MAX_COST));

            // Urgency match
            $urgencyMatch = match (true) {
                $urgency === 'instant' && $config['instant']   => 1.0,
                $urgency !== 'instant' && ! $config['instant'] => 1.0,
                default                                        => 0.5,
            };

            $score = ($successRate * 0.30)
                + ($latencyScore * 0.20)
                + ($costScore * 0.25)
                + ($availabilityScore * 0.15)
                + ($urgencyMatch * 0.10);

            $scores[$rail] = [
                'score'         => round($score, 4),
                'success_rate'  => $successRate,
                'latency_ms'    => $latencyMs,
                'cost'          => $cost,
                'available'     => (bool) $availabilityScore,
                'urgency_match' => $urgencyMatch,
            ];
        }

        if (empty($scores)) {
            // Fallback: return SWIFT even if limits exceeded
            $scores['SWIFT'] = [
                'score'         => 0.1,
                'success_rate'  => 0.95,
                'latency_ms'    => 86400000,
                'cost'          => 15.0,
                'available'     => true,
                'urgency_match' => 0.5,
            ];
        }

        uasort($scores, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $rails = array_keys($scores);
        $recommended = $rails[0];
        $recommendedData = $scores[$recommended];

        $alternatives = [];
        foreach (array_slice($rails, 1) as $rail) {
            $alternatives[] = ['rail' => $rail, 'score' => $scores[$rail]['score']];
        }

        $decisionFactors = [
            'amount'         => $amount,
            'currency'       => $currency,
            'country'        => $country,
            'urgency'        => $urgency,
            'success_rate'   => $recommendedData['success_rate'],
            'latency_p95_ms' => $recommendedData['latency_ms'],
            'cost_estimate'  => $recommendedData['cost'],
            'within_hours'   => $recommendedData['available'],
            'urgency_match'  => $recommendedData['urgency_match'],
        ];

        return [
            'recommended_rail' => $recommended,
            'score'            => $recommendedData['score'],
            'alternatives'     => $alternatives,
            'decision_factors' => $decisionFactors,
        ];
    }

    /**
     * Record a transaction outcome for adaptive learning.
     * Uses atomic Cache::add + Cache::increment to avoid TOCTOU races.
     */
    public function recordOutcome(string $rail, bool $success, int $latencyMs, float $cost): void
    {
        $date = now()->format('Y-m-d');
        $ttl = now()->addDays(8);

        // Atomic counters for success tracking
        $totalKey = "routing:total:{$rail}:{$date}";
        $successKey = "routing:success:{$rail}:{$date}";

        Cache::add($totalKey, 0, $ttl);
        Cache::increment($totalKey);

        if ($success) {
            Cache::add($successKey, 0, $ttl);
            Cache::increment($successKey);
        }

        // Store latency sample (capped list)
        $latencyKey = "routing:latency:{$rail}:{$date}";
        $latencySamples = Cache::get($latencyKey, []);

        if (is_array($latencySamples)) {
            $latencySamples[] = $latencyMs;
            // Keep last 1000 samples per day
            if (count($latencySamples) > 1000) {
                $latencySamples = array_slice($latencySamples, -1000);
            }

            Cache::put($latencyKey, $latencySamples, $ttl);
        }

        Log::debug('routing.outcome_recorded', [
            'rail'       => $rail,
            'success'    => $success,
            'latency_ms' => $latencyMs,
            'cost'       => $cost,
            'date'       => $date,
        ]);
    }

    /**
     * Calculate rolling success rate from cache counters.
     */
    public function getSuccessRate(string $rail, int $daysBack = 7): float
    {
        $totalAll = 0;
        $successAll = 0;

        for ($i = 0; $i < $daysBack; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $totalKey = "routing:total:{$rail}:{$date}";
            $successKey = "routing:success:{$rail}:{$date}";

            $total = (int) Cache::get($totalKey, 0);
            $success = (int) Cache::get($successKey, 0);

            $totalAll += $total;
            $successAll += $success;
        }

        if ($totalAll === 0) {
            // No historical data — return a sensible prior based on rail type
            return $this->defaultSuccessRate($rail);
        }

        return min(1.0, max(0.0, $successAll / $totalAll));
    }

    /**
     * Approximate latency percentile from stored samples.
     */
    public function getLatencyPercentile(string $rail, int $percentile = 95): int
    {
        $samples = [];

        for ($i = 0; $i < 7; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $latencyKey = "routing:latency:{$rail}:{$date}";
            $daily = Cache::get($latencyKey, []);

            if (is_array($daily)) {
                $samples = array_merge($samples, $daily);
            }
        }

        if (empty($samples)) {
            return $this->defaultLatencyMs($rail);
        }

        sort($samples);
        $index = (int) ceil(($percentile / 100) * count($samples)) - 1;
        $index = max(0, min($index, count($samples) - 1));

        return (int) $samples[$index];
    }

    /**
     * Estimate the fee for a given rail, amount, and currency.
     */
    public function getCostEstimate(string $rail, string $amount, string $currency): float
    {
        $config = self::RAIL_CONFIG[$rail] ?? null;
        $amountFloat = (float) $amount;

        if ($config === null) {
            return self::MAX_COST;
        }

        $base = $config['baseCost'];

        // Percentage-based surcharge for large SWIFT / Fedwire transfers
        if (in_array($rail, ['SWIFT', 'FEDWIRE'], true) && $amountFloat > 10000) {
            $base += $amountFloat * 0.0002; // 0.02% surcharge
        }

        return round($base, 4);
    }

    /**
     * Return an ordered list of fallback rails if the primary fails.
     *
     * @return string[]
     */
    public function getFailoverChain(string $primaryRail): array
    {
        return self::FAILOVER_CHAINS[$primaryRail] ?? [];
    }

    /**
     * Determine whether a rail is currently within its operating window.
     */
    public function isWithinOperatingHours(string $rail): bool
    {
        // 24/7 rails have no entry in OPERATING_HOURS
        if (! isset(self::OPERATING_HOURS[$rail])) {
            return true;
        }

        $hours = self::OPERATING_HOURS[$rail];
        $now = now()->utc();
        $dayOfWeek = (int) $now->format('N'); // 1 = Monday … 7 = Sunday
        $hour = (int) $now->format('G');

        if (! in_array($dayOfWeek, $hours['days'], true)) {
            return false;
        }

        if ($hours['overnight']) {
            // Window wraps midnight — open from `start` onwards OR before `end`
            return $hour >= $hours['start'] || $hour < $hours['end'];
        }

        // Standard same-day window
        return $hour >= $hours['start'] && $hour < $hours['end'];
    }

    /**
     * Write a structured audit-log entry for a routing decision.
     *
     * @param array<string, mixed> $factors
     */
    public function logDecision(
        string $rail,
        float $score,
        array $factors,
        ?string $transactionId = null,
    ): void {
        Log::info('routing.decision', [
            'rail'           => $rail,
            'score'          => $score,
            'factors'        => $factors,
            'transaction_id' => $transactionId,
            'timestamp'      => now()->toIso8601String(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Default success rate prior when no historical data is available.
     */
    private function defaultSuccessRate(string $rail): float
    {
        return match ($rail) {
            'FEDNOW', 'RTP' => 0.995,
            'FEDWIRE'       => 0.990,
            'SEPA_INSTANT'  => 0.993,
            'SEPA'          => 0.985,
            'ACH'           => 0.975,
            'SWIFT'         => 0.960,
            default         => 0.950,
        };
    }

    /**
     * Default p95 latency (ms) prior when no historical data is available.
     */
    private function defaultLatencyMs(string $rail): int
    {
        return match ($rail) {
            'FEDNOW', 'RTP' => 5000,
            'FEDWIRE'       => 3600000,    // ~1 h
            'SEPA_INSTANT'  => 10000,
            'SEPA'          => 86400000,   // ~1 day
            'ACH'           => 172800000,  // ~2 days
            'SWIFT'         => 259200000,  // ~3 days
            default         => 86400000,
        };
    }
}
