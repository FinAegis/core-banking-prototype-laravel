# External Exchange Connectors

## Overview

FinAegis Exchange integrates with major cryptocurrency exchanges to provide enhanced liquidity, price discovery, and arbitrage opportunities. This feature enables the platform to aggregate market data and potentially execute trades across multiple venues.

## Supported Exchanges

### 1. **Binance**
- **Features**: Spot trading, market data, order placement
- **API Version**: v3
- **Testnet**: Available for development
- **Configuration**:
  ```env
  BINANCE_API_KEY=your_api_key
  BINANCE_API_SECRET=your_api_secret
  BINANCE_IS_US=false
  BINANCE_IS_TESTNET=false
  ```

### 2. **Kraken**
- **Features**: Spot trading, market data, order placement
- **API Version**: v0
- **Configuration**:
  ```env
  KRAKEN_API_KEY=your_api_key
  KRAKEN_API_SECRET=your_api_secret
  ```

## Architecture

### Connector Interface
All exchange connectors implement the `IExternalExchangeConnector` interface:
- `getTicker()` - Get current bid/ask prices
- `getOrderBook()` - Get order book depth
- `getRecentTrades()` - Get recent market trades
- `placeBuyOrder()` / `placeSellOrder()` - Place orders
- `getBalance()` - Get account balances
- `ping()` - Check connectivity

### Connector Registry
The `ExternalExchangeConnectorRegistry` manages all connectors:
- Automatic registration of enabled connectors
- Health checking and availability monitoring
- Best price discovery across exchanges
- Aggregated order book compilation

### Value Objects
- `ExternalTicker` - Standardized ticker data
- `ExternalOrderBook` - Normalized order book
- `ExternalTrade` - Trade information
- `MarketPair` - Trading pair configuration

## Features

### 1. **Market Data Aggregation**
```php
// Get best prices across all exchanges
$bestBid = $registry->getBestBid('BTC', 'EUR');
$bestAsk = $registry->getBestAsk('BTC', 'EUR');

// Get aggregated order book
$aggregatedBook = $registry->getAggregatedOrderBook('BTC', 'EUR', 20);
```

### 2. **Arbitrage Detection**
The system continuously monitors price differences:
- Internal vs External prices
- Cross-exchange opportunities
- Configurable minimum spread thresholds
- Automated opportunity logging

### 3. **External Liquidity Provision**
When internal order books are thin:
- Automatically adds liquidity orders
- Prices based on external market rates
- Configurable spread markup/markdown
- Maintains market competitiveness

### 4. **Price Alignment**
Keeps internal prices aligned with external markets:
- Weighted average price calculation
- Market making order placement
- Automatic order cancellation/replacement
- Configurable spread parameters

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
      "spread": "100",
      "spread_percent": "0.207"
    }
  ]
}
```

## Configuration

### Environment Variables
```env
# Enable/disable external connectors
TRADING_EXTERNAL_CONNECTORS=binance,kraken

# Market making configuration
TRADING_MARKET_MAKING_ENABLED=true
TRADING_SYSTEM_ACCOUNT_ID=system-account-uuid
TRADING_MM_SPREAD=0.002
TRADING_MM_ORDER_SIZE=0.1
TRADING_MM_MAX_EXPOSURE=10

# Arbitrage configuration
TRADING_ARBITRAGE_ENABLED=true
TRADING_ARB_MIN_SPREAD=0.005
TRADING_ARB_CHECK_INTERVAL=60
TRADING_ARB_MAX_ORDER=1
```

### Trading Configuration
Located in `config/trading.php`:
- Order limits per currency
- Fee structures
- Supported trading pairs
- Rate limiting settings

## Jobs and Automation

### Arbitrage Checking Job
Runs periodically to check for opportunities:
```php
class CheckArbitrageOpportunitiesJob
{
    // Checks all configured pairs
    // Logs opportunities found
    // Can trigger automated trades
}
```

### Scheduled Tasks
When arbitrage is enabled:
- Runs every configured interval (default: 60 seconds)
- Prevents overlapping executions
- Runs on single server only

## Security Considerations

1. **API Key Management**
   - Store credentials in environment variables
   - Use separate keys for production/testing
   - Implement IP whitelisting where supported

2. **Rate Limiting**
   - Respect exchange rate limits
   - Implement backoff strategies
   - Cache responses appropriately

3. **Order Validation**
   - Verify minimum/maximum order sizes
   - Check tick size requirements
   - Validate price precision

4. **Error Handling**
   - Graceful degradation when exchanges unavailable
   - Comprehensive error logging
   - Fallback to other exchanges

## Testing

### Mock Connectors
For development and testing:
```php
$mockConnector = new MockExchangeConnector([
    'ticker' => ['bid' => '48000', 'ask' => '48100'],
    'orderbook' => [/* mock data */]
]);

$registry->register('mock', $mockConnector);
```

### Integration Tests
- Test each connector individually
- Verify data normalization
- Check error handling
- Validate aggregation logic

## Future Enhancements

1. **Additional Exchanges**
   - Coinbase Pro
   - Bitstamp
   - Bitfinex
   - OKEx

2. **Advanced Features**
   - WebSocket connections for real-time data
   - Smart order routing
   - Cross-exchange order execution
   - Historical data storage

3. **Risk Management**
   - Position limits per exchange
   - Exposure monitoring
   - Automated hedging
   - Circuit breakers

4. **Analytics**
   - Arbitrage opportunity tracking
   - Profitability analysis
   - Volume statistics
   - Latency monitoring