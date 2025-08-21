# Lending Domain - AI Agent Guide

## Purpose
This domain handles peer-to-peer lending operations including loan origination, credit scoring, risk assessment, repayment processing, and collection management.

## Key Components

### Aggregates
- **LoanAggregate**: Core loan state management with event sourcing
- **LoanApplicationAggregate**: Application processing and approval workflow
- **RepaymentAggregate**: Payment tracking and amortization

### Services
- **LendingService**: Main service for loan operations
- **CreditScoringService**: Credit evaluation and risk scoring
- **RiskAssessmentService**: Comprehensive risk analysis
- **InterestCalculationService**: Interest computation and amortization
- **CollectionService**: Overdue loan management
- **DemoLendingService**: Demo implementation for testing

### Workflows
- **LoanApplicationWorkflow**: Multi-step application with credit checks
- **LoanDisbursementWorkflow**: Fund disbursement with verification
- **RepaymentProcessingWorkflow**: Payment processing and allocation
- **CollectionWorkflow**: Automated collection procedures

### Activities (Workflow Steps)
- **PerformCreditCheckActivity**: Credit bureau integration
- **CalculateRiskScoreActivity**: Risk scoring algorithm
- **DisburseFundsActivity**: Fund transfer execution
- **ProcessRepaymentActivity**: Payment application logic
- **SendReminderActivity**: Payment reminder notifications

### Events (Event Sourcing)
All events extend `ShouldBeStored`:
- LoanApplicationSubmitted, LoanApplicationApproved, LoanApplicationRejected
- LoanDisbursed, LoanRepaymentReceived, LoanFullyRepaid
- LoanDefaulted, LoanRestructured, LoanWrittenOff
- InterestAccrued, LateFeeCharged, CollectionInitiated

### Models
- **Loan**: Active loan records
- **LoanApplication**: Application details and status
- **LoanRepayment**: Payment history
- **LoanSchedule**: Amortization schedule
- **CreditScore**: User credit scores

## Common Tasks

### Submit Loan Application
```php
use App\Domain\Lending\Services\LendingService;

$service = app(LendingService::class);
$application = $service->submitApplication([
    'borrower_id' => 'user-123',
    'amount' => 1000000, // $10,000 in cents
    'term_months' => 12,
    'purpose' => 'business_expansion',
    'employment_status' => 'employed',
    'annual_income' => 7500000 // $75,000
]);
```

### Approve Loan
```php
use App\Domain\Lending\Services\LendingService;

$service = app(LendingService::class);
$loan = $service->approveLoan(
    applicationId: 'app-456',
    approvedBy: 'officer-789',
    interestRate: 12.5, // 12.5% APR
    terms: ['early_repayment_allowed' => true]
);
```

### Process Repayment
```php
use App\Domain\Lending\Services\LendingService;

$service = app(LendingService::class);
$result = $service->processRepayment(
    loanId: 'loan-123',
    amount: 100000, // $1,000
    paymentMethod: 'bank_transfer',
    reference: 'TXN-789'
);
```

### Calculate Credit Score
```php
use App\Domain\Lending\Services\CreditScoringService;

$service = app(CreditScoringService::class);
$score = $service->calculateScore('user-123');
// Returns: ['score' => 750, 'rating' => 'excellent', 'factors' => [...]]
```

### Get Amortization Schedule
```php
use App\Domain\Lending\Services\InterestCalculationService;

$service = app(InterestCalculationService::class);
$schedule = $service->generateAmortizationSchedule(
    principal: 1000000, // $10,000
    rate: 12.5, // 12.5% APR
    termMonths: 12
);
```

## Testing

### Key Test Files
- `tests/Unit/Domain/Lending/Services/LendingServiceTest.php`
- `tests/Unit/Domain/Lending/Services/CreditScoringServiceTest.php`
- `tests/Feature/Lending/LoanApplicationWorkflowTest.php`
- `tests/Feature/Lending/RepaymentProcessingTest.php`

### Running Tests
```bash
# Run all Lending domain tests
./vendor/bin/pest tests/Unit/Domain/Lending tests/Feature/Lending

# Run workflow tests
./vendor/bin/pest tests/Feature/Lending --filter Workflow
```

## Database

### Main Tables
- `loans`: Active loan records
- `loan_applications`: Application data
- `loan_repayments`: Payment history
- `loan_schedules`: Amortization schedules
- `credit_scores`: Credit score history
- `lending_events`: Event sourcing storage
- `lending_snapshots`: Aggregate snapshots

### Migrations
Located in `database/migrations/`:
- `create_loans_table.php`
- `create_loan_applications_table.php`
- `create_loan_repayments_table.php`
- `create_loan_schedules_table.php`

## API Endpoints

### Loan Applications
- `POST /api/v1/loans/apply` - Submit application
- `GET /api/v1/loans/applications/{id}` - Get application status
- `POST /api/v1/loans/applications/{id}/approve` - Approve application
- `POST /api/v1/loans/applications/{id}/reject` - Reject application

### Loan Management
- `GET /api/v1/loans` - List user loans
- `GET /api/v1/loans/{id}` - Get loan details
- `GET /api/v1/loans/{id}/schedule` - Get payment schedule
- `POST /api/v1/loans/{id}/restructure` - Restructure loan

### Repayments
- `POST /api/v1/loans/{id}/repay` - Make payment
- `GET /api/v1/loans/{id}/payments` - Payment history
- `POST /api/v1/loans/{id}/payoff` - Get payoff amount
- `POST /api/v1/loans/{id}/early-repay` - Early repayment

### Credit & Risk
- `GET /api/v1/credit/score/{userId}` - Get credit score
- `GET /api/v1/credit/report/{userId}` - Credit report
- `POST /api/v1/risk/assess` - Risk assessment

## Configuration

### Environment Variables
```env
# Lending Configuration
LENDING_MIN_LOAN_AMOUNT=10000
LENDING_MAX_LOAN_AMOUNT=10000000
LENDING_MIN_TERM_MONTHS=3
LENDING_MAX_TERM_MONTHS=60

# Interest Rates
LENDING_BASE_RATE=5.0
LENDING_RISK_PREMIUM_EXCELLENT=2.0
LENDING_RISK_PREMIUM_GOOD=4.0
LENDING_RISK_PREMIUM_FAIR=7.0
LENDING_RISK_PREMIUM_POOR=12.0

# Credit Scoring
CREDIT_SCORE_MIN=300
CREDIT_SCORE_MAX=850
CREDIT_SCORE_EXCELLENT_THRESHOLD=750
CREDIT_SCORE_GOOD_THRESHOLD=650
CREDIT_SCORE_FAIR_THRESHOLD=550

# Risk Management
RISK_MAX_DTI_RATIO=0.4
RISK_MIN_CREDIT_SCORE=500
RISK_MAX_LOAN_TO_INCOME=5.0

# Collections
COLLECTION_GRACE_PERIOD_DAYS=5
COLLECTION_LATE_FEE_PERCENTAGE=5.0
COLLECTION_DEFAULT_DAYS=90
```

## Workflows

### Loan Application Workflow
1. Application submission
2. KYC verification
3. Credit check
4. Risk assessment
5. Approval/rejection decision
6. Terms finalization
7. Contract generation

### Disbursement Workflow
1. Contract signing
2. Final verification
3. Fund allocation
4. Transfer execution
5. Confirmation
6. Schedule activation

### Collection Workflow
1. Payment reminder (T-3 days)
2. Due date notification
3. Grace period monitoring
4. Late fee application
5. Collection calls/emails
6. Restructuring option
7. Default procedures

## Best Practices

1. **Always perform credit checks** before approval
2. **Calculate risk-based pricing** for interest rates
3. **Generate amortization schedules** upfront
4. **Track all payments** with proper allocation
5. **Implement grace periods** for payments
6. **Use workflows** for complex processes
7. **Log all decisions** for compliance
8. **Monitor portfolio health** metrics
9. **Automate collections** respectfully
10. **Maintain audit trails** for regulators

## Common Issues

### Application Problems
- Incomplete KYC documentation
- Credit score below threshold
- DTI ratio too high
- Employment verification failed
- Existing loan defaults

### Disbursement Issues
- Bank account verification failed
- Contract not signed
- Regulatory hold
- Insufficient funds in lending pool
- Technical transfer failures

### Repayment Challenges
- Insufficient funds
- Wrong payment amount
- Payment allocation errors
- Early repayment penalties
- Schedule recalculation needed

### Collection Difficulties
- Contact information outdated
- Payment arrangement breaches
- Bankruptcy proceedings
- Dispute resolution needed
- Write-off criteria met

## Risk Management

### Credit Risk
- Minimum credit score requirements
- Income verification mandatory
- DTI ratio limits enforced
- Employment stability checks
- Existing debt analysis

### Operational Risk
- Automated decision limits
- Manual review triggers
- Fraud detection systems
- Document verification
- Identity confirmation

### Portfolio Risk
- Concentration limits
- Vintage analysis
- Default rate monitoring
- Recovery rate tracking
- Stress testing

## AI Agent Tips

- LendingService is the main entry point for operations
- Use DemoLendingService for testing (instant approvals)
- All amounts are in cents (multiply dollars by 100)
- Interest rates are annual percentages (12.5 = 12.5% APR)
- Credit scores range from 300-850
- Workflows handle multi-step processes automatically
- Event sourcing tracks all loan lifecycle events
- Amortization uses monthly compounding by default
- Collection workflows respect grace periods
- Risk scoring considers multiple factors dynamically
- Use activities for external service integration
- Monitor lending_events for audit trail