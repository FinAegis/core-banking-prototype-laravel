@wip
Feature: Wallet Currency Conversion
  In order to convert between currencies
  As a user with multi-currency needs
  I need to exchange funds at competitive rates with transparency

  Background:
    Given I am logged in as "trader@example.com"
    And I have a multi-currency wallet
    And exchange rate providers are configured
    And real-time rates are available

  Scenario: Simple currency conversion
    Given I have 1000.00 USD in my wallet
    And the EUR/USD exchange rate is 1.20
    When I convert 500.00 USD to EUR
    Then the conversion should be successful
    And I should receive approximately 416.67 EUR
    And my USD balance should be reduced by 500.00
    And conversion fees should be clearly displayed

  Scenario: Multi-provider rate comparison
    Given multiple exchange rate providers are available:
      | Provider    | EUR/USD Rate | Fee  |
      | Provider A  | 1.2050      | 0.5% |
      | Provider B  | 1.2040      | 0.3% |
      | Provider C  | 1.2055      | 0.4% |
    When I request conversion from USD to EUR
    Then I should see all available rates
    And the best rate should be highlighted
    And total cost including fees should be shown

  Scenario: Large conversion with slippage protection
    Given I want to convert 50000.00 USD to EUR
    And I set maximum slippage to 0.5%
    When I initiate the large conversion
    Then the system should check for market impact
    And execute the conversion in chunks if needed
    And abort if slippage exceeds my limit
    And provide detailed execution report

  Scenario: Conversion with insufficient balance
    Given I have 100.00 USD in my wallet
    When I attempt to convert 200.00 USD to EUR
    Then the conversion should fail with error "Insufficient balance"
    And no currency exchange should occur
    And my balances should remain unchanged

  Scenario: Real-time rate expiry during conversion
    Given I initiate a conversion with quoted rate 1.2000
    And the rate quote is valid for 30 seconds
    When the rate expires during processing
    Then I should be offered a new rate
    And given option to accept or cancel
    And the original quote should no longer be valid

  Scenario: Conversion workflow with multiple steps
    Given I want to convert USD to JPY via EUR
    When I initiate the multi-step conversion
    Then USD should be converted to EUR first
    And EUR should be converted to JPY second
    And each step should be optimized for best rates
    And the complete conversion should be atomic

  Scenario: Historical conversion tracking
    Given I have made several conversions
    When I view my conversion history
    Then I should see all past conversions with:
      | Field        | Details |
      | Date         | ISO timestamp |
      | From Currency| Original currency |
      | To Currency  | Target currency |
      | Amount       | Converted amount |
      | Rate Used    | Exchange rate |
      | Fees Paid    | Total fees |
      | Provider     | Rate provider |

  Scenario: Conversion limits and compliance
    Given daily conversion limits are set to 10000.00 USD equivalent
    And I have already converted 8000.00 USD today
    When I attempt to convert another 3000.00 USD
    Then the conversion should be partially allowed
    And I should be informed of remaining daily limit
    And excess amount should be queued for next day

  Scenario: Automated conversion based on rules
    Given I set up automatic conversion rules:
      | Condition        | Action           |
      | USD > 5000       | Convert to EUR   |
      | EUR < 100        | Convert from USD |
    When my USD balance exceeds 5000
    Then automatic conversion should trigger
    And convert excess USD to EUR
    And notify me of the automatic action

  Scenario: Cross-border conversion compliance
    Given I convert large amounts between certain currencies
    When conversion amounts exceed reporting thresholds
    Then compliance checks should be triggered
    And additional verification may be required
    And regulatory reports should be filed automatically
    And conversion may be delayed pending verification