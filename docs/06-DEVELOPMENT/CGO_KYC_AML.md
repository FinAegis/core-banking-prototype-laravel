# CGO KYC/AML Implementation Guide

## Overview

The CGO (Continuous Growth Offering) demo module runs tiered Know Your Customer (KYC) and anti-money laundering (AML) checks for its investment intake. These checks do not by themselves make a deployment or offering compliant. The CGO module is a demo and is not an offer of securities. It is disabled on the production site through `MODULES_DISABLED`. This document describes the implementation, the configuration and how to use it.

## KYC Tiers

The service sets one of three KYC tiers from the user's total investment. That total is the amount the user has already invested plus the new investment. The thresholds are constants in `CgoKycService`.

1. **Basic KYC** applies up to $1,000 (`BASIC_KYC_THRESHOLD`). It requires a national ID and a selfie, and runs a basic identity check.

2. **Enhanced KYC** applies above $1,000 and up to $10,000 (`ENHANCED_KYC_THRESHOLD`). It requires a passport, a utility bill and a selfie. It adds screening for politically exposed persons (PEP), sanctions screening and an adverse media check.

3. **Full KYC** applies above $10,000. It requires a passport, a utility bill, a bank statement, a selfie and proof of income. It adds source of wealth and source of funds checks and a financial profile assessment.

The AML checks run for investments of $10,000 or more. From $50,000 (`FULL_KYC_THRESHOLD`) the AML check also requires a verified source of funds, and the amount counts as a risk factor.

## Implementation Components

### 1. CgoKycService

Located at `app/Domain/Cgo/Services/CgoKycService.php`

Key methods:
- `checkKycRequirements(CgoInvestment $investment)` returns the required KYC level.
- `verifyInvestor(CgoInvestment $investment)` runs the KYC and AML checks.
- `createVerificationRequest(CgoInvestment $investment, string $level)` creates a KYC verification request.

### 2. CgoKycController

Located at `app/Http/Controllers/CgoKycController.php`

Endpoints:
- `POST /cgo/kyc/check-requirements` checks the KYC requirements for an amount.
- `GET /cgo/kyc/status` returns the current KYC status.
- `POST /cgo/kyc/submit` submits KYC documents.
- `GET /cgo/kyc/documents` lists the submitted documents.
- `POST /cgo/kyc/verify/{investment}` verifies KYC for one investment.

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
- `pending` is the initial state.
- `kyc_required` means KYC verification is needed.
- `aml_review` means the investment is flagged for AML review.
- `confirmed` means KYC and AML passed and the payment is confirmed.
- `cancelled` means the investment was cancelled.
- `refunded` means the investment was refunded.

## AML Checks

### 1. Politically Exposed Person (PEP) Screening
The check looks at whether the user is flagged as a PEP. A positive result needs a manual review.

### 2. Sanctions Screening
The check compares the user's country with a hard-coded list of sanctioned countries. The list has Iran (IR), North Korea (KP), Syria (SY) and Cuba (CU). Investments from these countries are blocked. This is a country check only, not screening against a sanctions list provider.

### 3. Transaction Pattern Analysis
The check flags more than 3 investments in 7 days. It also flags an investment that is more than 5 times the user's average confirmed investment.

### 4. Risk Assessment
The service counts risk factors such as account age, country, PEP status and investment amount, and derives a risk level from them.

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
CASHIER_CURRENCY=eur

# Coinbase Commerce
CGO_COINBASE_COMMERCE_ENABLED=false
COINBASE_COMMERCE_API_KEY=your_api_key
COINBASE_COMMERCE_WEBHOOK_SECRET=your_webhook_secret
```

The CGO settings live in `config/cgo.php`.

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
./vendor/bin/pest tests/Domain/Cgo/Services/CgoKycServiceTest.php
```

### What the Tests Cover
The tests cover the KYC requirement calculation and investor verification at each KYC level. They also cover AML flag detection for PEP, sanctions and patterns, the risk level and the creation of verification requests.

## Security Considerations

1. **Data protection.** Encrypt KYC documents at rest. Restrict access to sensitive data. Log every KYC action for the audit trail.

2. **Compliance.** Update the sanctions data regularly and review the risk thresholds from time to time. Any real deployment needs a third-party KYC and AML provider.

3. **Manual review.** Set up a workflow for the manual review of flagged investments. Define clear escalation steps and record every review decision.

## Future Enhancements

1. **Third-party integrations.** Connect a professional KYC provider, real-time sanctions list updates and a PEP database. The platform already has an Ondato adapter for user identity checks (`users.kyc_status`).

2. **Machine learning.** Add models for transaction pattern analysis, predictive risk scoring and anomaly detection.

3. **Reporting.** Add automated regulatory reports, Suspicious Activity Reports (SARs) and compliance dashboards for the CGO flow.

## Troubleshooting

### Common Issues

1. **KYC level not updating.** Check that the user's required documents are verified, that the KYC has not expired and that the risk assessment passed.

2. **AML flags not clearing.** The investment may need a manual review or more documents. Check that the sanctions data is up to date.

3. **Investment stuck in review.** Read the logs for the specific flags and check that all required checks completed. The investment may need manual intervention.

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