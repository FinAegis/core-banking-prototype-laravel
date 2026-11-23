# External Exchange Connectors

## Overview

The external exchange connectors let the FinAegis demo exchange read market data from outside exchanges.
They compare outside prices with the internal order book and look for price differences.
Binance and Kraken are integration adapters for their public APIs.

*Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.*

The connectors are part of the demo trading engine.
Their routes use the `feature:trading.enabled` middleware, so they only run with `TRADING_ENABLED=true`.
Production deployments set `TRADING_ENABLED=false`.

## Supported Exchanges

### 1. **Binance**
The Binance connector uses the v3 REST API for market data and spot orders.
It can point at the Binance testnet or at Binance US.

```env
BINANCE_API_KEY=your_api_key
BINANCE_API_SECRET=your_api_secret
BINANCE_IS_US=false
BINANCE_IS_TESTNET=false
```

### 2. **Kraken**
The Kraken connector uses the v0 REST API for market data and spot orders.

```env
KRAKEN_API_KEY=your_api_key
KRAKEN_API_SECRET=your_api_secret
```

## Architecture

### Connector interface

Every connector implements `App\Domain\Exchange\Contracts\IExternalExchangeConnector`.

| Method | Purpose |
|--------|---------|
| `getTicker()` | Current bid and ask prices |
| `getOrderBook()` | Order book depth |
| `getRecentTrades()` | Recent market trades |
| `placeBuyOrder()`, `placeSellOrder()` | Place an order |
| `cancelOrder()`, `getOrderStatus()` | Manage an order |
| `getBalance()`, `getFees()` | Account balances and fees |
| `getSupportedPairs()`, `isAvailable()`, `ping()` | Pairs and connectivity |

### Connector registry

`App\Domain\Exchange\Services\ExternalExchangeConnectorRegistry` holds the connectors.
It registers Binance and Kraken when their names are listed in `TRADING_EXTERNAL_CONNECTORS`.
It finds the best bid and the best ask across the available connectors.
It also builds one order book from all of them.

### Value objects

The connectors return `ExternalTicker`, `ExternalOrderBook` and `ExternalTrade`.
`MarketPair` describes a trading pair.

## Features

### 1. **Market Data Aggregation**
```php
// Get best prices across all exchanges
$bestBid = $registry->getBestBid('BTC', 'EUR');
$bestAsk = $registry->getBestAsk('BTC', 'EUR');

// Get aggregated order book
$aggregatedBook = $registry->getAggregatedOrderBook('BTC', 'EUR', 20);
```

### 2. **Arbitrage detection**

`ExternalLiquidityService::findArbitrageOpportunities()` compares the internal best bid and ask with outside prices.
It reports every outside price that crosses the internal spread, with the profit as a percentage.
It does not apply `TRADING_ARB_MIN_SPREAD` at v7.18.1.
It calls `getAllBids()` and `getAllAsks()` on the registry, which the registry does not define, so it fails whenever the internal order book has both bids and asks.
The scheduled arbitrage job only logs what it finds. It does not trade.
`ExternalLiquidityService::executeArbitrage()` returns "External exchange trading not yet implemented".

### 3. **External liquidity and price alignment**

`ExternalLiquidityService` also has `provideLiquidity()` and `alignPrices()`.
They place internal orders priced from the outside markets.
At v7.18.1 no route or scheduled job calls them.
The web page `/exchange/external/price-alignment` does not work at v7.18.1.
Its controller calls `getPriceDiscrepancies()` and `updateAlignmentSettings()`, which `PriceAggregator` does not define.
Moving funds between exchanges is not implemented.

## API Endpoints

### Public Endpoints

#### Get Available Connectors
```http
GET /api/external-exchange/connectors
```
Response:
```json
{
  "connectors": [
    {
      "name": "binance",
      "display_name": "Binance",
      "available": true
    }
  ]
}
```

#### Get Aggregated Ticker
```http
GET /api/external-exchange/ticker/{base}/{quote}
```
Response:
```json
{
  "pair": "BTC/EUR",
  "tickers": {
    "binance": { /* ticker data */ },
    "kraken": { /* ticker data */ }
  },
  "best_bid": {
    "price": "48000",
    "exchange": "binance"
  },
  "best_ask": {
    "price": "48100",
    "exchange": "binance"
  }
}
```

#### Get Aggregated Order Book
```http
GET /api/external-exchange/orderbook/{base}/{quote}?depth=20
```
Response:
```json
{
  "pair": "BTC/EUR",
  "orderbook": {
    "bids": [
      {"price": "48000", "amount": "0.5", "exchange": "binance"},
      {"price": "47995", "amount": "0.8", "exchange": "kraken"}
    ],
    "asks": [
      {"price": "48100", "amount": "0.3", "exchange": "binance"},
      {"price": "48105", "amount": "0.4", "exchange": "kraken"}
    ]
  }
}
```

### Authenticated Endpoints

#### Check Arbitrage Opportunities
```http
GET /api/external-exchange/arbitrage/{base}/{quote}
Authorization: Bearer {token}
```
Response:
```json
{
  "pair": "BTC/EUR",
  "opportunities": [
    {
      "type": "buy_external_sell_internal",
      "external_exchange": "binance",
      "external_price": "48100",
      "internal_price": "48200",
      "profit_percentage": "0.207900",
      "action": "Buy on binance at 48100, sell internally at 48200"
    }
  ],
  "timestamp": "2026-11-23T10:00:00+00:00"
}
```

## Configuration

### Environment Variables
```env
# Connectors the registry loads
TRADING_EXTERNAL_CONNECTORS=binance,kraken

# Arbitrage checking job (read "Scheduling" below before turning it on)
TRADING_ARBITRAGE_ENABLED=false
TRADING_ARB_CHECK_INTERVAL=60
```

These three are the only connector settings the code reads at v7.18.1.
`config/trading.php` also defines `TRADING_ARB_MIN_SPREAD`, `TRADING_ARB_MAX_ORDER`, the market-making settings (`TRADING_MARKET_MAKING_ENABLED`, `TRADING_SYSTEM_ACCOUNT_ID` and `TRADING_MM_*`), order limits, fees, trading pairs and rate limits.
The code does not read those values, so changing them has no effect.

## Jobs and Automation

### Arbitrage checking job

`App\Domain\Exchange\Jobs\CheckArbitrageOpportunitiesJob` checks every pair of active currencies.
It logs each opportunity it finds. It does not place trades.

### Scheduling

`ExternalExchangeServiceProvider` schedules the job only when `TRADING_ARBITRAGE_ENABLED=true`.
The interval is `TRADING_ARB_CHECK_INTERVAL`, which defaults to 60 seconds.
The job does not overlap with itself and runs on one server only.
At v7.18.1 the provider calls `everyMinutes()`, which the Laravel 12 scheduler does not have, so turning arbitrage scheduling on fails when the application boots.

## Security Considerations

1. **API keys.** Keep exchange credentials in environment variables. Use separate keys for testing and for any live use. Restrict keys to known IP addresses where the exchange allows it.
2. **Rate limits.** Each exchange has its own rate limits. Cache responses and back off when an exchange slows you down.
3. **Order checks.** Check minimum and maximum order sizes, tick sizes and price precision before placing an order.
4. **Errors.** The ticker endpoint skips a connector that fails and logs a warning. The other connectors still answer.

## Testing

### Test connectors

There is no ready-made mock connector class.
A test can register any `IExternalExchangeConnector` implementation, such as a Mockery mock:
```php
$mockConnector = Mockery::mock(IExternalExchangeConnector::class);
$mockConnector->shouldReceive('isAvailable')->andReturn(true);

$registry->register('mock', $mockConnector);
```

### Existing tests

`tests/Feature/Http/Controllers/Api/ExternalExchangeControllerTest.php` covers the API endpoints.

## Future Enhancements

1. More exchange adapters, such as Coinbase, Bitstamp, Bitfinex and OKX
2. WebSocket market data, order routing and execution across exchanges
3. Position limits, exposure monitoring and circuit breakers for each exchange
4. Reports on arbitrage opportunities, volumes and latency