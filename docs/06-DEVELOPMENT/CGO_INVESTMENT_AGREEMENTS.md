# CGO Investment Agreement System

> Demo module: reference software that generates sample agreement and certificate documents for an investment-intake workflow. It is not an offer of securities.
>
> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Overview

The CGO investment agreement system generates sample documents for demo investments. It produces an investment agreement and a share certificate as PDF files from Blade templates. It stores the files outside the public folder and serves them only to their owner. The CGO module is disabled on the production site through `MODULES_DISABLED`.

## Components

### 1. InvestmentAgreementService

Located at `app/Domain/Cgo/Services/InvestmentAgreementService.php`

Key methods:
- `generateAgreement(CgoInvestment $investment)` generates the investment agreement PDF.
- `generateCertificate(CgoInvestment $investment)` generates the share certificate PDF.
- `sendAgreementToInvestor(CgoInvestment $investment)` sends the agreement by email.

### 2. CgoAgreementController

Located at `app/Http/Controllers/CgoAgreementController.php`

Endpoints:
- `POST /cgo/agreement/{investment}/generate` generates the investment agreement.
- `GET /cgo/agreement/{investment}/download` downloads the investment agreement.
- `POST /cgo/agreement/{investment}/sign` marks the agreement as signed.
- `POST /cgo/certificate/{investment}/generate` generates the share certificate.
- `GET /cgo/certificate/{investment}/download` downloads the share certificate.
- `GET /cgo/agreement/{investment}/preview` previews the agreement as an inline PDF. Only `super_admin` users can open it.

### 3. Database Schema

Added fields to `cgo_investments` table:
```sql
- agreement_path (string) - Storage path for agreement PDF
- agreement_generated_at (timestamp) - When agreement was generated
- agreement_signed_at (timestamp) - When agreement was signed
- certificate_path (string) - Storage path for certificate PDF
```

Added fields to `cgo_pricing_rounds` table:
```sql
- name (string) - Round name (e.g., "Series A")
- pre_money_valuation (decimal) - Company valuation before investment
- post_money_valuation (decimal) - Company valuation after investment
```

## Features

### Investment Agreement Generation

A generated agreement contains these sections:

1. **Party Information**
   - Company details (name, registration, address)
   - Investor details (name, email, address, country)

2. **Investment Details**
   - Investment amount and currency
   - Number of shares purchased
   - Price per share
   - Ownership percentage
   - Investment tier (Bronze/Silver/Gold)
   - Funding round information
   - Company valuation

3. **Investment Terms**
   - Lock-in period (12 months)
   - Dividend rights (pro-rata based on ownership)
   - Voting rights (one vote per share)
   - Transfer restrictions
   - Dilution protection (Gold tier only)
   - Information rights (varies by tier)
   - Board observer rights (Gold tier >$100k)

4. **Legal Sections**
   - Representations and warranties
   - Investment risks disclosure
   - Confidentiality clause
   - Governing law
   - Signature blocks

### Share Certificate Generation

A share certificate contains:
- Certificate number (auto-generated)
- Investor name
- Investment details
- Share ownership information
- Issue date
- Company seal
- Signature blocks for executives

### Tier-Specific Terms

#### Bronze Tier
- Basic terms
- Annual financial statements
- No dilution protection

#### Silver Tier
- Enhanced information rights (semi-annual statements)
- Priority support

#### Gold Tier
- Anti-dilution protection (24 months)
- Quarterly financial statements
- Board observer rights (investments >$100k)
- Direct access to management

## Usage

### Generate Agreement
```php
$agreementService = app(InvestmentAgreementService::class);
$investment = CgoInvestment::find($id);

// Generate agreement PDF
$path = $agreementService->generateAgreement($investment);
```

### Generate Certificate
```php
// Investment must be confirmed
if ($investment->status === 'confirmed') {
    $path = $agreementService->generateCertificate($investment);
}
```

### API Usage
```javascript
// Generate agreement
fetch(`/cgo/agreement/${investmentUuid}/generate`, {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
    },
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        window.location.href = data.download_url;
    }
});

// Mark as signed
fetch(`/cgo/agreement/${investmentUuid}/sign`, {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
    },
    body: JSON.stringify({
        signature_data: base64SignatureData
    })
});
```

## Configuration

### PDF Settings
PDFs are rendered with `barryvdh/laravel-dompdf`. The project does not publish a `config/dompdf.php`, so the package defaults apply. To change them, publish the config:
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### Company Information
The service reads the company details from `config('app.company_name')`, `config('app.company_registration')`, `config('app.company_address')`, `config('app.company_email')`, `config('app.ceo_name')` and `config('app.cfo_name')`. `config/app.php` does not define these keys yet, so environment variables such as `COMPANY_NAME` have no effect. The documents always show the built-in placeholder values, such as "FinAegis Ltd" and "John Doe", until the keys are added to `config/app.php`:
```php
// config/app.php (not present yet)
'company_name'         => env('COMPANY_NAME', 'FinAegis Ltd'),
'company_registration' => env('COMPANY_REGISTRATION'),
'company_address'      => env('COMPANY_ADDRESS'),
'company_email'        => env('COMPANY_EMAIL'),
'ceo_name'             => env('CEO_NAME'),
'cfo_name'             => env('CFO_NAME'),
```

## Security Considerations

1. **Access control.** Users can open only their own investment documents. The admin preview requires the `super_admin` role. Documents are kept in non-public storage.

2. **Document integrity.** PDFs are generated on the server. Unique file names prevent URL guessing. The signature data is stored with its metadata.

3. **Audit trail.** Every document generation is logged. The generation and signing times are stored, together with the IP address and user agent.

## Testing

### Service Tests
```bash
./vendor/bin/pest tests/Domain/Cgo/Services/InvestmentAgreementServiceTest.php
```

There is no separate controller test for `CgoAgreementController` yet.

### What the Tests Cover
The tests cover the tier-specific terms for Bronze, Silver, Gold and unknown tiers, the list of investment risk disclosures and the file name format. They do not render the PDFs.

## Customization

### Modifying Templates

Agreement template: `resources/views/cgo/agreements/investment-agreement.blade.php`
Certificate template: `resources/views/cgo/agreements/investment-certificate.blade.php`

### Adding New Terms
```php
// In InvestmentAgreementService::getInvestmentTerms()
switch ($investment->tier) {
    case 'platinum': // New tier
        $baseTerms['special_rights'] = 'Quarterly dividends';
        break;
}
```

### Styling PDFs
Templates use inline CSS for PDF compatibility:
```html
<style>
    @page { margin: 2cm; }
    body { font-family: 'Times New Roman', serif; }
    .header { text-align: center; }
</style>
```

## Troubleshooting

### Common Issues

1. **PDF generation fails.** Check the storage permissions, that dompdf is installed and the PHP memory limit for large PDFs.

2. **Missing data in the PDF.** Check that the investment has a user and that the pricing round data exists. Look for null values in the template.

3. **Download problems.** Check that the file exists in storage, that the storage disk is configured and that the right headers are sent.

### Debug Commands
```bash
# Test PDF generation
php artisan tinker
>>> $investment = CgoInvestment::first();
>>> $service = app(InvestmentAgreementService::class);
>>> $path = $service->generateAgreement($investment);

# Check storage
>>> Storage::exists($path);
>>> Storage::size($path);
```

## Future Enhancements

1. **Digital signatures.** Add an e-signature service, blockchain-based signatures and multi-party signing.

2. **Template management.** Add an admin interface for templates, several languages and version control for agreements.

3. **More features.** Add batch generation, reminders for unsigned agreements and integration with legal compliance systems.
