<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Interface for exchange rate operations used by external domains.
 *
 * This interface enables domain decoupling by allowing domains like
 * Basket, Stablecoin, Treasury, etc. to get exchange rates without
 * directly depending on the Exchange domain implementation.
 *
 * @see \App\Domain\Exchange\Services\ExchangeRateService for implementation
 */
interface ExchangeRateInterface
{
    /**
     * Get the current exchange rate between two currencies.
     *
     * @param string $fromCurrency Source currency code (e.g., 'EUR')
     * @param string $toCurrency Target currency code (e.g., 'USD')
     * @return string Exchange rate as string for precision
     *
     * @throws \App\Domain\Exchange\Exceptions\UnsupportedPairException
     */
    public function getRate(string $fromCurrency, string $toCurrency): string;

    /**
     * Get exchange rates for multiple pairs at once.
     *
     * @param array<array{from: string, to: string}> $pairs Currency pairs
     * @return array<string, string> Map of "FROM/TO" => rate
     */
    public function getRates(array $pairs): array;

    /**
     * Convert an amount from one currency to another.
     *
     * @param string $amount Amount to convert (as string for precision)
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @return string Converted amount as string
     */
    public function convert(string $amount, string $fromCurrency, string $toCurrency): string;

    /**
     * Get historical exchange rate for a specific date.
     *
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @param \DateTimeInterface $date Historical date
     * @return string|null Exchange rate or null if not available
     */
    public function getHistoricalRate(
        string $fromCurrency,
        string $toCurrency,
        \DateTimeInterface $date
    ): ?string;

    /**
     * Check if a currency pair is supported.
     *
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @return bool True if the pair is tradeable
     */
    public function isPairSupported(string $fromCurrency, string $toCurrency): bool;

    /**
     * Get all supported currencies.
     *
     * @return array<string> List of currency codes
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get the mid-market rate (average of bid and ask).
     *
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @return array{
     *     mid: string,
     *     bid: string,
     *     ask: string,
     *     spread: string,
     *     timestamp: string
     * }
     */
    public function getQuote(string $fromCurrency, string $toCurrency): array;
}
