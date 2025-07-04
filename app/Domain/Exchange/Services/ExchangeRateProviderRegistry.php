<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Contracts\ExchangeRateProviderRegistryInterface;
use App\Domain\Exchange\Contracts\IExchangeRateProvider;
use App\Domain\Exchange\Contracts\ExchangeRateProviderInterface;
use App\Domain\Exchange\Exceptions\RateProviderException;
use App\Domain\Exchange\ValueObjects\ExchangeRateQuote;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ExchangeRateProviderRegistry implements ExchangeRateProviderRegistryInterface
{
    private array $providers = [];
    private ?string $defaultProvider = null;

    /**
     * Register a provider
     */
    public function register(string $name, IExchangeRateProvider $provider): void
    {
        $this->providers[$name] = $provider;
        
        // Set as default if it's the first one
        if ($this->defaultProvider === null) {
            $this->defaultProvider = $name;
        }

        Log::info("Registered exchange rate provider: {$name}");
    }

    /**
     * Get a provider by name
     */
    public function get(string $name): IExchangeRateProvider
    {
        if (!isset($this->providers[$name])) {
            throw new RateProviderException("Exchange rate provider '{$name}' not found");
        }

        return $this->providers[$name];
    }

    /**
     * Get the default provider
     */
    public function getDefault(): IExchangeRateProvider
    {
        if ($this->defaultProvider === null) {
            throw new RateProviderException("No default exchange rate provider configured");
        }

        return $this->get($this->defaultProvider);
    }

    /**
     * Set the default provider
     */
    public function setDefault(string $name): void
    {
        if (!isset($this->providers[$name])) {
            throw new RateProviderException("Exchange rate provider '{$name}' not found");
        }

        $this->defaultProvider = $name;
    }

    /**
     * Get all registered providers
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Get available providers (those that pass health check)
     */
    public function available(): array
    {
        return array_filter($this->providers, fn($provider) => $provider->isAvailable());
    }

    /**
     * Get providers sorted by priority
     */
    public function byPriority(): Collection
    {
        return collect($this->providers)
            ->sortByDesc(fn($provider) => $provider->getPriority());
    }

    /**
     * Find providers that support a currency pair
     */
    public function findByCurrencyPair(string $fromCurrency, string $toCurrency): array
    {
        return array_filter($this->providers, function ($provider) use ($fromCurrency, $toCurrency) {
            return $provider->supportsPair($fromCurrency, $toCurrency);
        });
    }

    /**
     * Get rate from the first available provider
     */
    public function getRate(string $fromCurrency, string $toCurrency): ExchangeRateQuote
    {
        $availableProviders = $this->findByCurrencyPair($fromCurrency, $toCurrency);
        
        if (empty($availableProviders)) {
            throw new RateProviderException(
                "No providers available for currency pair {$fromCurrency}/{$toCurrency}"
            );
        }

        // Sort by priority and try each one
        $sorted = collect($availableProviders)
            ->sortByDesc(fn($provider) => $provider->getPriority());

        $lastException = null;

        foreach ($sorted as $provider) {
            try {
                if ($provider->isAvailable()) {
                    return $provider->getRate($fromCurrency, $toCurrency);
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("Provider {$provider->getName()} failed to get rate", [
                    'error' => $e->getMessage(),
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                ]);
            }
        }

        throw new RateProviderException(
            "Failed to get rate from any provider for {$fromCurrency}/{$toCurrency}",
            500,
            $lastException
        );
    }

    /**
     * Get rates from multiple providers for comparison
     */
    public function getRatesFromAll(string $fromCurrency, string $toCurrency): array
    {
        $results = [];

        foreach ($this->providers as $name => $provider) {
            try {
                if ($provider->isAvailable() && $provider->supportsPair($fromCurrency, $toCurrency)) {
                    $results[$name] = $provider->getRate($fromCurrency, $toCurrency);
                }
            } catch (\Exception $e) {
                Log::debug("Provider {$name} failed to get rate", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get aggregated rate (average of available providers)
     */
    public function getAggregatedRate(string $fromCurrency, string $toCurrency): ExchangeRateQuote
    {
        $rates = $this->getRatesFromAll($fromCurrency, $toCurrency);
        
        if (empty($rates)) {
            throw new RateProviderException(
                "No providers could fetch rate for {$fromCurrency}/{$toCurrency}"
            );
        }

        // Calculate averages
        $sumRate = 0;
        $sumBid = 0;
        $sumAsk = 0;
        $count = count($rates);

        foreach ($rates as $quote) {
            $sumRate += $quote->rate;
            $sumBid += $quote->bid;
            $sumAsk += $quote->ask;
        }

        // Use the most recent timestamp
        $latestTimestamp = collect($rates)
            ->map(fn($quote) => $quote->timestamp)
            ->max();

        return new ExchangeRateQuote(
            fromCurrency: $fromCurrency,
            toCurrency: $toCurrency,
            rate: $sumRate / $count,
            bid: $sumBid / $count,
            ask: $sumAsk / $count,
            provider: 'aggregated',
            timestamp: $latestTimestamp,
            metadata: [
                'providers' => array_keys($rates),
                'count' => $count,
            ]
        );
    }

    /**
     * Check if a provider is registered
     */
    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * Remove a provider
     */
    public function remove(string $name): void
    {
        unset($this->providers[$name]);
        
        if ($this->defaultProvider === $name) {
            $this->defaultProvider = null;
        }
    }

    /**
     * Get provider names
     */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}