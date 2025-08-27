<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Connectors\BinanceConnector;
use App\Domain\Exchange\Connectors\KrakenConnector;
use App\Domain\Exchange\Contracts\IExternalExchangeConnector;
use App\Domain\Exchange\Exceptions\ExternalExchangeException;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ExternalExchangeConnectorRegistry
{
    private Collection $connectors;

    private Collection $enabledConnectors;

    public function __construct()
    {
        $this->connectors = collect();
        $this->enabledConnectors = collect(config('trading.external_connectors', []));

        $this->registerDefaultConnectors();
    }

    private function registerDefaultConnectors(): void
    {
        // Register Binance
        if ($this->isConnectorEnabled('binance')) {
            $this->register(
                'binance',
                new BinanceConnector(
                    config('services.binance.api_key') ?: '',
                    config('services.binance.api_secret') ?: '',
                    config('services.binance.is_us', false),
                    config('services.binance.is_testnet', false)
                )
            );
        }

        // Register Kraken
        if ($this->isConnectorEnabled('kraken')) {
            $this->register(
                'kraken',
                new KrakenConnector(
                    config('services.kraken.api_key') ?: '',
                    config('services.kraken.api_secret') ?: ''
                )
            );
        }
    }

    public function register(string $name, IExternalExchangeConnector $connector): void
    {
        $this->connectors->put($name, $connector);
    }

    public function get(string $name): IExternalExchangeConnector
    {
        if (! $this->connectors->has($name)) {
            throw new ExternalExchangeException("Exchange connector '{$name}' not found");
        }

        return $this->connectors->get($name);
    }

    public function has(string $name): bool
    {
        return $this->connectors->has($name);
    }

    public function all(): Collection
    {
        return $this->connectors;
    }

    public function available(): Collection
    {
        return $this->connectors->filter(fn ($connector) => $connector->isAvailable());
    }

    public function isConnectorEnabled(string $name): bool
    {
        return $this->enabledConnectors->contains($name);
    }

    /**
     * Get best price across all available exchanges.
     */
    public function getBestBid(string $baseCurrency, string $quoteCurrency): ?array
    {
        $bestBid = null;
        $bestExchange = null;

        foreach ($this->available() as $name => $connector) {
            try {
                $ticker = $connector->getTicker($baseCurrency, $quoteCurrency);

                if (! $bestBid || $ticker->bid->isGreaterThan($bestBid)) {
                    $bestBid = $ticker->bid;
                    $bestExchange = $name;
                }
            } catch (Exception $e) {
                // Log and continue with other exchanges
                Log::warning("Failed to get ticker from {$name}", ['error' => $e->getMessage()]);
            }
        }

        return $bestBid ? [
            'price'    => $bestBid,
            'exchange' => $bestExchange,
        ] : null;
    }

    /**
     * Get best ask across all available exchanges.
     */
    public function getBestAsk(string $baseCurrency, string $quoteCurrency): ?array
    {
        $bestAsk = null;
        $bestExchange = null;

        foreach ($this->available() as $name => $connector) {
            try {
                $ticker = $connector->getTicker($baseCurrency, $quoteCurrency);

                if (! $bestAsk || $ticker->ask->isLessThan($bestAsk)) {
                    $bestAsk = $ticker->ask;
                    $bestExchange = $name;
                }
            } catch (Exception $e) {
                // Log and continue with other exchanges
                Log::warning("Failed to get ticker from {$name}", ['error' => $e->getMessage()]);
            }
        }

        return $bestAsk ? [
            'price'    => $bestAsk,
            'exchange' => $bestExchange,
        ] : null;
    }

    /**
     * Get aggregated order book from all available exchanges.
     */
    public function getAggregatedOrderBook(string $baseCurrency, string $quoteCurrency, int $depth = 20): array
    {
        $aggregatedBids = collect();
        $aggregatedAsks = collect();

        foreach ($this->available() as $name => $connector) {
            try {
                $orderBook = $connector->getOrderBook($baseCurrency, $quoteCurrency, $depth);

                // Add exchange info to each order
                $orderBook->bids->each(
                    function ($bid) use ($aggregatedBids, $name) {
                        $bid['exchange'] = $name;
                        $aggregatedBids->push($bid);
                    }
                );

                $orderBook->asks->each(
                    function ($ask) use ($aggregatedAsks, $name) {
                        $ask['exchange'] = $name;
                        $aggregatedAsks->push($ask);
                    }
                );
            } catch (Exception $e) {
                // Log and continue with other exchanges
                Log::warning("Failed to get order book from {$name}", ['error' => $e->getMessage()]);
            }
        }

        // Sort bids descending, asks ascending
        $sortedBids = $aggregatedBids->sortByDesc(fn ($bid) => $bid['price']->__toString())->take($depth);
        $sortedAsks = $aggregatedAsks->sortBy(fn ($ask) => $ask['price']->__toString())->take($depth);

        return [
            'bids'      => $sortedBids->values(),
            'asks'      => $sortedAsks->values(),
            'exchanges' => $this->available()->keys(),
        ];
    }
}
