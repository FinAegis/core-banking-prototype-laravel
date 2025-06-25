Feature: Cross-Domain Workflow Coordination
  In order to handle complex business processes
  As a platform with multiple bounded contexts
  I need workflows that coordinate across domain boundaries

  Background:
    Given the following domains are configured:
      | Domain       | Responsibility                    |
      | Account      | Account lifecycle and balances    |
      | Payment      | Transfers and payment processing  |
      | Asset        | Multi-asset and exchange rates    |
      | Compliance   | KYC, AML, and regulatory checks   |
      | Custodian    | External bank integrations        |
      | Governance   | Voting and decision management    |
      | Notification | User communications               |

  Scenario: International wire transfer workflow
    Given a user initiates an international wire transfer
    When the cross-domain workflow executes
    Then it should coordinate across domains:
      | Step | Domain      | Action                           | Dependencies    |
      | 1    | Account     | Validate source account          | None            |
      | 2    | Compliance  | Perform AML checks               | Step 1          |
      | 3    | Asset       | Get exchange rate                | Step 2          |
      | 4    | Account     | Freeze source funds              | Step 3          |
      | 5    | Custodian   | Initiate bank transfer           | Step 4          |
      | 6    | Payment     | Create transfer record           | Step 5          |
      | 7    | Compliance  | File regulatory reports          | Step 6          |
      | 8    | Account     | Debit source account             | Step 6          |
      | 9    | Notification| Send confirmation                 | Step 8          |
    And maintain transaction consistency across all domains
    And handle failures with appropriate compensation

  Scenario: New account onboarding mega-workflow
    Given a new user registers for an account
    When the onboarding workflow spans multiple domains
    Then the coordination should include:
      | Phase        | Domains Involved           | Actions                          |
      | Identity     | Compliance, Account        | KYC verification, document check |
      | Account      | Account, Asset             | Create account, set limits       |
      | Funding      | Payment, Custodian         | Link bank, initial deposit       |
      | Preferences  | Governance, Notification   | Set voting power, preferences    |
      | Activation   | All domains                | Final checks and activation      |
    And each domain should maintain its boundaries
    And rollback should work across all domains if needed

  Scenario: Basket rebalancing with governance
    Given a GCU basket needs monthly rebalancing
    When the rebalancing workflow triggers
    Then it should coordinate:
      | Domain      | Responsibility                      | Output              |
      | Governance  | Collect and tally votes             | New allocations     |
      | Asset       | Calculate required trades           | Trade list          |
      | Custodian   | Execute trades with banks           | Trade confirmations |
      | Account     | Update all holder balances          | New balances        |
      | Compliance  | Ensure regulatory compliance        | Compliance report   |
      | Notification| Inform affected users               | Notifications sent  |
    And ensure atomic rebalancing across all accounts

  Scenario: Multi-bank settlement workflow
    Given end-of-day settlement is required
    When the settlement workflow runs
    Then it should orchestrate:
      | Step | Domains              | Action                          | Timing   |
      | 1    | Payment, Account     | Aggregate net positions         | T+0 5PM  |
      | 2    | Custodian           | Query bank balances             | T+0 6PM  |
      | 3    | Asset               | Calculate FX requirements       | T+0 7PM  |
      | 4    | Compliance          | Review large transfers          | T+0 8PM  |
      | 5    | Custodian           | Submit settlement files         | T+0 9PM  |
      | 6    | Payment             | Update settlement status        | T+0 10PM |
      | 7    | Account, Notification| Confirm and notify              | T+1 6AM  |
    And handle bank-specific settlement windows
    And manage cross-timezone coordination

  Scenario: Compliance-triggered account freeze
    Given suspicious activity is detected
    When compliance initiates emergency freeze
    Then the workflow should cascade:
      | Domain       | Immediate Actions              | Follow-up Actions           |
      | Compliance   | Flag account for review        | Initiate investigation      |
      | Account      | Freeze all balances            | Block new transactions      |
      | Payment      | Cancel pending transfers       | Reverse recent suspicious   |
      | Custodian    | Notify partner banks           | Hold external transfers     |
      | Governance   | Suspend voting rights          | Review governance impact    |
      | Notification | Alert user and compliance      | Schedule follow-ups         |
    And ensure coordinated freeze across all systems
    And maintain audit trail across domains

  Scenario: Cross-asset arbitrage workflow
    Given price discrepancies exist between assets
    When arbitrage opportunity is detected
    Then the workflow should coordinate:
      | Parallel Path A                    | Parallel Path B                   |
      | Asset: Lock exchange rates         | Account: Reserve funds            |
      | Payment: Prepare transfer A→B      | Payment: Prepare transfer B→A     |
      | Custodian: Check bank liquidity    | Asset: Monitor rate changes       |
      | Execute both paths atomically      | Or cancel if opportunity closes   |
    And ensure exactly-once execution
    And handle partial failures gracefully

  Scenario: Regulatory audit workflow
    Given quarterly audit is initiated
    When the cross-domain audit workflow runs
    Then it should collect from all domains:
      | Domain      | Data Required                  | Format         |
      | Account     | All account snapshots          | JSON + Hash    |
      | Payment     | Transaction history            | Event stream   |
      | Asset       | Exchange rate history          | Time series    |
      | Compliance  | KYC/AML check results          | PDF reports    |
      | Custodian   | Bank reconciliation            | CSV files      |
      | Governance  | Voting records and outcomes    | Blockchain     |
    And compile comprehensive audit package
    And ensure data consistency across domains
    And maintain immutable audit trail

  Scenario: Emergency market halt workflow
    Given extreme market volatility is detected
    When emergency halt is triggered
    Then all domains should coordinate:
      | Priority | Domain      | Action                         | Deadline |
      | 1        | Asset       | Freeze all exchange rates      | 1 min    |
      | 1        | Payment     | Halt all transfers             | 1 min    |
      | 2        | Custodian   | Pause bank integrations        | 2 min    |
      | 2        | Account     | Lock all account operations    | 2 min    |
      | 3        | Governance  | Trigger emergency vote         | 5 min    |
      | 3        | Notification| Alert all users                | 5 min    |
      | 4        | Compliance  | File regulatory notices        | 15 min   |
    And ensure coordinated halt across all systems
    And prepare for coordinated resumption

  Scenario: User data deletion (GDPR)
    Given a user requests account deletion
    When the GDPR workflow executes
    Then it should coordinate deletion:
      | Domain       | Data to Remove              | Retention Exception       |
      | Account      | Account records             | Regulatory required       |
      | Payment      | Transaction details         | 7-year requirement        |
      | Asset        | Trading history             | Anonymize only            |
      | Compliance   | KYC documents               | Legal hold check          |
      | Custodian    | Bank account links          | Full deletion             |
      | Governance   | Voting history              | Anonymize only            |
      | Notification | Contact preferences         | Full deletion             |
    And ensure compliance across all domains
    And provide deletion certificate

  Scenario: Cross-domain performance optimization
    Given system load is increasing
    When optimization workflow triggers
    Then domains should coordinate:
      | Optimization      | Participating Domains        | Strategy              |
      | Cache warming     | Account, Asset, Payment      | Predictive caching    |
      | Rate limiting     | All domains                  | Coordinated quotas    |
      | Batch processing  | Payment, Custodian           | Aligned schedules     |
      | Circuit breaking  | Custodian, Asset             | Shared breaker state  |
    And maintain service quality across domains
    And balance load effectively