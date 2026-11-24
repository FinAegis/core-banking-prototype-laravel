# FinAegis Exchange

## Overview

FinAegis Exchange is an open-source exchange engine for multi-asset trading. It has an order book, order matching and fees. Licensing and regulatory compliance are the responsibility of the operator of each deployment.

The exchange is part of the demo trading engine. Its trading routes use the `feature:trading.enabled` middleware, which reads `TRADING_ENABLED`. The switch is on by default only for `APP_ENV=local`, `testing` and `demo`, and production sets `TRADING_ENABLED=false`. Zelta does not use the exchange. Exchange rates, settings and the status endpoint stay available without the switch.

## Key Features

### 1. Multi-asset support
- **Cryptocurrencies**: BTC, ETH
- **Fiat currencies**: EUR, USD, GBP
- **Demo units**: GCU demo (simulated; not issued or offered)
- **Trading pairs**: `config/trading.php` lists the intended pairs, but the code does not read that list. The order endpoint accepts any pair of three-letter currency codes.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

### 2. Order types
- **Market orders** execute at the best available price.
- **Limit orders** execute at the given price or better.
- People can place, view and cancel their orders.

### 3. Order book
The order book shows bids, asks and the spread for each pair. The trading page also shows recent trades.

### 4. Fees
- The default maker fee is 0.1%.
- The default taker fee is 0.2%.
- `FeeCalculator` lowers both fees when the 30-day trading volume reaches $500,000, and again when it reaches $1,000,000.

The fee values are set in `FeeCalculator`. The `fees` block in `config/trading.php` is not read.

### 5. Safeguards
- Funds are locked while an order is open.
- The API order endpoint uses the `transaction.rate_limit` middleware and the `idempotency` middleware.
- Every order action is stored as an event, so the history can be replayed.

## Architecture

### Event-driven design
The exchange uses event sourcing for its operations. The main events are these:
- `OrderPlaced` when a new order is created.
- `OrderMatched` when two orders match.
- `OrderPartiallyFilled` when part of an order executes.
- `OrderFilled` when an order is completely filled.
- `OrderCancelled` when an order is cancelled.

### Domain components
1. The **Order aggregate** manages the life of one order.
2. The **OrderBook aggregate** keeps the bid and ask collections.
3. `OrderMatchingWorkflow` matches orders.
4. `FeeCalculator` calculates the fees when orders match.

### Workflow-based matching
`OrderMatchingWorkflow` runs these steps with Laravel Workflow:
1. Validate the order, the account and the balance.
2. Lock the balance for the order.
3. Add the order to the order book.
4. Find and execute matches.
5. Move assets between accounts.
6. Remove filled orders from the order book.

## API Endpoints

### Public endpoints
- `GET /api/exchange/orderbook/{baseCurrency}/{quoteCurrency}` returns the order book.
- `GET /api/exchange/markets` returns market data.

### Authenticated endpoints
- `POST /api/exchange/orders` places a new order.
- `GET /api/exchange/orders` lists the person's orders.
- `DELETE /api/exchange/orders/{orderId}` cancels an order.
- `GET /api/exchange/trades` lists the person's trades.

## Web Interface

### Trading page (`/exchange`)
The trading page shows the order book, buy and sell forms with market and limit options, open orders, recent trades and a pair selector.

### Orders (`/exchange/orders`)
This page lists the person's orders with their status, and lets the person cancel open orders.

### Trades (`/exchange/trades`)
This page lists the person's completed trades and the fees paid. The page shows CSV and PDF export buttons that call `/exchange/export-trades`, but that route points to an `ExchangeController::exportTrades` method that does not exist, so export does not work at v7.18.1.

### External exchanges (`/exchange/external`)
These pages show market data from the Binance and Kraken connector adapters (integration adapters for third-party APIs; no partnership or endorsement implied). See [EXTERNAL-EXCHANGE-CONNECTORS.md](EXTERNAL-EXCHANGE-CONNECTORS.md).

Most of these pages do not work at v7.18.1. Connecting an exchange only logs the request and does not save the credentials. The arbitrage page and the arbitrage trade fail, because the controller calls `ArbitrageService::findOpportunities()` without a pair and passes an id where `ArbitrageService::executeArbitrage()` expects an array. The price alignment page fails, because its controller calls methods that `PriceAggregator` does not define.

## Admin Interface

The Filament admin panel has two resources in the Exchange group.
1. `OrderResource` lists platform orders and filters them by user, status and pair.
2. `OrderBookResource` shows the state of each order book.

## Testing

### Feature tests (Behat)
`features/exchange.feature` describes trading scenarios such as the one below.
The feature is tagged `@wip`, so the Behat suites in `behat.yml` skip it.

```gherkin
Feature: Exchange Trading
  Scenario: Place a limit buy order
    Given I am logged in as "alice@example.com"
    When I place a limit buy order for "0.1" "BTC" at "48000" "EUR"
    Then the order should be placed successfully
    And my "EUR" balance should be reduced by "4800.00"
```

### Unit tests
The unit tests in `tests/Unit/Domain/Exchange/` cover the exchange service, fee tiers, the order routing and spread sagas, and the liquidity pool services.

## Configuration

### Environment variables
```env
# Trading switch (production sets false)
TRADING_ENABLED=true

# External exchange connectors and arbitrage checks
TRADING_EXTERNAL_CONNECTORS=binance,kraken
TRADING_ARBITRAGE_ENABLED=false
```

`config/trading.php` also defines `TRADING_MIN_ORDER_*`, `TRADING_MAX_ORDER_*`, `TRADING_MAKER_FEE`, `TRADING_TAKER_FEE`, the market-making settings and the list of pairs. The code does not read those values at v7.18.1, so changing them has no effect.

### Trading rules
The minimum order sizes are set in `FeeCalculator::calculateMinimumOrderValue`.
- BTC: 0.0001 BTC
- ETH: 0.001 ETH
- EUR and USD: 10
- GCU demo: 10
- Any other base currency: 1

## Security Considerations

1. **Balance protection.** Balances are locked while an order is open, so the same funds cannot back two orders.
2. **Rate limiting.** Order placement uses `transaction.rate_limit:exchange_order`. `TransactionRateLimitMiddleware` has no `exchange_order` entry, so orders fall back to the transfer limits at v7.18.1.
3. **Audit trail.** Every order action is stored in the event store.

## Related Features

- Liquidity pools are described in [LIQUIDITY-POOLS.md](LIQUIDITY-POOLS.md). Adding, removing and rebalancing liquidity was removed in v7.18.0.
- External exchange connectors are described in [EXTERNAL-EXCHANGE-CONNECTORS.md](EXTERNAL-EXCHANGE-CONNECTORS.md).

## Future Enhancements

These ideas are not built.

1. **More order types** such as stop-loss, stop-limit, iceberg and time-weighted average price (TWAP) orders.
2. **Analytics** such as volume charts, price history and technical indicators.
