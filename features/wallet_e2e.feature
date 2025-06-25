Feature: Wallet End-to-End User Journeys
  In order to manage my digital assets effectively
  As a wallet user
  I need comprehensive wallet functionality with great user experience

  Background:
    Given I am a registered user "alice@example.com"
    And I have completed KYC verification
    And I have a multi-asset wallet enabled
    And the following assets are available:
      | Asset | Name            | Type      |
      | USD   | US Dollar       | fiat      |
      | EUR   | Euro            | fiat      |
      | GBP   | British Pound   | fiat      |
      | BTC   | Bitcoin         | crypto    |
      | ETH   | Ethereum        | crypto    |
      | GCU   | Global Currency | basket    |

  Scenario: First-time wallet setup and funding
    Given I am a new user with an empty wallet
    When I complete the wallet setup flow
    Then I should:
      | Step | Action                          | Result                         |
      | 1    | See welcome tutorial            | Understand wallet features     |
      | 2    | Set security preferences        | 2FA enabled, PIN set           |
      | 3    | Choose preferred currencies     | USD, EUR, BTC selected         |
      | 4    | Link bank account               | Verified via micro-deposits    |
      | 5    | Make initial deposit            | $1000 USD credited             |
      | 6    | Receive welcome bonus           | $10 USD bonus added            |
      | 7    | See wallet dashboard            | Balances and options visible   |
    And my wallet should be fully operational
    And I should receive onboarding emails

  Scenario: Daily wallet usage patterns
    Given I have an active wallet with multiple currencies
    When I perform typical daily operations:
      | Time  | Operation                      | Details                        |
      | 9 AM  | Check morning balances         | View dashboard                 |
      | 10 AM | Convert USD to EUR             | $500 at market rate            |
      | 12 PM | Send payment to friend         | $50 to bob@example.com         |
      | 2 PM  | Receive crypto payment         | 0.001 BTC from client          |
      | 3 PM  | Buy GCU basket                 | $200 worth                     |
      | 4 PM  | Set up recurring transfer      | $100/month to savings          |
      | 6 PM  | Review daily transactions      | Check history and fees         |
    Then all operations should complete successfully
    And transaction history should be accurate
    And real-time balances should be updated

  Scenario: Cross-border payment with currency conversion
    Given I need to pay a supplier in Japan
    And I have 5000 USD in my wallet
    When I initiate the payment flow:
      | Field               | Value                          |
      | Recipient           | supplier@example.jp            |
      | Amount              | 50000 JPY                      |
      | Payment method      | International wire             |
      | Preferred currency  | Convert from USD               |
      | Reference           | Invoice #12345                 |
      | Urgency             | Standard (1-2 days)            |
    Then the system should:
      | Action                  | Details                          |
      | Show conversion preview | USD amount, rate, fees           |
      | Perform compliance check| AML/sanctions screening          |
      | Request confirmation    | Total cost in USD                |
      | Execute conversion      | Lock rate for 30 seconds         |
      | Initiate wire transfer  | Via custodian bank               |
      | Provide tracking        | Reference number and ETA         |
    And deduct the correct USD amount including fees

  Scenario: Multi-signature wallet security
    Given I have enabled multi-signature for large transactions
    And my security rules are:
      | Amount           | Required Approvals |
      | < $1000          | Just me            |
      | $1000 - $10000   | Me + email confirm |
      | > $10000         | Me + authenticator |
    When I attempt various transactions:
      | Amount  | Type      | Approval Required        | Result  |
      | $500    | Transfer  | None                     | Success |
      | $2000   | Withdraw  | Email confirmation       | Pending |
      | $15000  | Wire      | Authenticator app        | Pending |
      | $2000   | Internal  | Email (remembered device)| Success |
    Then security protocols should be enforced
    And I should be able to manage pending approvals
    And completed transactions should show approval history

  Scenario: Wallet backup and recovery
    Given I want to ensure wallet recovery options
    When I set up wallet backup:
      | Method            | Status    | Details                    |
      | Recovery phrase   | Generated | 12-word mnemonic           |
      | Cloud backup      | Enabled   | Encrypted to iCloud        |
      | Trusted contacts  | Added     | spouse@example.com         |
      | Security questions| Set       | 3 questions configured     |
    And I simulate wallet recovery scenarios:
      | Scenario          | Recovery Method    | Success |
      | Lost phone        | Cloud backup       | Yes     |
      | Forgotten password| Security questions | Yes     |
      | Emergency access  | Trusted contact    | Yes     |
      | Complete loss     | Recovery phrase    | Yes     |
    Then all recovery methods should work correctly
    And recovered wallet should match original state

  Scenario: Investment portfolio management
    Given I want to diversify my holdings
    When I use portfolio management features:
      | Action                | Details                           | Result              |
      | View allocation       | Current asset distribution        | Pie chart displayed |
      | Set target allocation | 60% fiat, 30% crypto, 10% basket  | Targets saved       |
      | Enable rebalancing    | Monthly automatic                 | Scheduled           |
      | Create savings goal   | $10000 by December                | Progress tracked    |
      | Set up DCA           | $500/month into BTC               | Automated           |
      | Risk assessment       | Complete questionnaire            | Conservative profile|
      | Performance tracking  | Last 6 months                     | +12.5% return       |
    Then portfolio features should work seamlessly
    And recommendations should match risk profile

  Scenario: Mobile wallet synchronization
    Given I use wallet on multiple devices
    When I perform operations across devices:
      | Device  | Action                    | Time    |
      | Phone   | Send $100 to friend       | 2:00 PM |
      | Tablet  | Check balance             | 2:01 PM |
      | Desktop | Convert EUR to USD        | 2:02 PM |
      | Phone   | View transaction history  | 2:03 PM |
    Then all devices should show synchronized data
    And push notifications should work across devices
    And no duplicate transactions should occur
    And offline changes should sync when online

  Scenario: Wallet spending analytics
    Given I have 3 months of transaction history
    When I access spending analytics
    Then I should see:
      | Analytics Type     | Information Shown                |
      | Spending by category| Groceries, utilities, entertainment |
      | Monthly trends     | Average spend, peaks, savings    |
      | Currency usage     | Most used currencies             |
      | Fee analysis       | Total fees by type               |
      | Merchant insights  | Top merchants, frequency         |
      | Budget tracking    | vs. set budgets, alerts          |
      | Export options     | PDF, CSV, accounting software    |
    And insights should be actionable
    And data should be accurately categorized

  Scenario: Social wallet features
    Given social features are enabled
    When I interact with other users:
      | Feature           | Action                      | Privacy Setting |
      | Send to contact   | $50 to phone contact        | Contacts only   |
      | Request payment   | $25 from roommate           | Public username |
      | Split bill        | Dinner with 4 friends       | Private group   |
      | Recurring request | Monthly rent from tenant    | Scheduled       |
      | Payment link      | Generate for invoice        | One-time use    |
      | Group wallet      | Vacation fund with friends  | Shared view     |
    Then social features should respect privacy
    And notifications should be timely
    And settlement should be automatic

  Scenario: Emergency wallet access
    Given I'm traveling and have an emergency
    When I need emergency wallet access:
      | Situation            | Action Required         | Resolution           |
      | Lost phone abroad    | Access from web         | Emergency web login  |
      | Card compromised     | Freeze card instantly   | One-click freeze     |
      | Need emergency funds | Quick transfer          | Express transfer     |
      | Medical emergency    | Increase limits         | Temporary increase   |
      | Legal hold           | Provide records         | Export full history  |
    Then emergency features should be easily accessible
    And security should not be compromised
    And audit trail should be maintained

  Scenario: Wallet closure and migration
    Given I want to close my wallet account
    When I initiate account closure:
      | Step | Action                      | Verification         |
      | 1    | Request closure             | Email confirmation   |
      | 2    | Settle pending transactions | Wait for completion  |
      | 3    | Withdraw all funds          | To verified bank     |
      | 4    | Export transaction history  | PDF and CSV          |
      | 5    | Cancel subscriptions        | Auto-payments stopped|
      | 6    | Confirm closure             | Final verification   |
      | 7    | Receive confirmation        | Closure certificate  |
    Then account should be properly closed
    And data retention should follow regulations
    And reopening should be possible within 30 days