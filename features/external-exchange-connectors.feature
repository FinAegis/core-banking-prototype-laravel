Feature: External Exchange Connectors
  As a trading platform
  I need to connect to external exchanges
  So that I can provide liquidity and arbitrage opportunities

  Background:
    Given the following currencies exist:
      | code | name               | symbol | type   | is_active |
      | BTC  | Bitcoin            | ₿      | crypto | true      |
      | EUR  | Euro               | €      | fiat   | true      |
      | USD  | US Dollar          | $      | fiat   | true      |
    And the following external exchanges are configured:
      | name    | enabled | api_configured |
      | binance | true    | false          |
      | kraken  | true    | false          |

  Scenario: List available external exchange connectors
    When I request "GET /api/external-exchange/connectors"
    Then the response status should be 200
    And the response should contain:
      """
      {
        "connectors": [
          {
            "name": "binance",
            "display_name": "Binance",
            "available": true
          },
          {
            "name": "kraken",
            "display_name": "Kraken",
            "available": true
          }
        ]
      }
      """

  Scenario: Get aggregated ticker data from external exchanges
    Given the external exchange "binance" returns ticker for "BTC/EUR":
      | bid   | ask   | last  | volume_24h |
      | 48000 | 48100 | 48050 | 1234.5     |
    And the external exchange "kraken" returns ticker for "BTC/EUR":
      | bid   | ask   | last  | volume_24h |
      | 47950 | 48150 | 48075 | 987.6      |
    When I request "GET /api/external-exchange/ticker/BTC/EUR"
    Then the response status should be 200
    And the response should contain:
      """
      {
        "pair": "BTC/EUR",
        "best_bid": {
          "price": "48000",
          "exchange": "binance"
        },
        "best_ask": {
          "price": "48100",
          "exchange": "binance"
        }
      }
      """

  Scenario: Get aggregated order book from external exchanges
    Given the external exchange "binance" has order book for "BTC/EUR":
      | side | price | amount |
      | bid  | 48000 | 0.5    |
      | bid  | 47990 | 1.0    |
      | ask  | 48100 | 0.3    |
      | ask  | 48110 | 0.7    |
    And the external exchange "kraken" has order book for "BTC/EUR":
      | side | price | amount |
      | bid  | 47995 | 0.8    |
      | bid  | 47985 | 1.2    |
      | ask  | 48105 | 0.4    |
      | ask  | 48115 | 0.6    |
    When I request "GET /api/external-exchange/orderbook/BTC/EUR?depth=3"
    Then the response status should be 200
    And the aggregated order book should contain:
      | side | price | amount | exchange |
      | bid  | 48000 | 0.5    | binance  |
      | bid  | 47995 | 0.8    | kraken   |
      | bid  | 47990 | 1.0    | binance  |
      | ask  | 48100 | 0.3    | binance  |
      | ask  | 48105 | 0.4    | kraken   |
      | ask  | 48110 | 0.7    | binance  |

  @auth
  Scenario: Check arbitrage opportunities
    Given I am logged in as "trader@example.com"
    And the internal exchange has order book for "BTC/EUR":
      | side | price | amount |
      | bid  | 48200 | 1.0    |
      | ask  | 48300 | 1.0    |
    And the external exchange "binance" returns ticker for "BTC/EUR":
      | bid   | ask   |
      | 48000 | 48100 |
    When I request "GET /api/external-exchange/arbitrage/BTC/EUR"
    Then the response status should be 200
    And the response should contain arbitrage opportunity:
      """
      {
        "type": "buy_external_sell_internal",
        "external_exchange": "binance",
        "external_price": "48100",
        "internal_price": "48200",
        "spread": "100",
        "spread_percent": "0.207"
      }
      """

  Scenario: External exchange connector fails gracefully
    Given the external exchange "binance" is unavailable
    When I request "GET /api/external-exchange/ticker/BTC/EUR"
    Then the response status should be 200
    And the response should only contain data from "kraken"

  Scenario: Handle unsupported trading pair
    When I request "GET /api/external-exchange/ticker/XXX/YYY"
    Then the response status should be 200
    And the response should contain empty tickers

  @mock
  Scenario: Provide external liquidity to internal order book
    Given I am the system account
    And the internal order book for "BTC/EUR" has:
      | side | orders_count |
      | bid  | 2            |
      | ask  | 1            |
    And the external exchange "binance" returns ticker for "BTC/EUR":
      | bid   | ask   |
      | 48000 | 48100 |
    When the liquidity service provides liquidity for "BTC/EUR"
    Then the internal order book should have:
      | side | orders_count |
      | bid  | 7            |
      | ask  | 6            |
    And liquidity orders should be placed with:
      | side | price_range        | source             |
      | buy  | 47760.00-47952.00  | external_liquidity |
      | sell | 48148.10-48244.05  | external_liquidity |

  @mock
  Scenario: Align internal prices with external markets
    Given I am the system account
    And the external exchanges have weighted average prices for "BTC/EUR":
      | bid_avg | ask_avg |
      | 48000   | 48100   |
    When the price alignment service runs for "BTC/EUR"
    Then market making orders should be placed:
      | side | price    | amount | source        |
      | buy  | 47995.00 | 0.5    | market_making |
      | sell | 48105.00 | 0.5    | market_making |