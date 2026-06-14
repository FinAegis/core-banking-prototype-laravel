@wip
Feature: Transaction Reversal
  In order to correct errors and handle disputes
  As a system operator
  I need to reverse transactions safely with proper audit trails

  Background:
    Given I am logged in as an administrator
    And transaction reversal system is configured
    And audit logging is enabled

  Scenario: Reversing a simple deposit transaction
    Given user "customer@example.com" has a deposit of 100.00 USD
    And the transaction ID is "txn_12345"
    When I initiate a reversal for transaction "txn_12345"
    Then the reversal workflow should be created
    And the original deposit should be voided
    And 100.00 USD should be debited from the account
    And a reversal audit entry should be created

  Scenario: Reversing a complex multi-step transfer
    Given a transfer of 500.00 EUR from "alice@example.com" to "bob@example.com"
    And the transfer has completed successfully
    When I reverse the transfer transaction
    Then the withdrawal from Alice should be reversed
    And the deposit to Bob should be reversed
    And both accounts should return to original balances
    And compensation events should be recorded

  Scenario: Attempting to reverse an already reversed transaction
    Given transaction "txn_67890" has already been reversed
    When I attempt to reverse "txn_67890" again
    Then the reversal should fail with error "Transaction already reversed"
    And no balance changes should occur
    And the attempt should be logged for audit

  Scenario: Reversal authorization and approval workflow
    Given a high-value transaction of 10000.00 USD needs reversal
    When I submit a reversal request
    Then the request should require senior approval
    And the transaction should remain unchanged until approved
    And the approver should receive notification
    And approval details should be tracked

  Scenario: Partial reversal of batch operations
    Given a batch operation processed 100 transactions
    And 5 transactions need to be reversed
    When I submit partial reversal requests
    Then only the specified 5 transactions should be reversed
    And the remaining 95 should remain intact
    And the batch operation status should be updated
    And individual reversal confirmations should be provided

  Scenario: Cross-custodian transaction reversal
    Given a transaction involved multiple custodian banks
    And funds were distributed across Paysera and Deutsche Bank
    When the transaction is reversed
    Then reversals should be coordinated across all custodians
    And compensation should occur in all involved banks
    And the reversal should only complete when all banks confirm

  Scenario: Reversal impact analysis
    Given a transaction has dependent operations
    When I analyze reversal impact for "txn_complex"
    Then the system should identify all dependent transactions
    And show potential cascade effects
    And provide reversal recommendations
    And estimate the total impact scope

  Scenario: Time-limited reversal windows
    Given a transaction was completed 30 days ago
    When I attempt to reverse the old transaction
    Then the system should check reversal policies
    And require additional authorization for old reversals
    And possibly reject if beyond maximum time limit
    And log the policy check results

  Scenario: Reversal with compensation tracking
    Given a failed transfer left accounts in inconsistent state
    When the reversal workflow executes compensation
    Then each compensation step should be tracked
    And rollback should occur if compensation fails
    And the system should maintain transaction integrity
    And provide detailed compensation reports

  Scenario: Regulatory compliance during reversals
    Given transaction reversals must be reported to regulators
    When large value reversals are executed
    Then compliance reports should be automatically generated
    And suspicious reversal patterns should be flagged
    And audit trails should meet regulatory standards
    And timing requirements should be enforced