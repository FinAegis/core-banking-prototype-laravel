Feature: Compliance and Regulatory Flows
  In order to meet regulatory requirements
  As a financial platform
  I need comprehensive compliance workflows and controls

  Background:
    Given compliance services are configured for:
      | Jurisdiction | Regulations          | Reporting Required |
      | USA          | BSA, AML, OFAC       | CTR, SAR          |
      | EU           | GDPR, AML5, PSD2     | STR, Data Privacy |
      | UK           | MLR, Data Protection | SARs, GDPR        |
    And real-time monitoring is active
    And regulatory thresholds are configured

  Scenario: Enhanced KYC onboarding flow
    Given a new user "john.doe@example.com" registers
    When the KYC workflow initiates
    Then the verification should include:
      | Stage          | Checks Performed                    | Required Documents        |
      | Identity       | Name, DOB, SSN/ID verification      | Passport or Driver's License |
      | Address        | Proof of residence                  | Utility bill < 3 months   |
      | Source of Funds| Employment, income verification     | Pay stubs or bank statements |
      | PEP Screening  | Politically exposed person check    | Enhanced due diligence    |
      | Sanctions      | OFAC, UN, EU sanctions lists        | Real-time screening       |
      | Risk Scoring   | Calculate risk profile              | Low/Medium/High           |
    And verification results should be:
      | Check Type    | Result   | Action Required        |
      | Identity      | Passed   | None                   |
      | Address       | Passed   | None                   |
      | PEP Status    | Negative | Standard monitoring    |
      | Risk Score    | Medium   | Quarterly review       |
    And user should be notified of verification status

  Scenario: Transaction monitoring and alerting
    Given real-time transaction monitoring is active
    When various transactions occur:
      | Transaction Type | Amount    | Pattern                | Alert Level |
      | Wire transfer    | $15,000   | Single large           | High        |
      | Multiple deposits| $2,999 x4 | Structuring suspected  | Critical    |
      | International    | €50,000   | High-risk country      | High        |
      | Crypto purchase  | 5 BTC     | First crypto transaction| Medium      |
      | Rapid movement   | $100,000  | In and out same day    | High        |
    Then monitoring should:
      | Alert Level | Response                          | Timeline    |
      | Critical    | Freeze account, manual review     | Immediate   |
      | High        | Flag for review, monitor          | Within 1 hour|
      | Medium      | Add to watch list                 | Within 1 day |
    And generate appropriate compliance reports

  Scenario: Currency Transaction Report (CTR) generation
    Given daily transaction aggregation is running
    When transactions exceed $10,000 threshold:
      | Customer        | Date       | Total Amount | Transaction Count |
      | John Smith      | 2025-06-25 | $12,500      | 3                |
      | ABC Corp        | 2025-06-25 | $75,000      | 1                |
      | Jane Doe        | 2025-06-25 | $10,001      | 5                |
    Then CTR should be generated with:
      | Section | Information Required              | Source           |
      | Part I  | Person information               | KYC records      |
      | Part II | Transaction details              | Transaction log  |
      | Part III| Financial institution info       | System config    |
    And file with FinCEN within 15 days
    And maintain filing records for 5 years

  Scenario: Suspicious Activity Report (SAR) workflow
    Given suspicious activity is detected
    When compliance officer reviews the case:
      | Activity Type      | Details                        | Risk Indicators    |
      | Structuring        | 10 deposits of $9,500          | Avoiding CTR       |
      | Unusual pattern    | Dormant account sudden activity| Behavior change    |
      | High-risk geography| Transfers to sanctioned country| Sanctions risk     |
    Then the SAR process should:
      | Step | Action                        | Deadline         |
      | 1    | Freeze suspicious funds       | Immediate        |
      | 2    | Gather transaction details    | 5 days           |
      | 3    | Document investigation        | 10 days          |
      | 4    | Management review             | 15 days          |
      | 5    | File SAR with FinCEN          | 30 days          |
      | 6    | Continue monitoring           | 90 days          |
    And not notify the customer about SAR filing

  Scenario: GDPR data subject request
    Given a user submits a GDPR request
    When processing the "right to access" request
    Then the system should:
      | Data Category     | Action                      | Format         |
      | Personal info     | Compile from all systems    | JSON           |
      | Transaction history| Export with descriptions    | CSV            |
      | Account settings  | Include preferences         | JSON           |
      | Marketing data    | Show consent history        | PDF            |
      | Third-party sharing| List all processors        | Table          |
      | Profiling data    | Explain algorithms used     | Document       |
    And provide data within 30 days
    And log the request for compliance records

  Scenario: AML risk-based approach
    Given customers have different risk profiles
    When applying risk-based monitoring:
      | Risk Level | Customer Type    | Monitoring Frequency | Review Cycle   |
      | Low        | Retail, domestic | Standard            | Annual         |
      | Medium     | SMB, some int'l  | Enhanced            | Semi-annual    |
      | High       | PEP, high-value  | Continuous          | Quarterly      |
      | Prohibited | Sanctioned       | Block all           | N/A            |
    Then controls should be:
      | Control Type        | Low Risk | Medium Risk | High Risk |
      | Transaction limits  | $50k/day | $25k/day    | $10k/day  |
      | Verification required| Basic    | Enhanced    | Full      |
      | Document refresh    | 3 years  | 2 years     | 1 year    |
      | Manual review       | Sampling | 25% random  | 100%      |

  Scenario: Cross-border compliance coordination
    Given a transaction involves multiple jurisdictions
    When compliance checks are performed:
      | From Country | To Country | Amount   | Compliance Required        |
      | USA          | UK         | $25,000  | OFAC, UK sanctions         |
      | EU           | Switzerland| €100,000 | AML5, Swiss banking laws   |
      | UK           | Singapore  | £50,000  | MLR, MAS regulations       |
    Then the system should:
      | Check Type         | Action                          |
      | Sanctions screening| Check all relevant lists        |
      | Regulatory reporting| File in both jurisdictions     |
      | Tax compliance     | FATCA/CRS where applicable      |
      | Data protection    | Apply strictest standard        |
    And maintain audit trail for all jurisdictions

  Scenario: Compliance training and certification
    Given staff require compliance training
    When managing training requirements:
      | Role              | Required Training           | Frequency    |
      | Customer Service  | AML basics, GDPR            | Annual       |
      | Compliance Officer| Advanced AML, SAR filing    | Quarterly    |
      | Developers        | Data protection, Security   | Semi-annual  |
      | Management        | Regulatory overview         | Annual       |
    Then the system should track:
      | Metric                | Tracking Method            |
      | Completion status     | Per employee dashboard     |
      | Test scores           | Minimum 80% required       |
      | Certification expiry  | Automated reminders        |
      | Training effectiveness| Incident correlation       |

  Scenario: Regulatory examination preparation
    Given a regulatory exam is scheduled
    When preparing for examination:
      | Examiner | Scope                    | Preparation Required        |
      | FinCEN   | AML program review       | 2 years transaction data    |
      | OCC      | Risk management          | Policies and procedures     |
      | State    | Licensing compliance     | Customer complaints, reports|
    Then the system should:
      | Task                  | Timeline    | Output                 |
      | Generate data room    | T-30 days   | Secure portal          |
      | Run self-assessment   | T-21 days   | Gap analysis           |
      | Prepare narratives    | T-14 days   | Process documentation  |
      | Mock examination      | T-7 days    | Internal findings      |
      | Final review          | T-3 days    | Executive summary      |
    And provide real-time response during exam

  Scenario: Sanctions screening workflow
    Given real-time sanctions screening is enabled
    When screening various entities:
      | Entity Name      | Type      | Match Result  | Action Required   |
      | John Smith       | Individual| No match      | Clear             |
      | Ivan Petrov      | Individual| 85% match     | Manual review     |
      | ABC Trading Ltd  | Company   | 100% match    | Block immediately |
      | Maria Garcia     | Individual| 70% match     | Enhanced review   |
    Then the screening should:
      | Match Level | Workflow                      | Resolution Time |
      | 100%        | Auto-block, alert compliance  | Immediate       |
      | 80-99%      | Hold funds, urgent review     | 1 hour          |
      | 60-79%      | Flag for review               | 4 hours         |
      | Below 60%   | Log for audit                 | No action       |
    And maintain screening records for 5 years

  Scenario: Regulatory change management
    Given new AML regulations are announced
    When implementing regulatory changes:
      | Change Type        | Impact Area         | Implementation    |
      | Threshold decrease | CTR filing          | Update systems    |
      | New report type    | Crypto transactions | Develop feature   |
      | Enhanced KYC       | High-risk countries | Update workflow   |
    Then the process should include:
      | Phase          | Activities                    | Timeline   |
      | Assessment     | Gap analysis, impact study    | Week 1-2   |
      | Planning       | Resource allocation, timeline | Week 3     |
      | Development    | System updates, testing       | Week 4-8   |
      | Training       | Staff education, procedures   | Week 9     |
      | Implementation | Phased rollout               | Week 10-12 |
      | Validation     | Compliance testing           | Week 13    |
    And ensure no compliance gaps during transition