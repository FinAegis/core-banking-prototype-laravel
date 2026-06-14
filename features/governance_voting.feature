@wip
Feature: Governance and Voting
  In order to participate in platform governance
  As a stakeholder
  I need to be able to create and vote on proposals

  Background:
    Given I am logged in as "stakeholder@example.com"
    And I have voting power of 100 tokens
    And the governance system is active

  Scenario: Creating a configuration update proposal
    When I create a poll to update configuration:
      | Setting           | Current Value | Proposed Value |
      | min_deposit       | 10.00         | 5.00          |
      | max_transfer      | 10000.00      | 15000.00      |
      | kyc_threshold     | 1000.00       | 2000.00       |
    Then the proposal should be created successfully
    And the proposal status should be "pending"
    And other stakeholders should be notified

  Scenario: Voting on a basket composition change
    Given a proposal exists to add "BTC" to the "CRYPTO_BASKET"
    And the proposal is active for voting
    When I vote "yes" on the proposal
    Then my vote should be recorded
    And my voting power of 100 should be applied
    And the proposal should show updated vote counts

  Scenario: Proposal passes with majority support
    Given a proposal to add "ETH" asset with 60% support
    And the voting period is ending
    When the voting period closes
    Then the proposal should be marked as "passed"
    And the configuration should be automatically updated
    And the "ETH" asset should be added to the system

  Scenario: Proposal fails without majority
    Given a proposal to increase fees with 40% support
    And the voting period is ending
    When the voting period closes
    Then the proposal should be marked as "rejected"
    And the current configuration should remain unchanged
    And stakeholders should be notified of the result

  Scenario: Feature toggle governance
    Given a proposal exists to enable "advanced_derivatives"
    And I have sufficient voting power
    When I vote to enable the feature
    And the proposal reaches majority approval
    Then the feature should be automatically enabled
    And the system should log the governance action

  Scenario: Emergency governance override
    Given a critical security issue is detected
    When an emergency proposal is created
    Then it should have a shortened voting period
    And require higher approval threshold
    And allow admin override if consensus cannot be reached

  Scenario: Voting power calculation
    Given I hold tokens in multiple accounts
    When I check my voting power
    Then it should aggregate across all my accounts
    And include any delegated voting power
    And exclude any locked or frozen tokens

  Scenario: Proposal history and auditability
    Given multiple proposals have been voted on
    When I view the governance history
    Then I should see all past proposals
    And their voting results
    And implementation status
    And be able to verify the integrity of the voting process

  Scenario: Delegated voting
    Given I want to delegate my voting power
    When I delegate 50 tokens to "expert@example.com"
    Then my available voting power should be reduced by 50
    And the delegate should be able to vote with those tokens
    And I should be able to revoke delegation at any time