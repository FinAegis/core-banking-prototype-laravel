Feature: Exchange Trading
  In order to trade digital assets
  As a user with an account
  I need to be able to place and manage orders

  Background:
    Given the following assets exist:
      | code | name         | type   | precision | is_active | is_tradeable |
      | EUR  | Euro         | fiat   | 2         | true      | true         |
      | BTC  | Bitcoin      | crypto | 8         | true      | true         |
      | ETH  | Ethereum     | crypto | 18        | true      | true         |
      | USD  | US Dollar    | fiat   | 2         | true      | true         |
      | GCU  | Global Currency Unit | basket | 2    | true      | true         |
    And the following exchange rates exist:
      | from | to  | rate     |
      | BTC  | EUR | 50000.00 |
      | ETH  | EUR | 3000.00  |
      | USD  | EUR | 0.92     |
      | GCU  | EUR | 1.05     |
    And the following user exists:
      | name          | email              | password |
      | Alice Trader  | alice@example.com  | password |
    And the following account exists:
      | user              | type              | currency | balance    | is_active |
      | alice@example.com | customer_retail   | EUR      | 100000.00  | true      |
    And the following asset balances exist for account "alice@example.com":
      | asset | balance      |
      | BTC   | 2.50000000   |
      | ETH   | 50.000000000 |
      | USD   | 5000.00      |
      | GCU   | 10000.00     |

  Scenario: Place a limit buy order
    Given I am logged in as "alice@example.com"
    When I place a limit buy order for "0.1" "BTC" at "48000" "EUR"
    Then the order should be placed successfully
    And the order should have status "open"
    And my "EUR" balance should be reduced by "4800.00"
    And my locked "EUR" balance should be "4800.00"

  Scenario: Place a market sell order
    Given I am logged in as "alice@example.com"
    When I place a market sell order for "1.0" "ETH" for "EUR"
    Then the order should be placed successfully
    And my "ETH" balance should be reduced by "1.0"
    And my locked "ETH" balance should be "1.0"

  Scenario: Cancel an open order
    Given I am logged in as "alice@example.com"
    And I have placed a limit buy order for "0.1" "BTC" at "48000" "EUR"
    When I cancel the order
    Then the order should have status "cancelled"
    And my locked "EUR" balance should be "0.00"
    And my available "EUR" balance should be restored

  Scenario: Order matching
    Given the following user exists:
      | name        | email            | password |
      | Bob Seller  | bob@example.com  | password |
    And the following account exists:
      | user            | type              | currency | balance   | is_active |
      | bob@example.com | customer_retail   | EUR      | 10000.00  | true      |
    And the following asset balances exist for account "bob@example.com":
      | asset | balance    |
      | BTC   | 1.00000000 |
    And I am logged in as "alice@example.com"
    And I have placed a limit buy order for "0.5" "BTC" at "49000" "EUR"
    When user "bob@example.com" places a limit sell order for "0.5" "BTC" at "49000" "EUR"
    Then both orders should be matched
    And the trade should be executed at price "49000" "EUR"
    And "alice@example.com" should receive "0.5" "BTC"
    And "bob@example.com" should receive "24500" "EUR" minus fees
    And both orders should have status "filled"

  Scenario: Partial order filling
    Given the following user exists:
      | name        | email            | password |
      | Bob Seller  | bob@example.com  | password |
    And the following account exists:
      | user            | type              | currency | balance   | is_active |
      | bob@example.com | customer_retail   | EUR      | 10000.00  | true      |
    And the following asset balances exist for account "bob@example.com":
      | asset | balance    |
      | BTC   | 1.00000000 |
    And I am logged in as "alice@example.com"
    And I have placed a limit buy order for "1.0" "BTC" at "48000" "EUR"
    When user "bob@example.com" places a market sell order for "0.3" "BTC" for "EUR"
    Then the sell order should be fully filled
    And the buy order should be partially filled with "0.3" "BTC"
    And the buy order should have status "partially_filled"
    And the remaining amount for the buy order should be "0.7" "BTC"

  Scenario: View order book
    Given the following open orders exist:
      | user              | type | order_type | base | quote | amount | price |
      | alice@example.com | buy  | limit      | BTC  | EUR   | 0.5    | 48000 |
      | alice@example.com | buy  | limit      | BTC  | EUR   | 1.0    | 47500 |
      | bob@example.com   | sell | limit      | BTC  | EUR   | 0.3    | 49000 |
      | bob@example.com   | sell | limit      | BTC  | EUR   | 0.7    | 49500 |
    When I view the order book for "BTC/EUR"
    Then I should see the following buy orders:
      | price | amount | total  |
      | 48000 | 0.5    | 24000  |
      | 47500 | 1.0    | 47500  |
    And I should see the following sell orders:
      | price | amount | total  |
      | 49000 | 0.3    | 14700  |
      | 49500 | 0.7    | 34650  |
    And the spread should be "1000" "EUR"
    And the best bid should be "48000" "EUR"
    And the best ask should be "49000" "EUR"

  Scenario: Fee calculation based on volume
    Given I am logged in as "alice@example.com"
    And my 30-day trading volume is "0" "EUR"
    When I place a market buy order for "0.1" "BTC" for "EUR"
    And the order is filled at "50000" "EUR"
    Then I should be charged a taker fee of "0.2%" which is "10" "EUR"
    And my total cost should be "5010" "EUR"

  Scenario: Trading pair restrictions
    Given I am logged in as "alice@example.com"
    And the asset "XRP" is not tradeable
    When I try to place an order for "XRP/EUR"
    Then I should get an error "Currency pair not available for trading"

  Scenario: Minimum order validation
    Given I am logged in as "alice@example.com"
    When I try to place a buy order for "0.00001" "BTC" at "50000" "EUR"
    Then I should get an error "Minimum order amount is 0.0001 BTC"

  Scenario: Insufficient balance
    Given I am logged in as "alice@example.com"
    And my "EUR" balance is "100.00"
    When I try to place a buy order for "1.0" "BTC" at "50000" "EUR"
    Then I should get an error "Insufficient EUR balance"