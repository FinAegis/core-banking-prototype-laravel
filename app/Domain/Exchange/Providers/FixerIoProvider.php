<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Providers;

use App\Domain\Exchange\Exceptions\RateProviderException;
use App\Domain\Exchange\ValueObjects\ExchangeRateQuote;
use App\Domain\Exchange\ValueObjects\RateProviderCapabilities;
use App\Support\SecretRedactor;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

class FixerIoProvider extends BaseExchangeRateProvider
{
    private const SUPPORTED_CURRENCIES = [
        'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
        'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL',
        'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF',
        'CLP', 'CNY', 'COP', 'CRC', 'CUC', 'CUP', 'CVE', 'CZK', 'DJF', 'DKK',
        'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL',
        'GGP', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK',
        'HTG', 'HUF', 'IDR', 'ILS', 'IMP', 'INR', 'IQD', 'IRR', 'ISK', 'JEP',
        'JMD', 'JOD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KPW', 'KRW', 'KWD',
        'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'LTL', 'LVL', 'LYD',
        'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR',
        'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD',
        'OMR', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON',
        'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP',
        'SLL', 'SOS', 'SRD', 'STD', 'SVC', 'SYP', 'SZL', 'THB', 'TJS', 'TMT',
        'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD', 'UYU',
        'UZS', 'VEF', 'VND', 'VUV', 'WST', 'XAF', 'XAG', 'XAU', 'XCD', 'XDR',
        'XOF', 'XPF', 'YER', 'ZAR', 'ZMK', 'ZMW', 'ZWL',
    ];

    public function getName(): string
    {
        return 'Fixer.io';
    }

    public function getRate(string $fromCurrency, string $toCurrency): ExchangeRateQuote
    {
        $this->checkRateLimit();

        $cacheKey = "rate:{$fromCurrency}:{$toCurrency}";

        return $this->remember(
            $cacheKey,
            function () use ($fromCurrency, $toCurrency) {
                $endpoint = '/latest';
                $params = [
                    'access_key' => $this->config['api_key'],
                    'base'       => $fromCurrency,
                    'symbols'    => $toCurrency,
                ];

                $this->logRequest('GET', $endpoint, $params);

                $response = $this->fetch($endpoint, $params);

                $this->logResponse('GET', $endpoint, $response);

                if (! $response->successful()) {
                    $this->handleApiError($response, 'getRate');
                }

                $data = $response->json();

                if (! $data['success'] ?? false) {
                    throw new RateProviderException(
                        'Fixer.io API error: ' . ($data['error']['info'] ?? 'Unknown error')
                    );
                }

                if (! isset($data['rates'][$toCurrency])) {
                    throw new RateProviderException(
                        "Rate for {$fromCurrency}/{$toCurrency} not found in response"
                    );
                }

                $rate = (float) $data['rates'][$toCurrency];

                // Fixer.io doesn't provide bid/ask, so we'll simulate a small spread
                $spread = $rate * 0.002; // 0.2% spread
                $bid = $rate - ($spread / 2);
                $ask = $rate + ($spread / 2);

                return new ExchangeRateQuote(
                    fromCurrency: $fromCurrency,
                    toCurrency: $toCurrency,
                    rate: $rate,
                    bid: $bid,
                    ask: $ask,
                    provider: $this->getName(),
                    timestamp: Carbon::parse($data['timestamp']),
                    metadata: [
                        'base' => $data['base'],
                        'date' => $data['date'],
                    ]
                );
            }
        );
    }

    public function getRates(array $pairs): array
    {
        $rates = [];

        // Group by base currency for efficiency
        $grouped = [];
        foreach ($pairs as $pair) {
            if (str_contains($pair, '/')) {
                [$from, $to] = explode('/', $pair);
                if (! isset($grouped[$from])) {
                    $grouped[$from] = [];
                }
                $grouped[$from][] = $to;
            }
        }

        // Fetch rates for each base currency
        foreach ($grouped as $base => $symbols) {
            $quotes = $this->getRatesForBase($base, $symbols);
            foreach ($quotes as $symbol => $quote) {
                $rates["{$base}/{$symbol}"] = $quote;
            }
        }

        return $rates;
    }

    public function getAllRatesForBase(string $baseCurrency): array
    {
        return $this->getRatesForBase($baseCurrency);
    }

    private function getRatesForBase(string $baseCurrency, array $symbols = []): array
    {
        $this->checkRateLimit();

        $cacheKey = "rates:{$baseCurrency}:" . md5(implode(',', $symbols));

        return $this->remember(
            $cacheKey,
            function () use ($baseCurrency, $symbols) {
                $endpoint = '/latest';
                $params = [
                    'access_key' => $this->config['api_key'],
                    'base'       => $baseCurrency,
                ];

                if (! empty($symbols)) {
                    $params['symbols'] = implode(',', $symbols);
                }

                $this->logRequest('GET', $endpoint, $params);

                $response = $this->fetch($endpoint, $params);

                $this->logResponse('GET', $endpoint, $response);

                if (! $response->successful()) {
                    $this->handleApiError($response, 'getRatesForBase');
                }

                $data = $response->json();

                if (! $data['success'] ?? false) {
                    throw new RateProviderException(
                        'Fixer.io API error: ' . ($data['error']['info'] ?? 'Unknown error')
                    );
                }

                $quotes = [];
                $timestamp = Carbon::parse($data['timestamp']);

                foreach ($data['rates'] as $currency => $rate) {
                    $rateFloat = (float) $rate;
                    $spread = $rateFloat * 0.002; // 0.2% spread

                    $quotes[$currency] = new ExchangeRateQuote(
                        fromCurrency: $baseCurrency,
                        toCurrency: $currency,
                        rate: $rateFloat,
                        bid: $rateFloat - ($spread / 2),
                        ask: $rateFloat + ($spread / 2),
                        provider: $this->getName(),
                        timestamp: $timestamp,
                        metadata: [
                            'base' => $data['base'],
                            'date' => $data['date'],
                        ]
                    );
                }

                return $quotes;
            }
        );
    }

    public function getCapabilities(): RateProviderCapabilities
    {
        return new RateProviderCapabilities(
            supportsRealtime: true,
            supportsHistorical: true,
            supportsBidAsk: false,
            supportsVolume: false,
            supportsBulkQueries: true,
            requiresAuthentication: true,
            rateLimitPerMinute: $this->config['rate_limit'] ?? 100,
            supportedAssetTypes: ['fiat', 'crypto', 'commodity'],
            maxHistoricalDays: 365,
            additionalFeatures: ['time_series', 'fluctuation', 'conversion']
        );
    }

    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function getPriority(): int
    {
        return $this->config['priority'] ?? 50;
    }

    /**
     * Fixer.io takes the key as the `access_key` query parameter, so a
     * transport error (which embeds the request URL) is rethrown with the key
     * redacted and without the original exception attached.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws RateProviderException
     */
    private function fetch(string $endpoint, array $params): Response
    {
        try {
            return $this->client->get($endpoint, $params);
        } catch (ConnectionException $e) {
            throw new RateProviderException('Fixer.io unreachable: ' . SecretRedactor::message($e));
        }
    }

    protected function getBaseUrl(): string
    {
        return $this->config['base_url'] ?? 'https://api.fixer.io/v1';
    }

    protected function getApiKeyHeader(): string
    {
        return 'access_key'; // Fixer.io uses query parameter, not header
    }

    protected function getHealthCheckEndpoint(): string
    {
        return '/latest?access_key=' . ($this->config['api_key'] ?? '') . '&base=USD&symbols=EUR';
    }
}
