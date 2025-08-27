<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Providers;

use App\Domain\Exchange\ValueObjects\ExchangeRateQuote;
use App\Domain\Exchange\ValueObjects\RateProviderCapabilities;
use Carbon\Carbon;
use Exception;

class MockExchangeRateProvider extends BaseExchangeRateProvider
{
    private array $mockRates = [
        'USD' => [
            'EUR' => 0.85,
            'GBP' => 0.73,
            'JPY' => 110.0,
            'CHF' => 0.92,
            'AUD' => 1.35,
            'CAD' => 1.25,
        ],
        'EUR' => [
            'USD' => 1.18,
            'GBP' => 0.86,
            'JPY' => 129.5,
            'CHF' => 1.08,
        ],
        'BTC' => [
            'USD' => 45000.0,
            'EUR' => 38250.0,
            'GBP' => 32850.0,
        ],
        'ETH' => [
            'USD' => 3000.0,
            'EUR' => 2550.0,
            'BTC' => 0.0667,
        ],
        'XAU' => [
            'USD' => 1800.0,
            'EUR' => 1530.0,
        ],
    ];

    public function getName(): string
    {
        return $this->config['name'] ?? 'Mock Exchange Rate Provider';
    }

    public function getRate(string $fromCurrency, string $toCurrency): ExchangeRateQuote
    {
        $this->checkRateLimit();

        // Check for direct rate
        if (isset($this->mockRates[$fromCurrency][$toCurrency])) {
            $rate = $this->mockRates[$fromCurrency][$toCurrency];
        } elseif (isset($this->mockRates[$toCurrency][$fromCurrency])) {
            // Check for inverse rate
            $rate = 1 / $this->mockRates[$toCurrency][$fromCurrency];
        } elseif ($fromCurrency === $toCurrency) {
            // Same currency (identity rate)
            $rate = 1.0;
            // Skip variance for identity rates
            $actualRate = $rate;
            $spread = $actualRate * 0.001;
            $bid = $actualRate - ($spread / 2);
            $ask = $actualRate + ($spread / 2);

            return new ExchangeRateQuote(
                fromCurrency: $fromCurrency,
                toCurrency: $toCurrency,
                rate: $actualRate,
                bid: $bid,
                ask: $ask,
                provider: $this->getName(),
                timestamp: Carbon::now(),
                volume24h: 0, // No volume for identity rates
                change24h: 0, // No change for identity rates
                metadata: [
                    'source'        => 'mock',
                    'identity_rate' => true,
                ]
            );
        } else {
            throw new \App\Domain\Exchange\Exceptions\RateProviderException(
                "Currency pair {$fromCurrency}/{$toCurrency} not supported"
            );
        }

        // Add some randomness for realism
        $variance = $rate * 0.001; // 0.1% variance
        $actualRate = $rate + (rand(-1000, 1000) / 1000000) * $variance;

        // Calculate bid/ask with 0.1% spread
        $spread = $actualRate * 0.001;
        $bid = $actualRate - ($spread / 2);
        $ask = $actualRate + ($spread / 2);

        return new ExchangeRateQuote(
            fromCurrency: $fromCurrency,
            toCurrency: $toCurrency,
            rate: $actualRate,
            bid: $bid,
            ask: $ask,
            provider: $this->getName(),
            timestamp: Carbon::now(),
            volume24h: rand(1000000, 10000000) / 100, // Random volume
            change24h: (rand(-500, 500) / 10000), // Random change -5% to +5%
            metadata: [
                'source'         => 'mock',
                'mock_base_rate' => $rate,
            ]
        );
    }

    public function getRates(array $pairs): array
    {
        $rates = [];

        foreach ($pairs as $pair) {
            if (str_contains($pair, '/')) {
                [$from, $to] = explode('/', $pair);
                try {
                    $rates[$pair] = $this->getRate($from, $to);
                } catch (Exception $e) {
                    // Skip unsupported pairs
                }
            }
        }

        return $rates;
    }

    public function getAllRatesForBase(string $baseCurrency): array
    {
        $rates = [];

        // Get direct rates
        if (isset($this->mockRates[$baseCurrency])) {
            foreach ($this->mockRates[$baseCurrency] as $toCurrency => $rate) {
                $rates["{$baseCurrency}/{$toCurrency}"] = $this->getRate($baseCurrency, $toCurrency);
            }
        }

        // Get inverse rates
        foreach ($this->mockRates as $currency => $currencyRates) {
            if (isset($currencyRates[$baseCurrency]) && $currency !== $baseCurrency) {
                $rates["{$baseCurrency}/{$currency}"] = $this->getRate($baseCurrency, $currency);
            }
        }

        return $rates;
    }

    public function getCapabilities(): RateProviderCapabilities
    {
        return new RateProviderCapabilities(
            supportsRealtime: true,
            supportsHistorical: false,
            supportsBidAsk: true,
            supportsVolume: true,
            supportsBulkQueries: true,
            requiresAuthentication: false,
            rateLimitPerMinute: 1000,
            supportedAssetTypes: ['fiat', 'crypto', 'commodity'],
            maxHistoricalDays: null,
            additionalFeatures: ['mock_mode', 'configurable_rates']
        );
    }

    public function getSupportedCurrencies(): array
    {
        $currencies = array_keys($this->mockRates);

        // Add currencies that appear as values
        foreach ($this->mockRates as $rates) {
            $currencies = array_merge($currencies, array_keys($rates));
        }

        return array_unique($currencies);
    }

    public function getPriority(): int
    {
        return $this->config['priority'] ?? 1;
    }

    protected function getBaseUrl(): string
    {
        return 'https://mock-exchange-rates.local';
    }

    protected function getApiKeyHeader(): string
    {
        return 'X-API-Key';
    }

    protected function getHealthCheckEndpoint(): string
    {
        return '/health';
    }

    /**
     * Override isAvailable for mock provider.
     */
    public function isAvailable(): bool
    {
        return $this->config['available'] ?? true;
    }

    /**
     * Set mock rate for testing.
     */
    public function setMockRate(string $from, string $to, float $rate): void
    {
        if (! isset($this->mockRates[$from])) {
            $this->mockRates[$from] = [];
        }

        $this->mockRates[$from][$to] = $rate;
    }
}
