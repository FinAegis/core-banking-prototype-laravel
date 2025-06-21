Feature: Basket Asset Management
  In order to diversify my holdings
  As a bank customer
  I need to be able to create and manage basket assets

  Background:
    Given I am logged in as "investor@example.com"
    And the following assets exist:
      | code | name        | type |
      | USD  | US Dollar   | fiat |
      | EUR  | Euro        | fiat |
      | GBP  | GB Pound    | fiat |
      | CHF  | Swiss Franc | fiat |
    And the following exchange rates exist:
      | from | to  | rate |
      | USD  | USD | 1.00 |
      | EUR  | USD | 1.18 |
      | GBP  | USD | 1.37 |
      | CHF  | USD | 1.10 |

  Scenario: Creating a fixed basket
    When I create a basket "STABLE" with the following components:
      | asset | weight |
      | USD   | 40     |
      | EUR   | 30     |
      | GBP   | 20     |
      | CHF   | 10     |
    Then the basket should be created successfully
    And the basket value should be calculated correctly

  Scenario: Decomposing a basket into components
    Given I have a basket "GCU" with the following components:
      | asset | weight |
      | USD   | 35     |
      | EUR   | 30     |
      | GBP   | 25     |
      | CHF   | 10     |
    And I have an account with balance 1000.00 GCU
    When I decompose 100 of basket "GCU"
    Then I should have 35.00 USD in my account
    And I should have 30.00 EUR in my account
    And I should have 25.00 GBP in my account
    And I should have 10.00 CHF in my account
    And my GCU balance should be 900.00

  Scenario: Composing a basket from components
    Given I have a basket "GCU" with the following components:
      | asset | weight |
      | USD   | 50     |
      | EUR   | 50     |
    And I have an account with balance 100.00 USD
    And I have an account with balance 100.00 EUR
    When I compose 100 units of basket "GCU"
    Then my GCU balance should be 100.00
    And my USD balance should be 50.00
    And my EUR balance should be 50.00

  Scenario: Dynamic basket rebalancing
    Given I have a dynamic basket "DYNAMIC" with the following components:
      | asset | weight | min_weight | max_weight |
      | USD   | 40     | 35         | 45         |
      | EUR   | 35     | 30         | 40         |
      | GBP   | 25     | 20         | 30         |
    When the basket needs rebalancing
    And I trigger a rebalance
    Then the basket should be rebalanced within the weight limits
    And a rebalancing event should be recorded