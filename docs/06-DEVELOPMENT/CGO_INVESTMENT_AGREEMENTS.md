# CGO Investment Agreement System

## Overview

The CGO Investment Agreement System generates legal documents for investments including investment agreements and share certificates. The system uses PDF generation with customizable templates and provides secure document storage and retrieval.

## Components

### 1. InvestmentAgreementService

Located at `app/Services/Cgo/InvestmentAgreementService.php`

Key methods:
- `generateAgreement(CgoInvestment $investment)` - Generates investment agreement PDF
- `generateCertificate(CgoInvestment $investment)` - Generates share certificate PDF
- `sendAgreementToInvestor(CgoInvestment $investment)` - Sends agreement via email

### 2. CgoAgreementController

Located at `app/Http/Controllers/CgoAgreementController.php`

Endpoints:
- `POST /cgo/agreement/{investment}/generate` - Generate investment agreement
- `GET /cgo/agreement/{investment}/download` - Download investment agreement
- `POST /cgo/agreement/{investment}/sign` - Mark agreement as signed
- `POST /cgo/certificate/{investment}/generate` - Generate share certificate
- `GET /cgo/certificate/{investment}/download` - Download share certificate
- `GET /cgo/agreement/{investment}/preview` - Preview agreement (admin only)

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

The system generates comprehensive investment agreements including:

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

Professional share certificates include:
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
```php
// config/dompdf.php
return [
    'show_warnings' => false,
    'orientation' => 'portrait',
    'default_font' => 'serif',
    'dpi' => 96,
    'enable_remote' => true,
    'enable_html5_parser' => true,
];
```

### Company Information
Add to `.env`:
```env
COMPANY_NAME="FinAegis Ltd"
COMPANY_REGISTRATION="12345678"
COMPANY_ADDRESS="123 Business St, London, UK"
COMPANY_EMAIL="invest@finaegis.com"
CEO_NAME="John Doe"
CFO_NAME="Jane Smith"
```

## Security Considerations

1. **Access Control**
   - Users can only access their own investment documents
   - Admin preview requires super_admin role
   - Documents stored in non-public storage

2. **Document Integrity**
   - PDFs are generated server-side
   - Unique filenames prevent URL guessing
   - Signature data stored with metadata

3. **Audit Trail**
   - All document generation logged
   - Timestamps for generation and signing
   - IP address and user agent captured

## Testing

### Unit Tests
```bash
./vendor/bin/pest tests/Unit/Services/Cgo/InvestmentAgreementServiceTest.php
```

### Feature Tests
```bash
./vendor/bin/pest tests/Feature/CgoAgreementControllerTest.php
```

### Test Coverage
- Agreement generation with all data points
- Certificate generation for confirmed investments
- Access control and ownership verification
- Tier-specific terms application
- Error handling for missing data

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

1. **PDF Generation Fails**
   - Check storage permissions
   - Verify dompdf is installed
   - Check memory limits for large PDFs

2. **Missing Data in PDF**
   - Ensure investment has user relationship
   - Verify pricing round data exists
   - Check for null values in template

3. **Download Issues**
   - Verify file exists in storage
   - Check storage disk configuration
   - Ensure proper headers are sent

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

1. **Digital Signatures**
   - Integrate DocuSign or similar
   - Blockchain-based signatures
   - Multi-party signing workflows

2. **Template Management**
   - Admin interface for templates
   - Multiple language support
   - Version control for agreements

3. **Advanced Features**
   - Batch generation for multiple investments
   - Automated reminders for unsigned agreements
   - Integration with legal compliance systems