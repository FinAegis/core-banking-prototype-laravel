# Exchange Engine User Guide

## Overview

The FinAegis exchange engine is a demo order book that runs inside a self-hosted FinAegis deployment. It matches market and limit orders between accounts on the platform. It also has example connectors that read prices from the Binance and Kraken APIs. This guide describes what the engine does at v7.18.1. The operator of each deployment decides whether it is switched on.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory
> compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

The engine is part of the demo trading features. It needs `TRADING_ENABLED=true`. That flag is off in production, including on the Zelta site, and on in the demo environment. When it is off, the exchange pages and the exchange API return "not found".

## Table of Contents

1. [Getting Started](#getting-started)
2. [Placing Orders](#placing-orders)
3. [Order Types](#order-types)
4. [Fees and Limits](#fees-and-limits)
5. [Market Making](#market-making)
6. [External Exchanges](#external-exchanges)
7. [API Trading](#api-trading)
8. [Troubleshooting](#troubleshooting)

## Getting Started

### Before You Trade

1. Log in with a verified account. Placing and cancelling orders needs a logged-in user with a verified email.
2. Make sure your account holds a balance in the currency you want to spend. In the demo, the demo accounts start with simulated balances.

### Trading Pairs

A trading pair shows the price of one asset in another. In BTC/EUR, BTC is the base asset that you buy or sell. EUR is the quote asset that sets the price. A price of 45,000 means that one BTC costs 45,000 EUR.

An order can use any two assets that are marked tradeable in the `assets` table. A fresh install marks BTC, ETH, EUR, USD and GBP as tradeable. The trading page opens BTC/EUR by default and takes another pair through `?base=` and `?quote=`. Its market summary shows BTC and ETH against EUR and USD. The markets API lists the pairs of the active liquidity pools, or BTC/EUR and ETH/EUR when there are none. `config/trading.php` also has a `pairs` list, but the code does not read it.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## Placing Orders

The trading page is at `/exchange`. Anyone can see the order book there. You need to log in to place an order.

#### Market Order

A market order runs at once against the best prices in the order book.

1. Open **Exchange** in the top navigation.
2. Select the trading pair.
3. Choose **Market**.
4. Enter the amount to buy or sell.
5. Click to place the order.

#### Limit Order

A limit order runs only at your price or a better one.

1. Choose **Limit**.
2. Enter your price.
3. Enter the amount.
4. Click to place the order.

A limit order that does not match at once waits in the order book until another order matches it or you cancel it.

### Your Orders and Trades

- `/exchange/orders` lists your orders. You can cancel an open order there.
- `/exchange/trades` lists your completed trades. The page shows CSV and PDF export buttons, but the export does not work at v7.18.1 because its controller method is missing.

### Reading the Order Book

The order book lists open sell orders (asks) above open buy orders (bids).

```
SELL ORDERS (Asks)
Price     | Amount  | Total
45,100    | 0.5     | 22,550
45,050    | 1.2     | 54,060
45,000    | 2.0     | 90,000
---------SPREAD---------
44,950    | 1.5     | 67,425
44,900    | 0.8     | 35,920
44,850    | 2.3     | 103,155
BUY ORDERS (Bids)
```

The spread is the gap between the best bid and the best ask. The depth is the amount waiting at each price.

## Order Types

The engine accepts two order types, which are `market` and `limit`. Stop orders, stop-limit orders, fill-or-kill, immediate-or-cancel and day orders are not supported.

A market order fills straight away, but the final price can move away from the price you saw. This is called slippage. A limit order fixes your price, but it may never fill.

## Fees and Limits

### Fees

The fee calculator charges a maker fee of 0.1% and a taker fee of 0.2% by default. The maker is the side whose resting order is matched. The taker is the side whose new order matches it.

Lower fees apply when an account's trading volume over the last 30 days passes a threshold.

| 30-day volume | Maker fee | Taker fee |
|---------------|-----------|-----------|
| Below $500,000 | 0.1% | 0.2% |
| $500,000 or more | 0.095% | 0.19% |
| $1,000,000 or more | 0.09% | 0.18% |

### Order Size Limits

The engine has fixed minimum order amounts for the base asset. They are 0.0001 BTC, 0.001 ETH and 10 EUR, USD or GCU. Any other base asset needs at least 1 unit. There is no maximum order size.

`config/trading.php` also lists minimum and maximum order sizes and has the `TRADING_MAKER_FEE` and `TRADING_TAKER_FEE` settings. At v7.18.1 the order flow does not read these values, so changing them has no effect.

## Market Making

The engine can place buy and sell orders on both sides of a market to keep the order book filled. When trading is on, the scheduler runs `liquidity:update-market-making --cancel-existing` every five minutes. The command reads each active liquidity pool, cancels the pool's previous market-making orders and places new ones around the pool price.

`config/trading.php` also has a `market_making` block with `TRADING_MARKET_MAKING_ENABLED`, a spread, an order size and a maximum exposure. At v7.18.1 the scheduled command does not read that block.

Adding, removing and rebalancing pool liquidity was removed in v7.18.0. The pool lists, swaps, positions, rewards and impermanent-loss protection stay. See [Liquidity Pools](../03-FEATURES/LIQUIDITY-POOLS.md).

## External Exchanges

### Exchange API Connectors

- Example connectors for the Binance and Kraken APIs. Integration adapters for third-party APIs; no partnership or endorsement implied.

The connectors read public prices and order books. They do not hold any funds.

### External Exchange Pages

The page at `/exchange/external` lists the connectors and has a form for API credentials. The form accepts Binance, Kraken and Coinbase, but only the Binance and Kraken connectors exist. At v7.18.1 the credentials are only checked for presence, and the connection is logged. The credentials are not stored.

The page at `/exchange/external/arbitrage` shows price differences between the connected exchanges. Executing an arbitrage trade from that page does not work at v7.18.1.

The page at `/exchange/external/price-alignment` shows how the internal prices compare with the external ones.

## API Trading

### Authentication

The exchange API uses the same bearer tokens as the rest of the platform. Get a token from `POST /api/auth/login`.

```bash
curl -X POST https://<your-deployment>/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "you@example.com", "password": "..."}'
```

Send the `access_token` from the response in the `Authorization: Bearer` header.

### API Endpoints

The base URL is `https://<your-deployment>/api`. The full reference is at `/api/documentation`.

| Method | Path | Login needed |
|--------|------|--------------|
| GET | `/api/exchange/orderbook/{baseCurrency}/{quoteCurrency}` | No |
| GET | `/api/exchange/markets` | No |
| POST | `/api/exchange/orders` | Yes |
| GET | `/api/exchange/orders` | Yes |
| DELETE | `/api/exchange/orders/{orderId}` | Yes |
| GET | `/api/exchange/trades` | Yes |
| GET | `/api/external-exchange/connectors` | No |
| GET | `/api/external-exchange/ticker/{base}/{quote}` | No |
| GET | `/api/external-exchange/orderbook/{base}/{quote}` | No |
| GET | `/api/external-exchange/arbitrage/{base}/{quote}` | Yes |

#### Get the Order Book
```bash
curl https://<your-deployment>/api/exchange/orderbook/BTC/EUR
```

#### Place an Order
```bash
curl -X POST https://<your-deployment>/api/exchange/orders \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: 6f1c2b7e-0d1a-4c55-9f00-1b2c3d4e5f60" \
  -d '{
    "type": "buy",
    "order_type": "limit",
    "base_currency": "BTC",
    "quote_currency": "EUR",
    "amount": 0.1,
    "price": 44500
  }'
```

`price` is required for a limit order. The `Idempotency-Key` header is optional. When you send it, a repeated request with the same key returns the first result instead of placing a second order.

#### Cancel an Order
```bash
curl -X DELETE https://<your-deployment>/api/exchange/orders/{orderId} \
  -H "Authorization: Bearer {access_token}"
```

### Rate Limits

Order placement is limited per user. At v7.18.1 it uses the transfer limit of 15 orders per hour. Read requests have their own per-minute limits.

## Troubleshooting

### Common Issues

#### The exchange page returns "not found"

`TRADING_ENABLED` is off. Set it to `true` on a demo or development deployment.

#### An order does not fill

A limit order fills only when another order matches its price. Check your price against the order book. A market order fills only if there are resting orders on the other side.

#### The balance looks lower than expected

Open orders hold part of your balance until they fill or you cancel them. Check `/exchange/orders`.

### Error Messages

#### "Insufficient Balance"
Your account does not hold enough of the asset you are spending. Check the available balance and your open orders.

#### "Too Many Requests"
You reached a rate limit. Wait and try again later.

### Getting Help

- [GitHub Issues](https://github.com/FinAegis/core-banking-prototype-laravel/issues)

## Further Reading

- [Exchange feature notes](../03-FEATURES/EXCHANGE.md)
- [External exchange connectors](../03-FEATURES/EXTERNAL-EXCHANGE-CONNECTORS.md)
- [REST API Reference](../04-API/REST_API_REFERENCE.md)
