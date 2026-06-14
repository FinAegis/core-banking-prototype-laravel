@wip
Feature: Custodian Integration
  In order to provide secure multi-bank services
  As a financial platform
  I need to integrate with multiple custodian banks seamlessly

  Background:
    Given custodian banks are configured:
      | Bank Name      | Status | Type    | Region |
      | Paysera LT     | Active | Primary | EU     |
      | Deutsche Bank  | Active | Partner | EU     |
      | Santander      | Active | Partner | EU     |
      | Wise           | Active | Digital | Global |
    And bank health monitoring is enabled

  Scenario: Multi-bank account creation
    Given a new user registers for an account
    When the account creation workflow is triggered
    Then accounts should be created across all active custodians
    And each custodian should return account details
    And the accounts should be linked in our system
    And balance synchronization should be established

  Scenario: Distributed fund allocation
    Given a user has 1000.00 EUR to deposit
    And bank allocation is set to:
      | Bank Name     | Allocation |
      | Paysera LT    | 40%        |
      | Deutsche Bank | 30%        |
      | Santander     | 30%        |
    When the user deposits the funds
    Then 400.00 EUR should go to Paysera LT
    And 300.00 EUR should go to Deutsche Bank
    And 300.00 EUR should go to Santander
    And all deposits should be confirmed

  Scenario: Bank failover during transaction
    Given Deutsche Bank becomes unavailable
    And a user initiates a 500.00 EUR transfer
    When the system detects the bank failure
    Then the transaction should be rerouted to Santander
    And the user should be notified of the alternative routing
    And the transaction should complete successfully

  Scenario: Custodian webhook processing
    Given a webhook endpoint is configured for Paysera
    When Paysera sends a transaction confirmation webhook
    Then the system should verify the webhook signature
    And update the transaction status accordingly
    And trigger any dependent workflows
    And acknowledge receipt to Paysera

  Scenario: Cross-bank balance reconciliation
    Given accounts exist across multiple custodians
    When daily reconciliation runs
    Then balances should be fetched from all banks
    And compared with internal records
    And any discrepancies should be flagged
    And automatic correction should be attempted

  Scenario: Bank health monitoring and alerting
    Given continuous health monitoring is active
    When a bank's response time exceeds thresholds
    Then the bank should be marked as "degraded"
    And traffic should be reduced to that bank
    And operations team should be alerted
    And failover procedures should be prepared

  Scenario: Circuit breaker activation
    Given a bank is experiencing multiple failures
    When the failure rate exceeds 25% over 5 minutes
    Then the circuit breaker should activate
    And all traffic to that bank should be stopped
    And alternative banks should handle the load
    And the bank should be automatically retested

  Scenario: Compliance across custodians
    Given different banks have different compliance requirements
    When a high-value transaction is initiated
    Then the system should route to compliant banks only
    And apply the strictest requirements across all banks
    And maintain audit trails for all custodians

  Scenario: Multi-bank transaction coordination
    Given a large transfer requires multiple banks
    When transferring 50000.00 EUR internationally
    Then the system should coordinate across banks
    And ensure atomic transaction completion
    And handle partial failures with compensation
    And provide unified status tracking

  Scenario: Bank-specific feature management
    Given different banks support different features
    When a user requests cryptocurrency conversion
    Then the system should route to banks supporting crypto
    And fall back gracefully if features are unavailable
    And inform the user of available alternatives