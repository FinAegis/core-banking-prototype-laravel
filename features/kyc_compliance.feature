@wip
Feature: KYC and Compliance
  In order to meet regulatory requirements
  As a financial platform
  I need to verify customer identities and maintain compliance

  Background:
    Given the KYC system is configured with standard requirements
    And compliance monitoring is active

  Scenario: New user KYC submission
    Given I am a new user "newuser@example.com"
    And I am not KYC verified
    When I submit my KYC documents:
      | Document Type | Status   |
      | Passport      | Uploaded |
      | Address Proof | Uploaded |
      | Photo ID      | Uploaded |
    Then my KYC status should be "pending"
    And compliance team should be notified
    And I should receive submission confirmation

  Scenario: KYC approval process
    Given I have submitted KYC documents
    And a compliance officer reviews my application
    When the compliance officer approves my KYC
    Then my KYC status should be "approved"
    And my account limits should be increased
    And I should receive approval notification

  Scenario: KYC rejection and resubmission
    Given I have submitted KYC documents
    When the compliance officer rejects my KYC due to "unclear_documents"
    Then my KYC status should be "rejected"
    And I should receive rejection notification with reasons
    And I should be able to resubmit improved documents

  Scenario: Transaction monitoring and limits
    Given I am KYC approved at level 2
    And my daily limit is 5000.00 USD
    When I attempt a transfer of 6000.00 USD
    Then the transaction should be blocked
    And I should be prompted to complete higher KYC level
    And the compliance team should be alerted

  Scenario: Suspicious activity detection
    Given I have been making normal transactions
    When I suddenly make multiple large transactions:
      | Amount    | Frequency    | Pattern   |
      | 9000.00   | 5 times/day | Just below limit |
      | 4999.00   | 10 times/day| Round numbers    |
    Then the system should flag suspicious activity
    And my account should be temporarily restricted
    And compliance investigation should be initiated

  Scenario: PEP (Politically Exposed Person) screening
    Given I am flagged as a PEP during KYC
    When my application is processed
    Then enhanced due diligence should be required
    And senior compliance approval should be needed
    And ongoing monitoring should be increased

  Scenario: AML transaction reporting
    Given large transactions occur above reporting threshold
    When daily reconciliation runs
    Then CTR (Currency Transaction Report) should be generated
    And suspicious patterns should be identified
    And regulatory reports should be prepared

  Scenario: KYC document expiry management
    Given my KYC documents are expiring in 30 days
    When the system performs daily checks
    Then I should receive renewal notification
    And my account should be flagged for re-verification
    And temporary restrictions may apply after expiry

  Scenario: Cross-border compliance
    Given I initiate a transfer to a sanctioned country
    When the compliance engine processes the transaction
    Then the transaction should be automatically blocked
    And compliance team should be immediately notified
    And the incident should be logged for audit

  Scenario: Data privacy and GDPR compliance
    Given I request my personal data export
    When the system processes my GDPR request
    Then all my data should be compiled
    And delivered in machine-readable format
    And the request should be completed within 30 days