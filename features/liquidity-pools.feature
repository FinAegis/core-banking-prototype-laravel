Feature: Liquidity Pool Management
  As a liquidity provider
  I want to provide liquidity to pools
  So that I can earn trading fees and rewards

  Background:
    Given the following currencies exist:
      | code | name      | symbol | type   | is_active |
      | BTC  | Bitcoin   | ₿      | crypto | true      |
      | EUR  | Euro      | €      | fiat   | true      |
      | GCU  | GCU       | Ǥ      | custom | true      |
    And the following users exist:
      | email              | kyc_verified |
      | alice@example.com  | true         |
      | bob@example.com    | true         |
    And the following account balances exist:
      | user              | currency | available | locked |
      | alice@example.com | BTC      | 10.0      | 0      |
      | alice@example.com | EUR      | 500000    | 0      |
      | bob@example.com   | BTC      | 5.0       | 0      |
      | bob@example.com   | EUR      | 250000    | 0      |

  Scenario: Create a new liquidity pool
    Given I am logged in as "alice@example.com"
    When I create a liquidity pool for "BTC/EUR" with fee rate "0.003"
    Then a liquidity pool should exist for "BTC/EUR"
    And the pool should have:
      | fee_rate  | 0.003 |
      | is_active | true  |

  Scenario: Add initial liquidity to empty pool
    Given a liquidity pool exists for "BTC/EUR"
    And I am logged in as "alice@example.com"
    When I add liquidity to the "BTC/EUR" pool:
      | base_amount  | 1.0   |
      | quote_amount | 48000 |
    Then the pool reserves should be:
      | base_reserve  | 1.0   |
      | quote_reserve | 48000 |
    And I should have pool shares worth "219.089023"
    And my balances should be:
      | BTC | 9.0    |
      | EUR | 452000 |

  Scenario: Add liquidity maintaining pool ratio
    Given a liquidity pool exists for "BTC/EUR" with reserves:
      | base_reserve  | 2.0   |
      | quote_reserve | 96000 |
    And I am logged in as "bob@example.com"
    When I add liquidity to the "BTC/EUR" pool:
      | base_amount  | 0.5   |
      | quote_amount | 24000 |
    Then the pool reserves should be:
      | base_reserve  | 2.5    |
      | quote_reserve | 120000 |
    And I should have "20%" of the pool shares

  Scenario: Prevent adding liquidity with wrong ratio
    Given a liquidity pool exists for "BTC/EUR" with reserves:
      | base_reserve  | 1.0   |
      | quote_reserve | 48000 |
    And I am logged in as "bob@example.com"
    When I try to add liquidity to the "BTC/EUR" pool:
      | base_amount  | 1.0   |
      | quote_amount | 50000 |
    Then the transaction should fail with "Input amounts deviate too much from pool ratio"

  Scenario: Execute swap through liquidity pool
    Given a liquidity pool exists for "BTC/EUR" with reserves:
      | base_reserve  | 10.0   |
      | quote_reserve | 480000 |
    And I am logged in as "bob@example.com"
    When I swap "0.1" "BTC" for "EUR" in the pool
    Then I should receive approximately "4776.14" "EUR"
    And the pool should have collected fees of "14.40" "EUR"
    And the pool reserves should be:
      | base_reserve  | 10.1          |
      | quote_reserve | 475223.856775 |

  Scenario: Remove liquidity proportionally
    Given a liquidity pool exists for "BTC/EUR" with reserves:
      | base_reserve  | 2.0   |
      | quote_reserve | 96000 |
    And I am logged in as "alice@example.com"
    And I have "50%" of the pool shares
    When I remove "25%" of my liquidity
    Then I should receive:
      | BTC | 0.25  |
      | EUR | 12000 |
    And the pool reserves should be:
      | base_reserve  | 1.75  |
      | quote_reserve | 84000 |

  Scenario: Distribute and claim rewards
    Given a liquidity pool exists for "BTC/EUR"
    And the following providers have shares:
      | user              | shares |
      | alice@example.com | 600    |
      | bob@example.com   | 400    |
    When "1000" "EUR" rewards are distributed to the pool
    Then the pending rewards should be:
      | user              | EUR |
      | alice@example.com | 600 |
      | bob@example.com   | 400 |
    When "alice@example.com" claims rewards from the pool
    Then "alice@example.com" should receive "600" "EUR"
    And "alice@example.com" should have no pending rewards

  Scenario: Calculate pool metrics and APY
    Given a liquidity pool exists for "BTC/EUR" with reserves:
      | base_reserve       | 10.0  |
      | quote_reserve      | 480000|
      | volume_24h         | 100000|
      | fees_collected_24h | 300   |
    When I view the pool metrics
    Then the metrics should show:
      | tvl         | 960000      |
      | spot_price  | 48000       |
      | apy         | 11.40625    |

  Scenario: Price impact protection
    Given a liquidity pool exists for "BTC/EUR" with reserves:
      | base_reserve  | 1.0   |
      | quote_reserve | 48000 |
    And I am logged in as "bob@example.com"
    When I try to swap "0.5" "BTC" for "EUR" with minimum output "23000"
    Then the transaction should fail with "Slippage tolerance exceeded"

  Scenario: Rebalance pool through external liquidity
    Given a liquidity pool exists for "BTC/EUR" with reserves:
      | base_reserve  | 10.0   |
      | quote_reserve | 500000 |
    And the target price ratio is "48000"
    When the pool is rebalanced
    Then the pool should execute rebalancing trades
    And the pool reserves should maintain the constant product
    And the new ratio should be closer to "48000"

  Scenario: Multi-pool liquidity provision
    Given the following liquidity pools exist:
      | pair    | base_reserve | quote_reserve |
      | BTC/EUR | 10.0         | 480000        |
      | BTC/GCU | 5.0          | 240000        |
      | EUR/GCU | 100000       | 100000        |
    And I am logged in as "alice@example.com"
    When I view my liquidity positions
    Then I should see positions in multiple pools
    And each position should show:
      | shares           |
      | current_value    |
      | share_percentage |
      | pending_rewards  |