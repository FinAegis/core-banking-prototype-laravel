Feature: Money Transfers
  In order to send money to other accounts
  As a bank customer
  I need to be able to transfer funds between accounts

  Background:
    Given I am logged in as "alice@example.com"
    And the following accounts exist:
      | owner             | uuid                                 | balance | currency |
      | alice@example.com | a1234567-1234-1234-1234-123456789012 | 1000.00 | USD      |
      | bob@example.com   | b1234567-1234-1234-1234-123456789012 | 500.00  | USD      |

  Scenario: Successful transfer between accounts
    Given I have an account with balance 1000.00 USD
    When I transfer 250.00 USD to account "b1234567-1234-1234-1234-123456789012"
    Then the transfer should be successful
    And my account balance should be 750.00 USD
    And account "b1234567-1234-1234-1234-123456789012" should have balance 750.00 USD

  Scenario: Transfer with insufficient funds
    Given I have an account with balance 100.00 USD
    When I try to transfer 200.00 USD to account "b1234567-1234-1234-1234-123456789012"
    Then the transfer should fail with error "Insufficient funds"
    And my account balance should be 100.00 USD

  Scenario: Cross-currency transfer
    Given I have an account with balance 1000.00 USD
    And the following exchange rates exist:
      | from | to  | rate |
      | USD  | EUR | 0.85 |
    When I transfer 100.00 USD to EUR account "b1234567-1234-1234-1234-123456789012"
    Then the transfer should be successful
    And my account balance should be 900.00 USD
    And account "b1234567-1234-1234-1234-123456789012" should have balance 85.00 EUR

  Scenario: Bulk transfer processing
    Given I have an account with balance 5000.00 USD
    When I submit the following bulk transfers:
      | to_account                           | amount | currency |
      | b1234567-1234-1234-1234-123456789012 | 100.00 | USD      |
      | c1234567-1234-1234-1234-123456789012 | 200.00 | USD      |
      | d1234567-1234-1234-1234-123456789012 | 300.00 | USD      |
    Then all transfers should be successful
    And my account balance should be 4400.00 USD