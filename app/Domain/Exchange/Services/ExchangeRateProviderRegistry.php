<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Contracts\ExchangeRateProviderRegistryInterface;
use App\Domain\Exchange\Contracts\IExchangeRateProvider;
use App\Domain\Exchange\Contracts\ExchangeRateProviderInterface;
use App\Domain\Exchange\Exceptions\RateProviderException;
use App\Domain\Exchange\ValueObjects\ExchangeRateQuote;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ExchangeRateProviderRegistry implements ExchangeRateProviderRegistryInterface
{
    private array $providers = [];
    private array $priorities = [];
    private ?string $defaultProvider = null;

    /**
     * Register a provider
     */
    public function register(string $name, mixed $provider): void
    {
        if (!$provider instanceof IExchangeRateProvider && !$provider instanceof ExchangeRateProviderInterface) {
            throw new RateProviderException('Provider must implement IExchangeRateProvider or ExchangeRateProviderInterface');
        }
        
        $this->providers[$name] = $provider;
        $this->priorities[$name] = 100; // Default priority
        
        // Set as default if it's the first one
        if ($this->defaultProvider === null) {
            $this->defaultProvider = $name;
        }

        Log::info("Registered exchange rate provider: {$name}");
    }

    /**
     * Get a provider by name
     */
    public function get(string $name): IExchangeRateProvider|ExchangeRateProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new RateProviderException("Exchange rate provider '{$name}' not found");
        }

        return $this->providers[$name];
    }

    /**
     * Get the default provider
     */
    public function getDefault(): IExchangeRateProvider|ExchangeRateProviderInterface
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
     * Get all registered providers (interface method)
     */
    public function getProviders(): array
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
            ->sortByDesc(fn($provider, $name) => $this->priorities[$name] ?? 0);
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
    public function getRate(string $fromCurrency, string $toCurrency): ?BigDecimal
    {
        $availableProviders = $this->findByCurrencyPair($fromCurrency, $toCurrency);
        
        if (empty($availableProviders)) {
            Log::warning("No providers available for currency pair {$fromCurrency}/{$toCurrency}");
            return null;
        }

        // Sort by priority and try each one
        $sorted = collect($availableProviders)
            ->sortByDesc(fn($provider, $name) => $this->priorities[$name] ?? 0);

        $lastException = null;

        foreach ($sorted as $name => $provider) {
            try {
                if ($provider->isAvailable()) {
                    $quote = $provider->getRate($fromCurrency, $toCurrency);
                    if ($quote instanceof ExchangeRateQuote) {
                        return BigDecimal::of((string)$quote->rate);
                    } elseif ($quote instanceof BigDecimal) {
                        return $quote;
                    } else {
                        return BigDecimal::of((string)$quote);
                    }
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("Provider {$name} failed to get rate", [
                    'error' => $e->getMessage(),
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                ]);
            }
        }

        if ($lastException) {
            Log::error("Failed to get rate from any provider for {$fromCurrency}/{$toCurrency}", [
                'error' => $lastException->getMessage()
            ]);
        }

        return null;
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
                    $quote = $provider->getRate($fromCurrency, $toCurrency);
                    $results[$name] = $quote;
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
    public function getAggregatedRate(
        string $fromCurrency,
        string $toCurrency,
        string $aggregationMethod = 'median'
    ): ?BigDecimal {
        $rates = $this->getRatesFromAll($fromCurrency, $toCurrency);
        
        if (empty($rates)) {
            Log::warning("No providers could fetch rate for {$fromCurrency}/{$toCurrency}");
            return null;
        }

        // Extract numeric rates
        $numericRates = [];
        foreach ($rates as $quote) {
            if ($quote instanceof ExchangeRateQuote) {
                $numericRates[] = BigDecimal::of((string)$quote->rate);
            } elseif ($quote instanceof BigDecimal) {
                $numericRates[] = $quote;
            } else {
                $numericRates[] = BigDecimal::of((string)$quote);
            }
        }

        switch ($aggregationMethod) {
            case 'mean':
            case 'average':
                $sum = BigDecimal::of('0');
                foreach ($numericRates as $rate) {
                    $sum = $sum->plus($rate);
                }
                return $sum->dividedBy(count($numericRates), 8, RoundingMode::HALF_UP);
                
            case 'median':
                sort($numericRates);
                $count = count($numericRates);
                if ($count % 2 === 0) {
                    // Even number of rates - average the two middle values
                    $mid1 = $numericRates[$count / 2 - 1];
                    $mid2 = $numericRates[$count / 2];
                    return $mid1->plus($mid2)->dividedBy('2', 8, RoundingMode::HALF_UP);
                } else {
                    // Odd number of rates - return the middle value
                    return $numericRates[floor($count / 2)];
                }
                
            case 'min':
                return min($numericRates);
                
            case 'max':
                return max($numericRates);
                
            default:
                throw new RateProviderException("Unknown aggregation method: {$aggregationMethod}");
        }
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
        unset($this->priorities[$name]);
        
        if ($this->defaultProvider === $name) {
            $this->defaultProvider = null;
        }
    }

    /**
     * Update provider priority
     */
    public function setPriority(string $name, int $priority): void
    {
        if (!isset($this->providers[$name])) {
            throw new RateProviderException("Exchange rate provider '{$name}' not found");
        }

        $this->priorities[$name] = $priority;
    }

    /**
     * Check provider health
     */
    public function checkProviderHealth(string $name): array
    {
        if (!isset($this->providers[$name])) {
            throw new RateProviderException("Exchange rate provider '{$name}' not found");
        }

        $provider = $this->providers[$name];
        $health = [
            'name' => $name,
            'available' => false,
            'response_time' => null,
            'last_error' => null,
            'supported_pairs' => []
        ];

        try {
            $start = microtime(true);
            $available = $provider->isAvailable();
            $responseTime = (microtime(true) - $start) * 1000; // Convert to milliseconds
            
            $health['available'] = $available;
            $health['response_time'] = round($responseTime, 2);
            
            // Test a common currency pair if available
            if ($available && method_exists($provider, 'supportsPair')) {
                if ($provider->supportsPair('USD', 'EUR')) {
                    try {
                        $testStart = microtime(true);
                        $provider->getRate('USD', 'EUR');
                        $health['test_response_time'] = round((microtime(true) - $testStart) * 1000, 2);
                    } catch (\Exception $e) {
                        $health['test_error'] = $e->getMessage();
                    }
                }
            }
            
        } catch (\Exception $e) {
            $health['available'] = false;
            $health['last_error'] = $e->getMessage();
        }

        return $health;
    }

    /**
     * Get provider names
     */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}