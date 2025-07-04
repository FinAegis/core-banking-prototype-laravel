<?php

namespace App\Domain\Exchange\Contracts;

use App\Domain\Exchange\Contracts\ExchangeRateProviderInterface;
use Brick\Math\BigDecimal;

interface ExchangeRateProviderRegistryInterface
{
    /**
     * Register an exchange rate provider
     *
     * @param string $name
     * @param mixed $provider
     * @return void
     */
    public function register(string $name, mixed $provider): void;

    /**
     * Get rate from best available provider
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return BigDecimal|null
     */
    public function getRate(string $fromCurrency, string $toCurrency): ?BigDecimal;

    /**
     * Get aggregated rate from multiple providers
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param string $aggregationMethod
     * @return BigDecimal|null
     */
    public function getAggregatedRate(
        string $fromCurrency,
        string $toCurrency,
        string $aggregationMethod = 'median'
    ): ?BigDecimal;

    /**
     * Get rates from all providers
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return array
     */
    public function getRatesFromAll(string $fromCurrency, string $toCurrency): array;

    /**
     * Remove a provider
     *
     * @param string $name
     * @return void
     */
    public function remove(string $name): void;

    /**
     * Get all registered providers
     *
     * @return array
     */
    public function getProviders(): array;

    /**
     * Update provider priority
     *
     * @param string $name
     * @param int $priority
     * @return void
     */
    public function setPriority(string $name, int $priority): void;

    /**
     * Check provider health
     *
     * @param string $name
     * @return array
     */
    public function checkProviderHealth(string $name): array;
}