<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Services;

use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Exchange\ValueObjects\ExchangeRateQuote;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnhancedExchangeRateService extends ExchangeRateService
{
    public function __construct(
        private readonly ExchangeRateProviderRegistry $providerRegistry
    ) {
        // Parent has no constructor
    }

    /**
     * Get rate with fallback to external providers.
     */
    public function getRateWithFallback(string $from, string $to): float
    {
        // First, try to get from database
        try {
            $rate = $this->getRate($from, $to);

            // Check if rate is stale (older than 5 minutes)
            $exchangeRate = ExchangeRate::where('from_asset_code', $from)
                ->where('to_asset_code', $to)
                ->where('is_active', true)
                ->first();

            if ($exchangeRate && $exchangeRate->created_at->diffInMinutes(now()) <= 5) {
                return $rate;
            }
        } catch (Exception $e) {
            Log::debug("No valid rate in database for {$from}/{$to}");
        }

        // Fallback to external providers
        $exchangeRate = $this->fetchAndStoreRate($from, $to);

        return $exchangeRate ? $exchangeRate->rate : throw new Exception('Failed to fetch exchange rate');
    }

    /**
     * Fetch rate from external providers and store it.
     */
    public function fetchAndStoreRate(string $fromAsset, string $toAsset): ?ExchangeRate
    {
        try {
            $rate = $this->providerRegistry->getRate($fromAsset, $toAsset);

            if ($rate === null) {
                return null;
            }

            // Create a quote object from the rate
            $rateValue = (float) $rate->toScale(8, RoundingMode::HALF_UP)->__toString();
            $quote = new ExchangeRateQuote(
                fromCurrency: $fromAsset,
                toCurrency: $toAsset,
                rate: $rateValue,
                bid: $rateValue * 0.999, // 0.1% spread
                ask: $rateValue * 1.001, // 0.1% spread
                provider: 'registry',
                timestamp: now()
            );

            // Store in database
            return $this->storeQuote($quote);
        } catch (Exception $e) {
            Log::error(
                'Failed to fetch exchange rate',
                [
                'from'  => $fromAsset,
                'to'    => $toAsset,
                'error' => $e->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * Fetch rate as float (convenience method).
     */
    public function fetchRateAsFloat(string $from, string $to): float
    {
        $rate = $this->providerRegistry->getRate($from, $to);

        if ($rate === null) {
            throw new Exception("Failed to fetch exchange rate for {$from}/{$to}");
        }

        // Create a quote object from the rate
        $rateValue = (float) $rate->toScale(8, RoundingMode::HALF_UP)->__toString();
        $quote = new ExchangeRateQuote(
            fromCurrency: $from,
            toCurrency: $to,
            rate: $rateValue,
            bid: $rateValue * 0.999, // 0.1% spread
            ask: $rateValue * 1.001, // 0.1% spread
            provider: 'registry',
            timestamp: now()
        );

        // Store in database
        $this->storeQuote($quote);

        return $quote->rate;
    }

    /**
     * Store a quote in the database.
     */
    public function storeQuote(ExchangeRateQuote $quote): ExchangeRate
    {
        return DB::transaction(
            function () use ($quote) {
                // Deactivate old rates
                ExchangeRate::where('from_asset_code', $quote->fromCurrency)
                ->where('to_asset_code', $quote->toCurrency)
                ->where('is_active', true)
                ->update(['is_active' => false]);

                // Create new rate
                return ExchangeRate::create(
                    [
                    'from_asset_code' => $quote->fromCurrency,
                    'to_asset_code'   => $quote->toCurrency,
                    'rate'            => $quote->rate,
                    'bid'             => $quote->bid,
                    'ask'             => $quote->ask,
                    'source'          => $quote->provider,
                    'is_active'       => true,
                    'valid_at'        => now(),
                    'expires_at'      => now()->addMinutes(30),
                    'metadata'        => array_merge(
                        $quote->metadata,
                        [
                        'volume_24h' => $quote->volume24h,
                        'change_24h' => $quote->change24h,
                        'fetched_at' => $quote->timestamp->toISOString(),
                        ]
                    ),
                    ]
                );
            }
        );
    }

    /**
     * Refresh all active exchange rates.
     */
    public function refreshAllRates(): array
    {
        $refreshed = [];
        $failed = [];

        // Get unique currency pairs from active rates
        $pairs = ExchangeRate::where('is_active', true)
            ->select('from_asset_code', 'to_asset_code')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            try {
                $result = $this->fetchAndStoreRate($pair->from_asset_code, $pair->to_asset_code);
                if ($result) {
                    $refreshed[] = "{$pair->from_asset_code}/{$pair->to_asset_code}";
                } else {
                    $failed[] = [
                        'pair'  => "{$pair->from_asset_code}/{$pair->to_asset_code}",
                        'error' => 'Failed to fetch rate',
                    ];
                }
            } catch (Exception $e) {
                $failed[] = [
                    'pair'  => "{$pair->from_asset_code}/{$pair->to_asset_code}",
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'refreshed' => $refreshed,
            'failed'    => $failed,
            'total'     => count($pairs),
        ];
    }

    /**
     * Get rates from all providers for comparison.
     */
    public function compareRates(string $from, string $to): array
    {
        $quotes = $this->providerRegistry->getRatesFromAll($from, $to);

        $comparison = [];
        foreach ($quotes as $provider => $quote) {
            $comparison[$provider] = [
                'rate'              => $quote->rate,
                'bid'               => $quote->bid,
                'ask'               => $quote->ask,
                'spread'            => $quote->getSpread(),
                'spread_percentage' => $quote->getSpreadPercentage(),
                'timestamp'         => $quote->timestamp->toISOString(),
                'age_seconds'       => $quote->getAgeInSeconds(),
            ];
        }

        // Add aggregated rate
        try {
            $aggregated = $this->providerRegistry->getAggregatedRate($from, $to);
            $comparison['aggregated'] = [
                'rate'              => $aggregated->rate,
                'bid'               => $aggregated->bid,
                'ask'               => $aggregated->ask,
                'spread'            => $aggregated->getSpread(),
                'spread_percentage' => $aggregated->getSpreadPercentage(),
                'providers_used'    => $aggregated->metadata['providers'],
            ];
        } catch (Exception $e) {
            Log::debug('Failed to calculate aggregated rate', ['error' => $e->getMessage()]);
        }

        return $comparison;
    }

    /**
     * Get historical rates from database.
     */
    public function getHistoricalRates(
        string $from,
        string $to,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): array {
        return ExchangeRate::where('from_asset_code', $from)
            ->where('to_asset_code', $to)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(
                fn ($rate) => [
                'rate'      => $rate->rate,
                'bid'       => $rate->bid,
                'ask'       => $rate->ask,
                'source'    => $rate->source,
                'timestamp' => $rate->created_at->toISOString(),
                ]
            )
            ->toArray();
    }

    /**
     * Validate a quote against configured thresholds.
     */
    public function validateQuote(ExchangeRateQuote $quote): array
    {
        $warnings = [];

        // Check spread
        if ($quote->getSpreadPercentage() > 1.0) {
            $warnings[] = "High spread: {$quote->getSpreadPercentage()}%";
        }

        // Check age
        if (! $quote->isFresh(60)) {
            $warnings[] = "Stale quote: {$quote->getAgeInSeconds()} seconds old";
        }

        // Check against historical rates if available
        $historical = ExchangeRate::where('from_asset_code', $quote->fromCurrency)
            ->where('to_asset_code', $quote->toCurrency)
            ->where('created_at', '>=', now()->subHours(24))
            ->avg('rate');

        if ($historical) {
            $deviation = abs(($quote->rate - $historical) / $historical) * 100;
            if ($deviation > 10) {
                $warnings[] = "Large deviation from 24h average: {$deviation}%";
            }
        }

        return [
            'valid'    => empty($warnings),
            'warnings' => $warnings,
        ];
    }
}
