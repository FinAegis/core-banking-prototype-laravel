# P2P Lending Platform Architecture

## Overview

The FinAegis P2P Lending Platform connects borrowers with lenders directly, enabling efficient capital allocation with lower costs than traditional lending. The platform uses event sourcing for audit trails, workflows for complex loan lifecycles, and smart risk assessment algorithms.

## Key Features

### 1. Loan Management
- **Loan Application Processing**: Multi-step workflow with credit checks
- **Risk Assessment**: Automated scoring with manual override capabilities
- **Interest Rate Determination**: Dynamic pricing based on risk profile
- **Loan Matching**: Automated matching of borrowers with suitable lenders

### 2. Risk Management
- **Credit Scoring Integration**: External bureau + internal scoring
- **Collateral Management**: Support for crypto and traditional collateral
- **Default Prediction**: ML-based early warning system
- **Portfolio Diversification**: Automated allocation recommendations

### 3. Investor Features
- **Auto-Invest**: Rule-based automatic investment
- **Portfolio Management**: Risk-adjusted portfolio construction
- **Secondary Market**: Trade loan positions before maturity
- **Returns Tracking**: Real-time yield calculation

### 4. Borrower Features
- **Quick Application**: Streamlined onboarding process
- **Transparent Pricing**: Clear fee structure
- **Flexible Repayment**: Multiple repayment options
- **Credit Building**: Report to credit bureaus

## Domain Model

### Core Aggregates

#### 1. LoanApplication (Event-Sourced)
```php
class LoanApplication extends AggregateRoot
{
    private string $applicationId;
    private string $borrowerId;
    private Money $requestedAmount;
    private int $termMonths;
    private string $purpose;
    private string $status;
    private ?CreditScore $creditScore;
    private ?RiskRating $riskRating;
    private ?Money $approvedAmount;
    private ?float $interestRate;
}
```

#### 2. Loan (Event-Sourced)
```php
class Loan extends AggregateRoot
{
    private string $loanId;
    private string $borrowerId;
    private Money $principal;
    private float $interestRate;
    private int $termMonths;
    private string $status;
    private array $investors; // [investorId => amount]
    private RepaymentSchedule $schedule;
    private Money $outstandingBalance;
}
```

#### 3. InvestmentOrder (Event-Sourced)
```php
class InvestmentOrder extends AggregateRoot
{
    private string $orderId;
    private string $investorId;
    private Money $amount;
    private InvestmentCriteria $criteria;
    private string $status;
    private array $allocations; // [loanId => amount]
}
```

### Events

#### Loan Application Events
- `LoanApplicationSubmitted`
- `CreditCheckCompleted`
- `RiskAssessmentCompleted`
- `LoanApplicationApproved`
- `LoanApplicationRejected`
- `LoanApplicationWithdrawn`

#### Loan Events
- `LoanCreated`
- `LoanFunded`
- `LoanDisbursed`
- `RepaymentReceived`
- `LoanDefaulted`
- `LoanSettledEarly`
- `LoanCompleted`

#### Investment Events
- `InvestmentOrderPlaced`
- `FundsAllocatedToLoan`
- `InvestmentMatured`
- `ReturnsDistributed`
- `PositionSoldOnSecondaryMarket`

## Workflows & Sagas

### 1. Loan Application Workflow
```yaml
LoanApplicationWorkflow:
  steps:
    - ValidateApplication
    - PerformKYCCheck
    - RunCreditCheck
    - AssessRisk
    - DetermineInterestRate
    - ApproveOrReject
    - NotifyBorrower
```

### 2. Loan Funding Saga
```yaml
LoanFundingSaga:
  triggers:
    - LoanApplicationApproved
  steps:
    - CreateLoanListing
    - MatchWithInvestors
    - CollectInvestorFunds
    - CompleteFunding
    - DisburseFundsToBorrower
  compensations:
    - RefundInvestorsOnFailure
    - CancelLoanListing
```

### 3. Repayment Processing Workflow
```yaml
RepaymentWorkflow:
  schedule: Monthly
  steps:
    - CalculateAmountDue
    - CollectFromBorrower
    - DistributeToInvestors
    - UpdateLoanBalance
    - HandleFailedPayments
```

### 4. Default Management Saga
```yaml
DefaultManagementSaga:
  triggers:
    - PaymentMissed
  steps:
    - SendReminders
    - ApplyLateFees
    - InitiateCollectionProcess
    - MarkAsDefaulted
    - InitiateRecovery
```

## Service Layer

### 1. CreditScoringService
```php
interface CreditScoringService
{
    public function getScore(string $borrowerId): CreditScore;
    public function updateScore(string $borrowerId, array $data): void;
    public function getCreditReport(string $borrowerId): CreditReport;
}
```

### 2. RiskAssessmentService
```php
interface RiskAssessmentService
{
    public function assessLoan(LoanApplication $application): RiskAssessment;
    public function calculateInterestRate(RiskAssessment $assessment): float;
    public function predictDefaultProbability(Loan $loan): float;
}
```

### 3. MatchingEngine
```php
interface MatchingEngine
{
    public function matchLoanWithInvestors(Loan $loan): array;
    public function allocateFunds(InvestmentOrder $order): array;
    public function optimizePortfolio(string $investorId): Portfolio;
}
```

### 4. SecondaryMarketService
```php
interface SecondaryMarketService
{
    public function listPosition(string $investorId, string $loanId, Money $price): Listing;
    public function executeTrade(string $listingId, string $buyerId): Trade;
    public function calculateFairValue(string $loanId): Money;
}
```

## Database Schema

### loans table
```sql
- loan_id (uuid)
- borrower_id
- principal_amount
- interest_rate
- term_months
- status
- funded_at
- disbursed_at
- completed_at
- metadata (json)
```

### loan_investors table
```sql
- loan_id
- investor_id
- invested_amount
- share_percentage
- returns_earned
- status
```

### repayment_schedule table
```sql
- schedule_id
- loan_id
- payment_number
- due_date
- principal_amount
- interest_amount
- status
- paid_at
```

### investment_orders table
```sql
- order_id
- investor_id
- amount
- criteria (json)
- status
- created_at
```

### secondary_market_listings table
```sql
- listing_id
- seller_id
- loan_id
- asking_price
- listed_at
- status
```

## Risk Management

### 1. Credit Risk Mitigation
- Multi-source credit scoring
- Income verification
- Debt-to-income ratio limits
- Collateral requirements for high-risk loans

### 2. Operational Risk Controls
- Automated compliance checks
- Transaction monitoring
- Fraud detection algorithms
- Regular audits

### 3. Market Risk Management
- Interest rate hedging
- Diversification requirements
- Stress testing
- Liquidity buffers

## Compliance & Regulations

### 1. Regulatory Requirements
- Lending license compliance
- Interest rate caps adherence
- Consumer protection laws
- Anti-money laundering (AML)

### 2. Reporting
- Regulatory reports (monthly/quarterly)
- Credit bureau reporting
- Tax reporting (1099-INT for investors)
- Default reporting

### 3. Data Protection
- GDPR compliance for EU users
- PCI DSS for payment data
- Secure document storage
- Data retention policies

## API Endpoints

### Borrower APIs
```
POST   /api/loans/applications          # Submit loan application
GET    /api/loans/applications/{id}     # Get application status
GET    /api/loans/{id}                  # Get loan details
POST   /api/loans/{id}/repayments       # Make repayment
GET    /api/loans/{id}/schedule         # Get repayment schedule
```

### Investor APIs
```
POST   /api/investments/orders          # Place investment order
GET    /api/investments/portfolio       # View portfolio
GET    /api/investments/opportunities   # Browse available loans
POST   /api/investments/auto-invest     # Configure auto-invest
GET    /api/investments/returns         # View returns
```

### Secondary Market APIs
```
GET    /api/market/listings             # Browse listings
POST   /api/market/listings             # Create listing
POST   /api/market/trades               # Execute trade
GET    /api/market/valuations/{loanId}  # Get fair value
```

## Integration Points

### 1. Credit Bureaus
- Experian API integration
- Equifax API integration
- Real-time credit pulls
- Credit report updates

### 2. Payment Processing
- Bank account verification (Plaid)
- ACH payment processing
- Card payment backup
- International transfers

### 3. Identity Verification
- KYC provider integration
- Document verification
- Biometric checks
- Sanctions screening

### 4. Banking Partners
- Escrow account management
- Fund disbursement
- Collection services
- Reconciliation

## Performance Metrics

### Platform KPIs
- Loan origination volume
- Default rate
- Average interest rate
- Investor returns
- Platform fee revenue

### Operational Metrics
- Application approval rate
- Time to funding
- Repayment success rate
- Customer acquisition cost
- Lifetime value

## Security Considerations

### 1. Data Security
- Encryption at rest and in transit
- Secure key management
- Regular security audits
- Penetration testing

### 2. Access Control
- Role-based permissions
- Multi-factor authentication
- API rate limiting
- Session management

### 3. Fraud Prevention
- Identity verification
- Behavioral analysis
- Transaction monitoring
- Blacklist management

## Implementation Status

### Completed Components

#### Domain Layer
- [x] Event-sourced aggregates (LoanApplication, Loan)
- [x] Domain events with separate lending_events table
- [x] Value objects (CreditScore, RiskRating, RepaymentSchedule)
- [x] Custom event repository (LendingEventRepository)
- [x] Exception handling

#### Application Layer
- [x] Loan application workflow (saga pattern)
- [x] Service interfaces (CreditScoringService, RiskAssessmentService)
- [x] Mock implementations for testing
- [x] Activity classes for workflow steps

#### Infrastructure Layer
- [x] Database migrations (loan_applications, loans, loan_repayments)
- [x] Eloquent models with UUID support
- [x] Projectors for read models
- [x] Service provider registration

#### API Layer
- [x] Borrower endpoints (applications, loans, payments)
- [x] Request validation
- [x] Rate limiting and sub-product middleware
- [x] Early settlement functionality

#### Testing
- [x] Workflow tests
- [x] Aggregate tests
- [x] Event sourcing tests

### Implementation Phases

### Phase 1: Core Lending (Completed)
- [x] Loan application aggregate
- [x] Basic credit scoring
- [x] Manual loan approval
- [ ] Simple investor matching

### Phase 2: Automation
- [ ] Automated risk assessment
- [ ] Auto-invest functionality
- [ ] Workflow automation
- [ ] Payment processing

### Phase 3: Advanced Features
- [ ] Secondary market
- [ ] Mobile app
- [ ] International lending
- [ ] Crypto collateral

### Phase 4: Scale & Optimize
- [ ] Machine learning models
- [ ] Advanced analytics
- [ ] Institutional investors
- [ ] Securitization