# FinAegis REST API Reference

**Version:** v7.18.1
**Last Updated:** 2026-11-24
**Status:** Open-source reference platform

This page lists the main REST endpoint groups of FinAegis and shows how to call them. It covers the core banking reference API, the demo modules and the Zelta wallet endpoints. The route list (`php artisan route:list`) is the source of truth for which endpoints exist. The OpenAPI document served at `/api/documentation` gives the request and response schemas for each operation.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Table of Contents
- [Which endpoints are switched on](#which-endpoints-are-switched-on)
- [Authentication](#authentication)
- [Zelta wallet endpoints](#zelta-wallet-endpoints)
- [Hardware Wallet](#hardware-wallet)
- [WebSocket Streaming](#websocket-streaming)
- [Account Management](#account-management)
- [Asset Management](#asset-management)
- [Transaction Management](#transaction-management)
- [Transfer Operations](#transfer-operations)
- [Exchange Rates](#exchange-rates)
- [Governance & Voting](#governance--voting)
- [GCU demo (simulated conversions)](#gcu-demo-simulated-conversions)
- [CGO demo module (investment-intake workflow)](#cgo-demo-module-investment-intake-workflow)
- [Basket API](#basket-api)
- [Compliance API](#compliance-api)
- [Custodian Integration](#custodian-integration)
- [Webhooks](#webhooks)
- [Bank Allocation](#bank-allocation)
- [Batch Operations](#batch-operations)
- [Transaction Reversal](#transaction-reversal)
- [Regulatory Reporting](#regulatory-reporting)
- [Daily Reconciliation](#daily-reconciliation)
- [Bank Health and Alerting](#bank-health-and-alerting)
- [Stablecoin Operations](#stablecoin-operations)
- [Exchange & Trading](#exchange--trading)
- [External Exchange Integration](#external-exchange-integration)
- [Liquidity Pools](#liquidity-pools)
- [P2P Lending](#p2p-lending)
- [Blockchain Wallets](#blockchain-wallets)
- [Error Responses](#error-responses)
- [Rate Limiting](#rate-limiting)
- [Pagination](#pagination)
- [API Versioning](#api-versioning)

## Which endpoints are switched on

Many groups on this page belong to demo modules. A deployment turns a module off with `MODULES_DISABLED`, and the routes of that module then answer `404`. Some groups are also behind a feature flag. A route that a feature flag switches off also answers `404`. `TRADING_ENABLED` and `LEDGER_DIRECT_FUNDING_ENABLED` are on by default when `APP_ENV` is `local`, `testing` or `demo`, and off by default everywhere else.

The production Zelta configuration in `.env.zelta.example` sets these values:

```env
MODULES_DISABLED="Banking,Basket,Cgo,CrossChain,DeFi,FinancialInstitution,Governance,ISO20022,ISO8583,Interledger,Ledger,Lending,Microfinance,OpenBanking,PaymentRails,Stablecoin,Treasury"
TRADING_ENABLED=false
LEDGER_DIRECT_FUNDING_ENABLED=false
```

| Group on this page | Gate | Production Zelta |
|--------------------|------|------------------|
| Voting and polls | `Governance` module | Off |
| GCU conversions, baskets | `Basket` module | Off |
| CGO pages | `Cgo` module | Off |
| Bank allocation, batch operations, the CTR, SAR, KYC and summary reports, reconciliation, bank health | `Banking` module | Off |
| Stablecoins | `Stablecoin` module | Off |
| P2P lending | `Lending` module and `sub_product:lending` | Off |
| Order book, external exchange, liquidity pools | `TRADING_ENABLED` (`feature:trading.enabled`) | Off |
| Direct deposits, withdrawals, reversals, custodian transfers, `POST /api/exchange/convert` | `LEDGER_DIRECT_FUNDING_ENABLED` (`feature:ledger.direct_funding_enabled`) | Off |
| Cards | `CARD_PROVIDERS_ENABLED` (empty means cards are not offered) | Closed pilot only |
| Card top-ups ("Add to card") | `CARD_TOPUPS_ENABLED` | Off |

## Authentication

Most endpoints need a Laravel Sanctum bearer token. Send it in the `Authorization: Bearer {token}` header. A token carries the abilities `read`, `write` and `delete`. A `GET` request needs `read`, a `POST`, `PUT` or `PATCH` request needs `write`, and a `DELETE` request needs `delete`.

### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password",
  "device_name": "My laptop"
}
```

`device_name` is optional. The same login is also available at `POST /api/v2/auth/login`.

### Logout
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

`POST /api/auth/logout-all` revokes every token of the user.

### Other authentication endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/auth/register` | Create a user |
| POST | `/api/auth/refresh` | Refresh a token |
| GET | `/api/auth/user`, `/api/auth/me` | Current user |
| POST | `/api/auth/2fa/enable`, `/api/auth/2fa/confirm`, `/api/auth/2fa/verify`, `/api/auth/2fa/disable`, `/api/auth/2fa/recovery-codes` | Two-factor authentication |
| POST | `/api/auth/passkey/challenge`, `/api/auth/passkey/register`, `/api/auth/passkey/authenticate`, `/api/auth/passkey/verify` | Passkeys |
| GET | `/api/auth/social/{provider}` | Start a social login |
| POST | `/api/auth/social/{provider}/callback` | Finish a social login |
| POST | `/api/v1/auth/privy-login` | Exchange a Privy token for a Sanctum token (mobile app) |

## Zelta wallet endpoints

Zelta is the hosted non-custodial wallet built on FinAegis. These endpoints serve the Zelta mobile app. The device holds the keys and signs every transaction, so the backend never sees private key material.

### Wallet and sending

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/wallet/state` | Wallet home data |
| GET | `/api/v1/wallet/balances`, `/api/v1/wallet/tokens` | Balances and supported tokens |
| GET, POST | `/api/v1/wallet/addresses` | List or register the device's public addresses |
| GET | `/api/v1/wallet/transactions`, `/api/v1/wallet/transactions/{id}` | Wallet activity |
| POST | `/api/v1/wallet/transactions/quote` | Quote a send |
| POST | `/api/v1/wallet/transactions/prepare` | Return the unsigned transaction |
| POST | `/api/v1/wallet/transactions/submit` | Accept the signed transaction |
| GET | `/api/v1/wallet/transactions/by-intent/{intentId}` | Look up a send by its intent |

Prepare takes an `Idempotency-Key` HTTP header. Submit is matched to its send by `intent_id`. A send whose result is unclear after a timeout stays submitted until the chain settles it. A second submit of the same send while the first is still running gets `409 SUBMIT_IN_PROGRESS`. Sponsored sends are capped per user and per day, and a capped request gets `429 SEND_DAILY_LIMIT_REACHED`. Ethereum mainnet sends are off by default (`WALLET_SEND_EVM_NETWORKS=polygon,base,arbitrum`), and a disabled network gets `422 NETWORK_DISABLED`.

### Bank ramp, subscriptions and cards

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/user/bridge-setup-status` | Bank ramp setup status |
| POST | `/api/v1/user/bridge-kyc-link` | Start the ramp identity check |
| POST | `/api/v1/ramp/session` | Start a ramp session (an off-ramp request gets `422 OFFRAMP_NOT_AVAILABLE`) |
| GET | `/api/v1/ramp/sessions`, `/api/v1/ramp/session/{id}`, `/api/v1/ramp/quotes`, `/api/v1/ramp/supported` | Ramp sessions, quotes and supported options |
| POST | `/api/v1/subscription/iap/verify` | Verify an App Store or Google Play purchase |
| GET | `/api/v1/subscription/me` | Current subscription |
| GET | `/api/v1/cards/onboarding` | Card onboarding form and status |
| GET, POST | `/api/v1/cards/cardholder` | Cardholder status, or become a cardholder |
| POST | `/api/v1/cards/kyc/session` | Start the card identity check |
| GET, POST | `/api/v1/cards/issued` | List or open cards |
| GET | `/api/v1/cards/issued/{card}`, `/api/v1/cards/issued/{card}/transactions` | Card details and transactions |
| POST | `/api/v1/cards/issued/{card}/freeze`, `/unfreeze`, `/cancel` | Card controls |
| GET | `/api/v1/cards/issued/{card}/sensitive` | Link to the card partner's secure page for the card number |
| GET | `/api/v1/cards/issued/{card}/3ds/code` | 3-D Secure approval code for an online payment |
| GET, POST | `/api/v1/cards/issued/{card}/topups` | List or start top-ups ("Add to card") |
| GET | `/api/v1/cards/issued/{card}/topups/estimate` | Top-up estimate |
| POST | `/api/v1/cards/issued/{card}/topups/{topup}/cancel` | Cancel a waiting top-up |

The ramp session, quote and supported-options endpoints need a completed identity check. Without one they answer `403 ERR_KYC_REQUIRED`. Virtual cards run through Lepo Finance in a closed pilot. Only the users on the pilot list (`CARD_PROVIDER_LEPO_PILOT_USERS`) are offered a card. Any card services will be provided by licensed third parties under their own terms. The 3-D Secure code is kept encrypted for at most ten minutes and is never stored in the database or in logs. Top-ups stay off while `CARD_TOPUPS_ENABLED=false`.

For the full mobile contract, see [MOBILE_API_COMPATIBILITY.md](../MOBILE_API_COMPATIBILITY.md), [FINCARD_MOBILE_INTEGRATION.md](../FINCARD_MOBILE_INTEGRATION.md), [bridge-ramp.md](../operations/bridge-ramp.md), [iap-subscriptions.md](../operations/iap-subscriptions.md) and [RAILGUN_MOBILE_INTEGRATION.md](../RAILGUN_MOBILE_INTEGRATION.md).

## Hardware Wallet

The hardware wallet endpoints connect Ledger and Trezor devices. They support the Ethereum, Bitcoin, Polygon and BSC chains. The authenticated endpoints need the `blockchain` sub-product.

### Register Device
```http
POST /api/hardware-wallet/register
Authorization: Bearer {token}
Content-Type: application/json

{
  "device_type": "ledger_nano_x",
  "device_id": "unique-device-identifier",
  "device_label": "My Ledger",
  "public_key": "04...",
  "address": "0x...",
  "chain": "ethereum",
  "derivation_path": "m/44'/60'/0'/0/0"
}
```

### Create Signing Request
```http
POST /api/hardware-wallet/signing-request
Authorization: Bearer {token}
Content-Type: application/json

{
  "association_id": "uuid-of-device-association",
  "transaction": {
    "from": "0x...",
    "to": "0x...",
    "value": "1000000000000000000",
    "chain": "ethereum",
    "data": "0x..."
  }
}
```

`transaction.gas_limit`, `gas_price`, `max_fee_per_gas`, `max_priority_fee_per_gas` and `nonce` are optional.

### Submit Signature
```http
POST /api/hardware-wallet/signing-request/{id}/submit
Authorization: Bearer {token}
Content-Type: application/json

{
  "signature": "0x...",
  "public_key": "04..."
}
```

### Get Signing Request Status
```http
GET /api/hardware-wallet/signing-request/{id}
Authorization: Bearer {token}
```

### Cancel Signing Request
```http
POST /api/hardware-wallet/signing-request/{id}/cancel
Authorization: Bearer {token}
```

### List User's Devices
```http
GET /api/hardware-wallet/associations
Authorization: Bearer {token}
```

### Remove Device
```http
DELETE /api/hardware-wallet/associations/{uuid}
Authorization: Bearer {token}
```

### Get Supported Devices (Public)
```http
GET /api/hardware-wallet/supported
```

**Response:**
```json
{
  "data": {
    "device_types": {
      "ledger_nano_s": { "name": "Ledger Nano S", "supported_chains": ["..."] },
      "ledger_nano_x": { "name": "Ledger Nano X", "supported_chains": ["..."] },
      "trezor_one": { "name": "Trezor One", "supported_chains": ["..."] },
      "trezor_model_t": { "name": "Trezor Model T", "supported_chains": ["..."] }
    },
    "chains": {
      "ethereum": { "name": "Ethereum", "coin_type": 60 },
      "bitcoin": { "name": "Bitcoin", "coin_type": 0 },
      "polygon": { "name": "Polygon", "coin_type": 60 },
      "bsc": { "name": "Binance Smart Chain", "coin_type": 60 }
    },
    "signing_request_ttl_seconds": 300
  }
}
```

## WebSocket Streaming

The platform streams events over private WebSocket channels. A client must authenticate before it can subscribe.

### Connection
```javascript
const echo = new Echo({
  broadcaster: 'pusher',
  key: 'your-pusher-key',
  cluster: 'mt1',
  authEndpoint: '/broadcasting/auth',
  auth: { headers: { Authorization: 'Bearer ' + token } }
});
```

### Channels

| Channel | Description |
|---------|-------------|
| `tenant.{tenantId}` | General tenant notifications |
| `tenant.{tenantId}.accounts` | Account balance updates |
| `tenant.{tenantId}.transactions` | Transaction status updates |
| `tenant.{tenantId}.exchange` | Order book and trade updates |
| `tenant.{tenantId}.compliance` | Compliance alerts (admin) |
| `tenant.{tenantId}.wallet.multi-sig` | Multi-signature wallet updates |
| `tenant.{tenantId}.mobile` | Mobile app updates |
| `user.{userId}`, `wallet.{userId}`, `payments.{userId}` | Per-user events |

`routes/channels.php` lists every channel and its authorisation rule.

### Subscribe to Channel
```javascript
echo.private('tenant.' + tenantId + '.transactions')
  .listen('TransactionCompleted', (e) => {
    console.log('Transaction:', e.transaction);
  });
```

## Account Management

### List Accounts
```http
GET /api/accounts
Authorization: Bearer {token}
```

### Get Account Details
```http
GET /api/accounts/{uuid}
Authorization: Bearer {token}
```

### Create Account
```http
POST /api/accounts
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Savings Account"
}
```

An account opens empty. The deprecated `initial_balance` field accepts only `0`, and any other value is rejected with `422`.

### Get Account Balances (Multi-Asset)
```http
GET /api/accounts/{uuid}/balances
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
    "balances": [
      {
        "asset_code": "USD",
        "balance": 150000,
        "formatted": "1500.00 USD",
        "asset": {
          "code": "USD",
          "name": "US Dollar",
          "type": "fiat",
          "symbol": "$",
          "precision": 2
        }
      }
    ],
    "summary": {
      "total_assets": 1,
      "total_usd_equivalent": "1,500.00"
    }
  }
}
```

`balance` is in minor units, such as cents.

### Get Transaction History
```http
GET /api/accounts/{uuid}/transactions?page=1&per_page=20
Authorization: Bearer {token}
```

### Freeze and Unfreeze
```http
POST /api/accounts/{uuid}/freeze
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Card lost"
}
```

`POST /api/accounts/{uuid}/unfreeze` takes the same body. Both need a `reason`. The owner or a platform operator can freeze an account. The owner cannot lift a freeze that staff placed.

## Asset Management

### List Assets
```http
GET /api/v2/assets
Authorization: Bearer {token}
```

`GET /api/v1/assets` returns the same list for older clients.

### Get Asset Details
```http
GET /api/v2/assets/{code}
Authorization: Bearer {token}
```

The routes `POST /api/v2/assets`, `PUT /api/v2/assets/{code}` and `DELETE /api/v2/assets/{code}` are registered, but their controller methods do not exist, so they fail. Operators manage assets in the admin panel.

## Transaction Management

Direct deposits and withdrawals move ledger balances without a real payment behind them. They exist for the demo and answer `404` while `LEDGER_DIRECT_FUNDING_ENABLED` is off. `amount` is in major units, such as dollars, and the API converts it to minor units by the asset's precision. The same endpoints exist under `/api/v2/accounts/{uuid}/...`.

### Create Deposit
```http
POST /api/accounts/{uuid}/deposit
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 100.00,
  "asset_code": "USD",
  "description": "Demo deposit"
}
```

### Create Withdrawal
```http
POST /api/accounts/{uuid}/withdraw
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 50.00,
  "asset_code": "USD",
  "description": "Demo withdrawal"
}
```

### Transaction History
`GET /api/accounts/{uuid}/transactions` lists the transactions of an account (see [Account Management](#account-management)). The routes `GET /api/v2/transactions` and `GET /api/v2/transactions/{id}` are registered, but their controller methods do not exist, so they fail.

## Transfer Operations

### Create Transfer
```http
POST /api/transfers
Authorization: Bearer {token}
Content-Type: application/json

{
  "from_account_uuid": "123e4567-e89b-12d3-a456-426614174000",
  "to_account_uuid": "987fcdeb-51a2-43d1-b890-123456789012",
  "amount": 100.00,
  "asset_code": "USD",
  "reference": "TRF-123456",
  "description": "Payment for services"
}
```

`amount` is in major units. There is no separate cross-asset transfer endpoint. A transfer moves one asset.

### Get Transfer Status
```http
GET /api/transfers/{uuid}
Authorization: Bearer {token}
```

`GET /api/accounts/{uuid}/transfers` lists the transfers of an account.

## Exchange Rates

The rate endpoints are public and read-only.

### List Rates
```http
GET /api/exchange-rates
```

### Get Current Rate
```http
GET /api/exchange-rates/{from}/{to}
```

### Convert an Amount (quote only)
```http
GET /api/exchange-rates/{from}/{to}/convert?amount=100
```

### Rate Providers

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/exchange-providers` | List rate providers |
| GET | `/api/v1/exchange-providers/{provider}/rate` | Rate from one provider |
| GET | `/api/v1/exchange-providers/compare` | Compare providers |
| GET | `/api/v1/exchange-providers/aggregated` | Aggregated rate |
| GET | `/api/v1/exchange-providers/historical` | Historical rates |
| POST | `/api/v1/exchange-providers/refresh` | Refresh rates (platform operators only) |
| POST | `/api/v1/exchange-providers/validate` | Validate a rate |

The route `POST /api/v2/exchange-rates/refresh` is registered, but its controller method does not exist, so it fails. There is no endpoint that writes a rate by hand.

## Governance & Voting

> Basket-composition polls belong to the GCU demo; any basket "votes" are simulated. See the [GCU demo disclaimer](#gcu-demo-simulated-conversions).

These endpoints belong to the `Governance` module.

### List Active Polls
```http
GET /api/voting/polls
Authorization: Bearer {token}
```

`GET /api/voting/polls/upcoming` and `GET /api/voting/polls/history` list upcoming polls and the user's past votes.

### Get Poll Details
```http
GET /api/polls/{uuid}
Authorization: Bearer {token}
```

### Submit Basket Vote
```http
POST /api/voting/polls/{uuid}/vote
Authorization: Bearer {token}
Content-Type: application/json

{
  "allocations": {
    "USD": 35,
    "EUR": 30,
    "GBP": 20,
    "CHF": 10,
    "JPY": 3,
    "XAU": 2
  }
}
```

The allocations must add up to 100. `POST /api/polls/{uuid}/vote` is the general vote endpoint for other poll types.

### Get Voting Power
```http
GET /api/polls/{uuid}/voting-power
Authorization: Bearer {token}
```

### Get Poll Results
```http
GET /api/polls/{uuid}/results
Authorization: Bearer {token}
```

### Get Voting Dashboard
```http
GET /api/voting/dashboard
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "stats": {
      "active_polls": 1,
      "votes_cast": 18,
      "gcu_balance": 25000,
      "voting_power": 25000
    },
    "next_poll": {
      "title": "Q3 2024 GCU Basket Composition",
      "starts_in": "5 days before",
      "start_date": "2024-09-01T00:00:00.000000Z"
    },
    "basket_info": {
      "name": "Global Currency Unit",
      "code": "GCU",
      "symbol": "Ǥ"
    }
  }
}
```

`next_poll` is `null` when no poll is scheduled.

### Create Poll
```http
POST /api/polls
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Q3 2024 GCU Basket Composition",
  "description": "Vote on the asset allocation for the next quarter",
  "type": "weighted_choice",
  "options": [
    {"id": "USD", "label": "US Dollar"},
    {"id": "EUR", "label": "Euro"}
  ],
  "start_date": "2024-09-01",
  "end_date": "2024-09-07",
  "voting_power_strategy": "asset_weighted_vote",
  "metadata": {"template": "monthly_basket"}
}
```

`type` is one of `single_choice`, `multiple_choice`, `weighted_choice`, `yes_no` and `ranked_choice`. A poll needs at least two options, and `start_date` must lie in the future. `voting_power_strategy` is `one_user_one_vote` or `asset_weighted_vote`. Any signed-in user can create a poll. Only platform operators can attach an `execution_workflow`. The basket vote endpoint accepts only polls whose `metadata.template` is `monthly_basket`. `POST /api/polls/{uuid}/activate` opens a poll. The `/api/votes` endpoints list, show and verify individual votes, and the `/api/v2/gcu/voting/proposals` endpoints serve the same polls in the v2 format.

## GCU demo (simulated conversions)

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

GCU endpoints operate on simulated demo balances; GCU is not issued, offered or sold and has no monetary value.

The conversion endpoints need the `Basket` module. The accepted currencies are EUR, USD, GBP and CHF.

### Simulated GCU conversion (buy side)
```http
POST /api/v2/gcu/buy
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 1000.00,
  "currency": "EUR",
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000"
}
```

Response:
```json
{
  "data": {
    "transaction_id": "550e8400-e29b-41d4-a716-446655440000",
    "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
    "spent_amount": 1000.00,
    "spent_currency": "EUR",
    "received_amount": 912.45,
    "received_currency": "GCU",
    "exchange_rate": 0.91245,
    "fee_amount": 10.00,
    "fee_currency": "EUR",
    "new_gcu_balance": 1912.45,
    "timestamp": "2024-09-02T15:30:00Z"
  },
  "message": "Successfully purchased 912.45 GCU"
}
```

### Simulated GCU conversion (sell side)
```http
POST /api/v2/gcu/sell
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 100.00,
  "currency": "EUR",
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000"
}
```

Response:
```json
{
  "data": {
    "transaction_id": "660e8400-e29b-41d4-a716-446655440001",
    "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
    "sold_amount": 100.00,
    "sold_currency": "GCU",
    "received_amount": 109.00,
    "received_currency": "EUR",
    "exchange_rate": 1.0956,
    "fee_amount": 1.10,
    "fee_currency": "EUR",
    "new_gcu_balance": 812.45,
    "timestamp": "2024-09-02T15:35:00Z"
  },
  "message": "Successfully sold 100.00 GCU"
}
```

### Get Conversion Quote (demo)
```http
GET /api/v2/gcu/quote?operation=buy&amount=1000&currency=EUR
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "operation": "buy",
    "input_amount": 1000.00,
    "input_currency": "EUR",
    "output_amount": 912.45,
    "output_currency": "GCU",
    "exchange_rate": 0.91245,
    "fee_amount": 10.00,
    "fee_currency": "EUR",
    "fee_percentage": 1.0,
    "quote_valid_until": "2024-09-02T15:35:00Z",
    "minimum_amount": 100.00,
    "maximum_amount": 1000000.00
  }
}
```

### Get Trading Limits
```http
GET /api/v2/gcu/trading-limits
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "daily_buy_limit": 10000.00,
    "daily_sell_limit": 10000.00,
    "daily_buy_used": 0,
    "daily_sell_used": 0,
    "monthly_buy_limit": 100000.00,
    "monthly_sell_limit": 100000.00,
    "monthly_buy_used": 0,
    "monthly_sell_used": 0,
    "minimum_buy_amount": 100.00,
    "minimum_sell_amount": 10.00,
    "kyc_level": 2,
    "limits_currency": "EUR"
  }
}
```

The limits depend on the user's KYC level. The `..._used` amounts are not tracked yet and are always `0`.

### Get GCU Info (Public)
```http
GET /api/v2/gcu
```

Response:
```json
{
  "data": {
    "code": "GCU",
    "name": "Global Currency Unit",
    "symbol": "Ǥ",
    "current_value": 1.0975,
    "value_currency": "USD",
    "last_rebalanced": "2024-09-01T00:00:00Z",
    "next_rebalance": "2024-09-01T00:00:00Z",
    "composition": [
      {
        "asset_code": "USD",
        "asset_name": "US Dollar",
        "weight": 0.25,
        "value_contribution": 0.2500
      }
    ],
    "statistics": {
      "total_supply": 10000000,
      "holders_count": 1234,
      "24h_change": 0.25,
      "7d_change": 1.50,
      "30d_change": 2.75
    }
  }
}
```

`GET /api/v2/gcu/composition`, `GET /api/v2/gcu/value-history` and `GET /api/v2/gcu/supported-banks` are also public.

## CGO demo module (investment-intake workflow)

> Reference software only; not an offer of securities or tokens.

The CGO demo has no REST API. It runs as authenticated web pages under `/cgo` (for example `/cgo/invest`, `/cgo/investments`, `/cgo/agreement/{investment}/download` and `/cgo/certificate/{investment}/download`). The pages need the `Cgo` module, which production Zelta disables. Card and crypto payments for the demo arrive through the shared Stripe webhook (`POST /stripe/webhook`) and `POST /api/webhooks/coinbase-commerce`. See [docs/16-CGO](../16-CGO/) for the demo flow.

## Basket API

> Basket endpoints back the GCU demo; see the [GCU demo disclaimer](#gcu-demo-simulated-conversions).

These endpoints belong to the `Basket` module.

### List Baskets

```
GET /api/v2/baskets
```

Query parameters:
- `type` (string): Filter by basket type (`fixed` or `dynamic`)
- `active` (boolean): Filter by active status

Response (a plain array):
```json
[
  {
    "code": "GCU",
    "name": "Global Currency Unit",
    "description": "Demo basket",
    "type": "dynamic",
    "rebalance_frequency": "monthly",
    "is_active": true,
    "latest_value": {
      "value": 1.0234,
      "calculated_at": "2024-06-21T10:00:00.000000Z"
    },
    "components": [
      {
        "asset_code": "USD",
        "asset_name": "US Dollar",
        "weight": 35.0,
        "min_weight": 30.0,
        "max_weight": 40.0
      }
    ]
  }
]
```

### Get Basket Details

```
GET /api/v2/baskets/{code}
```

### Get Basket Value

```
GET /api/v2/baskets/{code}/value
```

Response:
```json
{
  "basket_code": "GCU",
  "value": 1.0234,
  "calculated_at": "2024-06-21T10:00:00.000000Z",
  "component_values": { "...": "..." }
}
```

### Create Basket

```
POST /api/v2/baskets
```

Request body:
```json
{
  "code": "STABLE_BASKET",
  "name": "Stable Currency Basket",
  "type": "fixed",
  "rebalance_frequency": "monthly",
  "components": [
    {
      "asset_code": "USD",
      "weight": 40.0
    },
    {
      "asset_code": "EUR",
      "weight": 35.0
    },
    {
      "asset_code": "GBP",
      "weight": 25.0
    }
  ]
}
```

`type` is `fixed` or `dynamic`. `rebalance_frequency` is `daily`, `weekly`, `monthly`, `quarterly` or `never`. A basket needs at least two components, and the weights must add up to 100.

### Rebalance Basket

```
POST /api/v2/baskets/{code}/rebalance
```

Creating and rebalancing a basket is for platform operators only.

### Basket Performance

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v2/baskets/{code}/history` | Value history |
| GET | `/api/v2/baskets/{code}/performance` | Current performance |
| POST | `/api/v2/baskets/{code}/performance/calculate` | Recalculate performance |
| GET | `/api/v2/baskets/{code}/performance/summary` | Performance summary |
| GET | `/api/v2/baskets/{code}/performance/history` | Performance history |
| GET | `/api/v2/baskets/{code}/performance/components` | Performance per component |
| GET | `/api/v2/baskets/{code}/performance/compare` | Compare with a benchmark |
| GET | `/api/v2/baskets/{code}/performance/top-performers` | Best components |
| GET | `/api/v2/baskets/{code}/performance/worst-performers` | Worst components |

### Account Basket Operations

#### Get Account Basket Holdings

```
GET /api/v2/accounts/{uuid}/baskets
```

#### Decompose Basket

```
POST /api/v2/accounts/{uuid}/baskets/decompose
```

Request body:
```json
{
  "basket_code": "GCU",
  "amount": 10000
}
```

#### Compose Basket

```
POST /api/v2/accounts/{uuid}/baskets/compose
```

Request body:
```json
{
  "basket_code": "GCU",
  "amount": 10000
}
```

## Compliance API

The compliance endpoints are readiness tooling. They help an operator build Know Your Customer (KYC) and data protection processes, and they do not certify a deployment.

### KYC Management

#### Get KYC Status

```
GET /api/compliance/kyc/status
```

Response:
```json
{
  "status": "approved",
  "level": "enhanced",
  "submitted_at": "2024-06-20T10:00:00Z",
  "approved_at": "2024-06-20T11:00:00Z",
  "expires_at": "2027-06-20T11:00:00Z",
  "needs_kyc": false,
  "documents": [
    {
      "id": "123",
      "type": "passport",
      "status": "verified",
      "uploaded_at": "2024-06-20T10:00:00Z"
    }
  ]
}
```

#### Get KYC Requirements

```
GET /api/compliance/kyc/requirements?level=enhanced
```

#### Submit KYC Documents

```
POST /api/compliance/kyc/submit
```

Request body (multipart/form-data):
```
documents[0][type]=passport
documents[0][file]=@passport.jpg
documents[1][type]=selfie
documents[1][file]=@selfie.jpg
```

#### Other KYC endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/compliance/kyc/documents` | Upload one document |
| GET | `/api/compliance/kyc/documents/{documentId}/download` | Download a document |
| POST | `/api/compliance/kyc/ondato/start` | Start an Ondato identity check |
| GET | `/api/compliance/kyc/ondato/status/{verificationId}` | Ondato check status |
| GET | `/api/v2/compliance/kyc/status` | KYC status in the v2 format |
| POST | `/api/v2/compliance/kyc/start` | Start a v2 verification |
| POST | `/api/v2/compliance/kyc/{verificationId}/document`, `/api/v2/compliance/kyc/{verificationId}/selfie` | Upload a document or selfie for a v2 verification |

### GDPR API

#### Get Consent Status

```
GET /api/compliance/gdpr/consent
```

#### Update Consent

```
POST /api/compliance/gdpr/consent
```

Request body:
```json
{
  "marketing": true,
  "data_retention": true
}
```

#### Request Data Export

```
POST /api/compliance/gdpr/export
```

`GET /api/compliance/gdpr/export/{exportId}` shows the export status and `GET /api/compliance/gdpr/export/{exportId}/download` downloads it.

#### Request Account Deletion

```
POST /api/compliance/gdpr/delete
```

Request body:
```json
{
  "confirm": true,
  "reason": "No longer using the service"
}
```

`GET /api/compliance/gdpr/retention-policy` returns the retention policy. The `/api/compliance/gdpr/v2/...` endpoints manage consent records, breach records, data protection impact assessments, the processing register and retention policies.

## Custodian Integration

Custodian connectors are integration adapters for third-party APIs; no partnership or endorsement implied.

### List Custodians
```http
GET /api/custodians
Authorization: Bearer {token}
```

### Get Custodian Account Info
```http
GET /api/custodians/{custodian}/account-info?account_id={account_id}
Authorization: Bearer {token}
```

### Get Custodian Balance
```http
GET /api/custodians/{custodian}/balance?account_id={account_id}&asset_code={asset}
Authorization: Bearer {token}
```

### Get Custodian Transactions
```http
GET /api/custodians/{custodian}/transactions?account_id={account_id}&limit=100
Authorization: Bearer {token}
```

`GET /api/custodians/{custodian}/transactions/{transactionId}` returns one transaction.

### Initiate Custodian Transfer
```http
POST /api/custodians/{custodian}/transfer
Authorization: Bearer {token}
Content-Type: application/json

{
  "internal_account_uuid": "123e4567-e89b-12d3-a456-426614174000",
  "custodian_account_id": "custodian-account-id",
  "asset_code": "EUR",
  "amount": 100.50,
  "direction": "deposit",
  "reference": "Invoice payment"
}
```

`direction` is `deposit` or `withdraw`, and `amount` is in major units. Only the owner of the internal account can move money in or out of it. The custodian account must be linked to an account the caller owns, and platform operators can read any custodian account. The transfer endpoint answers `404` while `LEDGER_DIRECT_FUNDING_ENABLED` is off. Custodian health is under [Bank Health and Alerting](#bank-health-and-alerting).

## Webhooks

Outgoing webhook subscriptions are managed through the v2 API. Every subscription endpoint except the event list is for platform administrators only.

### List Event Types (Public)
```http
GET /api/v2/webhooks/events
```

### Register Webhook
```http
POST /api/v2/webhooks
Authorization: Bearer {token}
Content-Type: application/json

{
  "url": "https://example.com/webhook",
  "events": ["transaction.created", "transfer.completed"],
  "description": "Ledger events for the reporting service",
  "is_active": true
}
```

The URL must use `https://` and must not resolve to a private or internal address. The response shows the signing secret once, at creation. The event list accepts more names than the platform sends today. [WEBHOOK_INTEGRATION.md](WEBHOOK_INTEGRATION.md) lists the events that are actually sent.

### List Webhooks
```http
GET /api/v2/webhooks
Authorization: Bearer {token}
```

### Update, Delete and Inspect Deliveries
```http
PUT /api/v2/webhooks/{id}
DELETE /api/v2/webhooks/{id}
GET /api/v2/webhooks/{id}/deliveries
Authorization: Bearer {token}
```

[WEBHOOK_INTEGRATION.md](WEBHOOK_INTEGRATION.md) explains the payloads and how to check their signatures. Incoming provider webhooks are described in the operations runbooks under `docs/operations/`.

## Bank Allocation

Bank allocation lets a user spread a balance across several banks in the demo. It belongs to the `Banking` module. Bank connectors are integration adapters for third-party APIs; no partnership or endorsement implied. Bank names below are placeholders.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/bank-allocations` | Current allocation of the user |
| PUT | `/api/bank-allocations` | Replace the allocation |
| POST | `/api/bank-allocations/banks` | Add a bank |
| DELETE | `/api/bank-allocations/banks/{bankCode}` | Remove a bank |
| PUT | `/api/bank-allocations/primary/{bankCode}` | Set the primary bank |
| GET | `/api/bank-allocations/available-banks` | Banks that can be chosen |
| POST | `/api/bank-allocations/distribution-preview` | Preview how an amount would be split |

### Update Allocation
```http
PUT /api/bank-allocations
Authorization: Bearer {token}
Content-Type: application/json

{
  "allocations": {
    "EXAMPLE_BANK_A": 60,
    "EXAMPLE_BANK_B": 40
  },
  "primary_bank": "EXAMPLE_BANK_A"
}
```

### Add a Bank
```http
POST /api/bank-allocations/banks
Authorization: Bearer {token}
Content-Type: application/json

{
  "bank_code": "EXAMPLE_BANK_C",
  "percentage": 20
}
```

### Preview Distribution
```http
POST /api/bank-allocations/distribution-preview
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 10000,
  "asset_code": "EUR"
}
```

The `bank_code` values must be keys of `UserBankPreference::AVAILABLE_BANKS`.

## Batch Operations

Batch operations run system jobs such as interest, fee collection and reconciliation in one request. They belong to the `Banking` module and are for platform operators only.

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/batch-operations/execute` | Start a batch |
| GET | `/api/batch-operations/{batchId}/status` | Batch status |
| GET | `/api/batch-operations` | Batch history |
| POST | `/api/batch-operations/{batchId}/cancel` | Cancel a batch |

### Start a Batch
```http
POST /api/batch-operations/execute
Authorization: Bearer {token}
Content-Type: application/json

{
  "batch_name": "Month-end run",
  "operations": [
    { "type": "fee_collection", "parameters": {} },
    { "type": "balance_reconciliation", "parameters": {} }
  ]
}
```

The allowed operation types are `account_interest`, `fee_collection`, `balance_reconciliation`, `report_generation` and `maintenance_tasks`. `schedule_time` (format `Y-m-d H:i:s`) is optional. Cancelling a batch needs a `reason`.

## Transaction Reversal

A reversal books the opposite of an earlier debit or credit. Creating one answers `404` while `LEDGER_DIRECT_FUNDING_ENABLED` is off.

### Reverse a Transaction
```http
POST /api/accounts/{uuid}/transactions/reverse
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 100.00,
  "asset_code": "USD",
  "transaction_type": "debit",
  "reversal_reason": "Duplicate charge",
  "original_transaction_id": "txn_123"
}
```

### Reversal History and Status
```http
GET /api/accounts/{uuid}/transactions/reversals
GET /api/transactions/reversals/{reversalId}/status
Authorization: Bearer {token}
```

## Regulatory Reporting

The regulatory reporting endpoints generate draft reports for an operator's own compliance team. They are for compliance staff only. They do not file anything with a regulator.

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/regulatory/reports/ctr` | Generate a currency transaction report |
| POST | `/api/regulatory/reports/sar-candidates` | List candidates for a suspicious activity report |
| POST | `/api/regulatory/reports/compliance-summary` | Generate a compliance summary |
| POST | `/api/regulatory/reports/kyc` | Generate a KYC report |
| GET | `/api/regulatory/reports` | List generated reports |
| GET | `/api/regulatory/reports/{filename}/download` | Download a report file |
| DELETE | `/api/regulatory/reports/{filename}` | Delete a report |
| GET | `/api/regulatory/metrics` | Reporting metrics |

`routes/api/regulatory.php` also registers `/api/regulatory/reports/generate`, `/templates`, `/jurisdictions`, `/requirements`, `/deadlines`, `/filings`, `/reports/{id}/submit`, `/reports/{id}/amend` and `/reports/{id}/status`, plus the `/api/sar/...` and `/api/ctr/...` routes. Their controller methods do not exist, so these routes fail. The one exception is `POST /api/ctr/generate`, which runs the same report as `POST /api/regulatory/reports/ctr`. The `GET /api/regulatory/reports/{id}` route from that file also catches requests meant for `GET /api/regulatory/reports/{filename}`, so showing a single report fails. Downloading a report works.

The `/api/regtech/...` endpoints cover regulation checks such as MiCA, MiFID and the travel rule.

## Daily Reconciliation

Daily reconciliation compares ledger balances with custodian balances. It belongs to the `Banking` module and is for platform operators only.

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/reconciliation/trigger` | Run a reconciliation now |
| GET | `/api/reconciliation/latest` | Latest report |
| GET | `/api/reconciliation/reports/{date}` | Report for one date |
| GET | `/api/reconciliation/history` | Past reports |
| GET | `/api/reconciliation/metrics` | Reconciliation metrics |
| GET | `/api/reconciliation/status` | Current status |

## Bank Health and Alerting

These endpoints check the health of custodian and bank connectors and manage the alerts they raise. They belong to the `Banking` module and are for platform operators only.

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/bank-health/check` | Run a health check |
| GET | `/api/bank-health/status` | Health of all connectors |
| GET | `/api/bank-health/custodians/{custodian}` | Health of one custodian |
| GET | `/api/bank-health/alerts/{custodian}/history` | Alert history of one custodian |
| GET | `/api/bank-health/alerts/stats` | Alert statistics |
| GET, PUT | `/api/bank-health/alerts/config` | Read or change the alert settings |
| POST | `/api/bank-health/alerts/test` | Send a test alert |
| POST | `/api/bank-health/alerts/{alertId}/acknowledge` | Acknowledge an alert |

## Stablecoin Operations

These endpoints belong to the `Stablecoin` demo module and need the `stablecoins` sub-product. Minting, burning, adding collateral and running liquidations also need `LEDGER_DIRECT_FUNDING_ENABLED` to be on. The risk and liquidation endpoints, and the endpoints that create or control a stablecoin, are for stablecoin operators only.

### List Stablecoins
```http
GET /api/v2/stablecoins
Authorization: Bearer {token}
```

`GET /api/v2/stablecoins/{code}`, `GET /api/v2/stablecoins/metrics`, `GET /api/v2/stablecoins/health` and `GET /api/v2/stablecoins/{code}/collateral-distribution` return details, metrics and health.

### Get Stablecoin Metrics
```http
GET /api/v2/stablecoins/{code}/metrics
Authorization: Bearer {token}
```

### Mint Stablecoins
```http
POST /api/v2/stablecoin-operations/mint
Authorization: Bearer {token}
Content-Type: application/json

{
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
  "stablecoin_code": "USDX",
  "collateral_asset_code": "USD",
  "collateral_amount": 150000,
  "mint_amount": 100000
}
```

### Other Stablecoin Operations

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/v2/stablecoin-operations/burn` | Burn stablecoins and release collateral |
| POST | `/api/v2/stablecoin-operations/add-collateral` | Add collateral to a position |
| GET | `/api/v2/stablecoin-operations/accounts/{accountUuid}/positions` | Positions of an account |
| GET | `/api/v2/stablecoin-operations/positions/{positionUuid}` | One position |
| GET | `/api/v2/stablecoin-operations/positions/at-risk` | Positions close to liquidation |
| GET | `/api/v2/stablecoin-operations/liquidation/opportunities` | Positions that can be liquidated |
| POST | `/api/v2/stablecoin-operations/liquidation/execute` | Run liquidations |
| POST | `/api/v2/stablecoin-operations/liquidation/positions/{positionUuid}` | Liquidate one position |
| GET | `/api/v2/stablecoin-operations/liquidation/positions/{positionUuid}/reward` | Liquidation reward of a position |
| POST | `/api/v2/stablecoin-operations/simulation/{stablecoinCode}/mass-liquidation` | Simulate a mass liquidation |
| POST, PUT | `/api/v2/stablecoins`, `/api/v2/stablecoins/{code}` | Create or change a stablecoin |
| POST | `/api/v2/stablecoins/{code}/deactivate`, `/reactivate`, `/execute-stability` | Stablecoin controls |

## Exchange & Trading

The order book is part of the demo trading engine. It answers `404` while `TRADING_ENABLED` is off, and production Zelta keeps it off. Each response below is the shape the controller returns today. The OpenAPI document at `/api/documentation` has the full schemas.

### List Trading Pairs
```http
GET /api/exchange/markets
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "pair": "BTC/EUR",
      "last_price": "45678.90",
      "volume_24h": "125.5",
      "change_24h_percentage": "1.2",
      "high_24h": "46000.00",
      "low_24h": "45000.00",
      "base_currency": "BTC",
      "quote_currency": "EUR"
    }
  ]
}
```

The pairs come from the active liquidity pools. Without an active pool the list falls back to BTC/EUR and ETH/EUR.

### Get Order Book
```http
GET /api/exchange/orderbook/{baseCurrency}/{quoteCurrency}?depth=20
```

`depth` defaults to 20 and is capped at 100.

Response:
```json
{
  "pair": "BTC/EUR",
  "bids": [...],
  "asks": [...],
  "spread": "10.00",
  "spread_percentage": "0.02",
  "mid_price": "45605.00",
  "last_price": "45600.00",
  "volume_24h": "125.5",
  "high_24h": "46000.00",
  "low_24h": "45000.00",
  "change_24h": "500.00",
  "change_24h_percentage": "1.1",
  "updated_at": "2024-09-07T10:00:00+00:00"
}
```

`bids` and `asks` list the resting orders on each side. For a pair without an order book the price fields are `null` and both lists are empty.

### Place Order
```http
POST /api/exchange/orders
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: {unique-key}

{
  "type": "buy",
  "order_type": "limit",
  "base_currency": "BTC",
  "quote_currency": "USD",
  "amount": "0.5",
  "price": "45000"
}
```

`type` is `buy` or `sell`, and `order_type` is `market` or `limit`. `price` is required for a limit order. `stop_price` is optional.

Response:
```json
{
  "success": true,
  "order_id": "ord_123456",
  "message": "Order placed successfully",
  "workflow_id": "workflow-id"
}
```

### Place Market Order
```http
POST /api/exchange/orders
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: {unique-key}

{
  "type": "sell",
  "order_type": "market",
  "base_currency": "BTC",
  "quote_currency": "USD",
  "amount": "0.1"
}
```

### Cancel Order
```http
DELETE /api/exchange/orders/{orderId}
Authorization: Bearer {token}
```

Response:
```json
{
  "success": true,
  "message": "Order cancelled successfully"
}
```

### List Orders
```http
GET /api/exchange/orders?status=open&base_currency=BTC&quote_currency=USD
Authorization: Bearer {token}
```

`status` can be `open`, `all` or a single order status. The result is a standard Laravel page of 20 orders. There is no endpoint that returns one order by id.

### Get Trade History
```http
GET /api/exchange/trades?base_currency=BTC&quote_currency=USD
Authorization: Bearer {token}
```

The result is a standard Laravel page of 20 trades. Each trade has `trade_id`, `buy_order_id`, `sell_order_id`, `base_currency`, `quote_currency`, `price`, `amount`, `value`, `maker_fee`, `taker_fee` and `maker_side`.

The API has no market statistics or candle endpoints.

## External Exchange Integration

These endpoints read prices from connected external exchanges. They are integration adapters for third-party APIs; no partnership or endorsement implied. They answer `404` while `TRADING_ENABLED` is off.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/external-exchange/connectors` | Configured connectors and their status |
| GET | `/api/external-exchange/ticker/{base}/{quote}` | Ticker from each connector |
| GET | `/api/external-exchange/orderbook/{base}/{quote}` | Order books from each connector |
| GET | `/api/external-exchange/arbitrage/{base}/{quote}` | Price differences between connectors (authenticated) |

The API reads prices only. It does not execute arbitrage trades and has no manual price sync endpoint.

## Liquidity Pools

> Reference module. Example values (APY, rewards, fees) are sample data, not an offer of returns.

The liquidity pool endpoints are part of the demo trading engine. They answer `404` while `TRADING_ENABLED` is off, and production Zelta keeps them off.

The add liquidity, remove liquidity and rebalance flow was removed in v7.18.0, because it never worked. Pool lists, pool creation, swaps, positions, rewards, impermanent loss protection and analytics remain.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/liquidity/pools` | List active pools with metrics |
| GET | `/api/liquidity/pools/{poolId}` | Pool details |
| POST | `/api/liquidity/pools` | Create a pool |
| POST | `/api/liquidity/swap` | Swap through a pool |
| GET | `/api/liquidity/positions` | Positions of the user |
| POST | `/api/liquidity/claim-rewards` | Claim rewards in a pool |
| GET | `/api/liquidity/il-protection/{positionId}` | Impermanent loss of a position |
| POST | `/api/liquidity/il-protection/enable` | Enable impermanent loss protection for a pool |
| POST | `/api/liquidity/il-protection/process-claims` | Process protection claims for a pool |
| GET | `/api/liquidity/il-protection/fund-requirements/{poolId}` | Protection fund requirements |
| GET | `/api/liquidity/analytics/{poolId}` | Pool analytics |

Everything except the two pool reads needs authentication.

### List All Pools
```http
GET /api/liquidity/pools
```

Response:
```json
{
  "pools": [
    {
      "pool_id": "pool-uuid",
      "base_currency": "BTC",
      "quote_currency": "EUR",
      "base_reserve": "10",
      "quote_reserve": "400000",
      "total_shares": "2000",
      "fee_rate": "0.003",
      "spot_price": "40000",
      "tvl": "800000",
      "volume_24h": "150000",
      "fees_24h": "450",
      "apy": "12.5",
      "provider_count": 45
    }
  ]
}
```

### Create Pool
```http
POST /api/liquidity/pools
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: {unique-key}

{
  "base_currency": "ETH",
  "quote_currency": "USD",
  "fee_rate": "0.003"
}
```

`fee_rate` is optional and must be between 0.0001 and 0.01.

### Execute Swap
```http
POST /api/liquidity/swap
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: {unique-key}

{
  "pool_id": "pool-uuid",
  "input_currency": "USD",
  "input_amount": "1000",
  "min_output_amount": "0.021"
}
```

### Claim Rewards
```http
POST /api/liquidity/claim-rewards
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: {unique-key}

{
  "pool_id": "pool-uuid"
}
```

### Enable Impermanent Loss Protection
```http
POST /api/liquidity/il-protection/enable
Authorization: Bearer {token}
Content-Type: application/json

{
  "pool_id": "pool-uuid",
  "protection_threshold": 0.02,
  "max_coverage": 0.8,
  "min_holding_hours": 168,
  "fund_size": 10000
}
```

Only `pool_id` is required. The analytics endpoint returns the pool metrics. Its `price_history` field is placeholder data that repeats the current spot price.

Two scheduled commands also act on pools. `liquidity:distribute-rewards` distributes rewards and `liquidity:update-market-making` refreshes market-making orders. They are scheduled only while `TRADING_ENABLED` is on.

## P2P Lending

> Reference module. Example values (interest rates, repayments) are sample data, not an offer of credit or returns.

The lending endpoints belong to the `Lending` demo module and need the `lending` sub-product.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/lending/applications` | Loan applications of the user |
| POST | `/api/lending/applications` | Apply for a loan |
| GET | `/api/lending/applications/{id}` | One application |
| POST | `/api/lending/applications/{id}/cancel` | Cancel an application |
| GET | `/api/lending/loans` | Loans of the user |
| GET | `/api/lending/loans/{id}` | One loan |
| POST | `/api/lending/loans/{id}/payments` | Make a repayment |
| GET | `/api/lending/loans/{id}/settlement-quote` | Quote for early settlement |
| POST | `/api/lending/loans/{id}/settle` | Confirm early settlement |

The write endpoints take an `Idempotency-Key` header. The API has no lender marketplace, credit score, refinancing or default reporting endpoints.

## Blockchain Wallets

These endpoints manage server-side HD wallets for the FinAegis demo. They need the `blockchain` sub-product. The Zelta app does not use them, because Zelta is non-custodial and the device holds the keys (see [Zelta wallet endpoints](#zelta-wallet-endpoints)).

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/blockchain-wallets` | Wallets of the user |
| POST | `/api/blockchain-wallets` | Create a wallet |
| GET | `/api/blockchain-wallets/{walletId}` | One wallet |
| PUT | `/api/blockchain-wallets/{walletId}` | Update a wallet |
| GET | `/api/blockchain-wallets/{walletId}/addresses` | Addresses of a wallet |
| POST | `/api/blockchain-wallets/{walletId}/addresses` | Generate an address |
| GET | `/api/blockchain-wallets/{walletId}/transactions` | Transactions of a wallet |
| POST | `/api/blockchain-wallets/{walletId}/backup` | Create a backup |
| POST | `/api/blockchain-wallets/generate-mnemonic` | Generate a mnemonic |

The `/api/multi-sig/...` endpoints manage multi-signature wallets and their approval requests.

## Error Responses

A validation error returns `422` with the Laravel error format. On API routes the exception handler adds an `error` code, such as `VALIDATION_ERROR`, `UNAUTHENTICATED` or `NOT_FOUND`, and a `request_id` taken from the `X-Request-ID` header:

```json
{
  "message": "The amount field must be at least 0.01. (and 1 more error)",
  "errors": {
    "amount": ["The amount field must be at least 0.01."],
    "asset_code": ["The selected asset code is invalid."]
  },
  "error": "VALIDATION_ERROR",
  "request_id": null
}
```

Many newer endpoints, including the Zelta wallet endpoints, return their own machine-readable code:

```json
{
  "success": false,
  "error": {
    "code": "SUBMIT_IN_PROGRESS",
    "message": "This transfer is already being submitted."
  }
}
```

Common HTTP status codes:
- `200 OK` - Successful request
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Missing or invalid authentication
- `403 Forbidden` - Insufficient permissions or token abilities
- `404 Not Found` - Resource not found, or a feature flag switches the route off
- `409 Conflict` - The request conflicts with work in progress
- `422 Unprocessable Entity` - Validation errors
- `429 Too Many Requests` - Rate limit or daily cap reached
- `500 Internal Server Error` - Server error

## Rate Limiting

Rate limits are set per endpoint type in `config/rate_limiting.php`. The defaults are shown below, and each value can be changed through the environment variable in the second column.

| Type | Default limit per 60 seconds | Variable |
|------|------------------------------|----------|
| `auth` | 3 | `RATE_LIMIT_AUTH` |
| `transaction` | 30 | `RATE_LIMIT_TRANSACTION` |
| `query` | 100 | `RATE_LIMIT_QUERY` |
| `admin` | 200 | `RATE_LIMIT_ADMIN` |
| `public` | 60 | `RATE_LIMIT_PUBLIC` |
| `webhook` | 1000 | `RATE_LIMIT_WEBHOOK` |

Transactions such as deposits, withdrawals and transfers also have hourly and daily count and amount limits. Responses carry these headers:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1625097600
X-RateLimit-Window: 60
```

A blocked request also gets `Retry-After`.

## Pagination

List endpoints support pagination with these parameters:
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

Paginated responses include metadata:
```json
{
  "data": [...],
  "links": {
    "first": "https://your-finaegis-host.example/accounts?page=1",
    "last": "https://your-finaegis-host.example/accounts?page=10",
    "prev": null,
    "next": "https://your-finaegis-host.example/accounts?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

## API Versioning

The API uses URL prefixes. The original endpoints live under `/api/`, the public developer API under `/api/v2/`, and the mobile and newer product endpoints under `/api/v1/`. The prefixes mark different surfaces rather than a strict old and new order. Older `/api/v1/` aliases such as `/api/v1/assets` and `/api/v1/exchange-rates` stay available.

---

**Last Updated**: 2026-11-24
**Platform Version**: v7.18.1
