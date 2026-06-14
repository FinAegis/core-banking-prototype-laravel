@wip
Feature: Stablecoin Operations
  In order to use digital stablecoins
  As a crypto-enabled user
  I need to be able to mint, burn and manage stablecoin positions

  Background:
    Given I am logged in as "crypto@example.com"
    And I have an account with balance 1000.00 USD
    And a stablecoin "FGUSDC" exists with collateral ratio 150%

  Scenario: Minting stablecoins with sufficient collateral
    Given I have 300.00 USD as collateral
    When I mint 200.00 FGUSDC stablecoins
    Then the minting should be successful
    And I should have 200.00 FGUSDC in my account
    And my collateral should be locked at 300.00 USD
    And my collateral ratio should be 150%

  Scenario: Minting fails with insufficient collateral
    Given I have 100.00 USD as collateral
    When I try to mint 200.00 FGUSDC stablecoins
    Then the minting should fail with error "Insufficient collateral"
    And I should have 0.00 FGUSDC in my account
    And my collateral should remain unlocked

  Scenario: Burning stablecoins to release collateral
    Given I have 200.00 FGUSDC stablecoins
    And 300.00 USD locked as collateral
    When I burn 100.00 FGUSDC stablecoins
    Then the burning should be successful
    And I should have 100.00 FGUSDC remaining
    And 150.00 USD collateral should be released
    And 150.00 USD should remain locked

  Scenario: Adding collateral to improve ratio
    Given I have 200.00 FGUSDC stablecoins
    And 300.00 USD locked as collateral
    And my collateral ratio is 150%
    When I add 100.00 USD additional collateral
    Then the collateral addition should be successful
    And my total collateral should be 400.00 USD
    And my collateral ratio should be 200%

  Scenario: Liquidation when collateral ratio falls
    Given I have 200.00 FGUSDC stablecoins
    And 300.00 USD locked as collateral
    And the USD price drops affecting my collateral ratio to 140%
    When the system checks for liquidation opportunities
    Then my position should be flagged for liquidation
    And liquidators should be able to liquidate my position
    And I should receive the liquidation penalty

  Scenario: Mass liquidation simulation
    Given multiple users have under-collateralized positions
    And the total system collateral ratio is below 120%
    When I trigger a mass liquidation simulation
    Then the system should identify all at-risk positions
    And calculate the total liquidation impact
    And provide recovery scenarios

  Scenario: Stablecoin system health monitoring
    Given the stablecoin system is operational
    When I check the system health metrics
    Then I should see:
      | Metric                    | Value |
      | Total Supply             | > 0   |
      | Average Collateral Ratio | > 150% |
      | System Stability        | Stable |
      | Liquidation Queue       | Empty |

  Scenario: Emergency stability mechanism activation
    Given the stablecoin price deviates by more than 5%
    When the stability mechanism is triggered
    Then the system should attempt to restore the peg
    And emergency measures should be logged
    And notifications should be sent to stakeholders