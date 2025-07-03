# CGO KYC/AML Implementation Guide

## Overview

The CGO (Continuous Growth Offering) module implements a tiered KYC/AML system to ensure regulatory compliance for investment activities. This document outlines the implementation details, configuration, and usage.

## KYC Tiers

The system implements three KYC tiers based on investment amounts:

1. **Basic KYC** - Up to $1,000
   - Required documents: National ID, Selfie
   - Basic identity verification

2. **Enhanced KYC** - Up to $10,000
   - Required documents: Passport, Utility Bill, Selfie
   - PEP screening
   - Sanctions screening
   - Adverse media check

3. **Full KYC** - Above $50,000
   - Required documents: Passport, Utility Bill, Bank Statement, Selfie, Proof of Income
   - All enhanced checks plus:
   - Source of wealth verification
   - Source of funds verification
   - Financial profile assessment

## Implementation Components

### 1. CgoKycService

Located at `app/Services/Cgo/CgoKycService.php`

Key methods:
- `checkKycRequirements(CgoInvestment $investment)` - Determines required KYC level
- `verifyInvestor(CgoInvestment $investment)` - Performs KYC/AML verification
- `createVerificationRequest(CgoInvestment $investment, string $level)` - Creates KYC verification request

### 2. CgoKycController

Located at `app/Http/Controllers/CgoKycController.php`

Endpoints:
- `POST /cgo/kyc/check-requirements` - Check KYC requirements for amount
- `GET /cgo/kyc/status` - Get current KYC status
- `POST /cgo/kyc/submit` - Submit KYC documents
- `GET /cgo/kyc/documents` - List submitted documents
- `POST /cgo/kyc/verify/{investment}` - Verify KYC for specific investment

### 3. Database Schema

#### cgo_investments table additions:
```sql
- kyc_verified_at (timestamp)
- kyc_level (string)
- risk_assessment (decimal)
- aml_checked_at (timestamp)
- aml_flags (json)
```

#### users table additions:
```sql
- country_code (string)
```

#### Investment statuses:
- `pending` - Initial state
- `kyc_required` - KYC verification needed
- `aml_review` - Flagged for AML review
- `confirmed` - KYC/AML passed, payment confirmed
- `cancelled` - Investment cancelled
- `refunded` - Investment refunded

## AML Checks

### 1. PEP (Politically Exposed Person) Screening
- Checks if user is flagged as PEP
- Requires manual review if positive

### 2. Sanctions Screening
- Checks against sanctioned countries list
- Currently includes: Iran (IR), North Korea (KP), Syria (SY), Cuba (CU)
- Blocks investments from sanctioned jurisdictions

### 3. Transaction Pattern Analysis
- Monitors for rapid successive investments (>3 in 7 days)
- Flags significant amount increases (>5x average)

### 4. Risk Assessment
- Calculates risk score based on multiple factors
- Considers: account age, country, PEP status, investment amount

## Usage Examples

### Check KYC Requirements
```php
$kycService = app(CgoKycService::class);
$investment = CgoInvestment::find($id);

$requirements = $kycService->checkKycRequirements($investment);
// Returns:
// [
//     'required_level' => 'enhanced',
//     'current_level' => 'basic',
//     'is_sufficient' => false,
//     'required_documents' => ['passport', 'utility_bill', 'selfie'],
//     'additional_checks' => ['pep_screening', 'sanctions_screening']
// ]
```

### Verify Investor
```php
$verified = $kycService->verifyInvestor($investment);

if (!$verified) {
    // Check investment status for reason
    // Could be: kyc_required, aml_review
}
```

### Submit KYC Documents
```php
// POST /cgo/kyc/submit
{
    "investment_id": "uuid-here",
    "documents": [
        {
            "type": "passport",
            "file": <uploaded-file>
        },
        {
            "type": "utility_bill",
            "file": <uploaded-file>
        }
    ]
}
```

## Configuration

### Environment Variables
```env
# Stripe Configuration
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
CASHIER_CURRENCY=USD

# Coinbase Commerce
COINBASE_COMMERCE_API_KEY=your_api_key
COINBASE_COMMERCE_WEBHOOK_SECRET=your_webhook_secret
```

### Service Configuration
Located in `config/services.php`:
```php
'coinbase_commerce' => [
    'api_key' => env('COINBASE_COMMERCE_API_KEY'),
    'webhook_secret' => env('COINBASE_COMMERCE_WEBHOOK_SECRET'),
],
```

## Testing

### Unit Tests
```bash
./vendor/bin/pest tests/Unit/Services/Cgo/CgoKycServiceTest.php
```

### Test Coverage
- KYC requirement calculation
- Investor verification with different KYC levels
- AML flag detection (PEP, sanctions, patterns)
- Risk level determination
- Verification request creation

## Security Considerations

1. **Data Protection**
   - KYC documents should be encrypted at rest
   - Implement access controls for sensitive data
   - Log all KYC-related actions for audit trail

2. **Compliance**
   - Regular updates to sanctions lists
   - Periodic review of risk thresholds
   - Integration with third-party KYC/AML providers recommended for production

3. **Manual Review Process**
   - Implement workflow for manual review of flagged investments
   - Define clear escalation procedures
   - Maintain documentation of review decisions

## Future Enhancements

1. **Third-party Integrations**
   - Integrate with professional KYC providers (e.g., Jumio, Onfido)
   - Real-time sanctions list updates
   - Enhanced PEP databases

2. **Machine Learning**
   - Implement ML models for transaction pattern analysis
   - Predictive risk scoring
   - Anomaly detection

3. **Reporting**
   - Automated regulatory reporting
   - Suspicious Activity Reports (SARs)
   - Compliance dashboards

## Troubleshooting

### Common Issues

1. **KYC Level Not Updating**
   - Ensure user has required documents verified
   - Check if KYC has expired
   - Verify risk assessment passed

2. **AML Flags Not Clearing**
   - Manual review may be required
   - Check if additional documentation needed
   - Verify sanctions list is up-to-date

3. **Investment Stuck in Review**
   - Check logs for specific flags
   - Ensure all required checks completed
   - May need manual intervention

### Debug Commands
```bash
# Check user KYC status
php artisan tinker
>>> $user = User::find($id);
>>> $user->kyc_status;
>>> $user->kyc_level;

# Check investment flags
>>> $investment = CgoInvestment::find($id);
>>> $investment->aml_flags;
>>> $investment->notes;
```

## References

- [FATF Recommendations](https://www.fatf-gafi.org/recommendations.html)
- [EU AML Directive](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:32018L0843)
- [US Patriot Act](https://www.fincen.gov/resources/statutes-regulations/usa-patriot-act)