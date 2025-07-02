# CGO (Continuous Growth Offering) Documentation

## Overview

The Continuous Growth Offering (CGO) is an innovative investment mechanism that allows users to invest in the FinAegis platform's growth. Unlike traditional investment rounds, CGO provides continuous access to investment opportunities with multiple payment methods including cryptocurrency, bank transfers, and card payments.

## Features

### 1. Investment Options

Users can invest with flexible amounts:
- Minimum investment: $100
- Maximum investment: Configurable per user/organization
- Multiple payment methods supported

### 2. Payment Methods

#### Cryptocurrency Payments
- Supported currencies: BTC, ETH, USDT
- QR code generation for easy mobile payments
- Real-time exchange rate conversion
- Automatic wallet address generation

#### Bank Transfer
- SEPA transfers for EUR
- ACH transfers for USD
- Wire transfers for large amounts
- Reference number tracking

#### Card Payments
- Integration ready for Stripe
- Support for major credit/debit cards
- 3D Secure authentication
- Instant processing

### 3. Investment Tracking

- Real-time investment status
- Transaction history
- Investment certificates
- Performance tracking

## Implementation

### Controller: CgoController

Located at `app/Http/Controllers/CgoController.php`

Key methods:
- `index()` - Display CGO landing page
- `invest()` - Process investment form submission
- `processCryptoPayment()` - Handle crypto payment flow
- `processBankTransfer()` - Handle bank transfer flow
- `processCardPayment()` - Handle card payment flow

### Database Schema

#### CGO Investments Table
```sql
CREATE TABLE cgo_investments (
    id BIGINT PRIMARY KEY,
    user_id BIGINT FOREIGN KEY,
    amount DECIMAL(20,2),
    currency VARCHAR(3),
    payment_method VARCHAR(50),
    payment_details JSON,
    status VARCHAR(50),
    reference_number VARCHAR(100) UNIQUE,
    invested_at TIMESTAMP,
    confirmed_at TIMESTAMP NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

### Views

#### Main CGO Page
`resources/views/cgo/index.blade.php`
- Investment benefits display
- Investment calculator
- Payment method selection
- Terms and conditions

#### Payment Confirmation Views

1. **Crypto Payment** (`crypto-payment.blade.php`)
   - Displays wallet address
   - QR code for mobile scanning
   - Payment instructions
   - Reference number

2. **Bank Transfer** (`bank-transfer.blade.php`)
   - Bank account details
   - SWIFT/IBAN information
   - Reference number (MUST be included)
   - Processing time expectations

3. **Card Payment** (`card-payment.blade.php`)
   - Stripe integration placeholder
   - Alternative payment instructions
   - Security information

### Routes

```php
// Public CGO landing
Route::get('/cgo', [CgoController::class, 'index'])->name('cgo');

// Authenticated investment routes
Route::middleware(['auth'])->group(function () {
    Route::post('/cgo/invest', [CgoController::class, 'invest'])
        ->name('cgo.invest');
    Route::get('/cgo/status/{reference}', [CgoController::class, 'status'])
        ->name('cgo.status');
});
```

## User Flow

### Investment Process

1. **Landing Page**
   - User visits `/cgo`
   - Reviews investment information
   - Clicks "Invest Now"

2. **Authentication Check**
   - If not logged in, redirect to login
   - If logged in, show investment form

3. **Investment Form**
   - Select investment amount
   - Choose payment method
   - Accept terms and conditions
   - Submit form

4. **Payment Processing**
   - Generate unique reference number
   - Create investment record (pending status)
   - Route to appropriate payment method

5. **Payment Confirmation**
   - Display payment instructions
   - Show reference number prominently
   - Provide support contact

6. **Post-Payment**
   - Monitor for payment confirmation
   - Update investment status
   - Send confirmation email
   - Issue investment certificate

## Security Considerations

### 1. Authentication
- All investment actions require authentication
- Session validation on each step
- CSRF protection on forms

### 2. Payment Security
- Unique reference numbers prevent duplicate payments
- Payment details stored encrypted
- PCI compliance for card payments
- Crypto address validation

### 3. Transaction Integrity
- Database transactions for atomic operations
- Event sourcing for audit trail
- Webhook validation for payment providers

## Testing

### Manual Testing Checklist

1. **Investment Flow**
   - [ ] Can access CGO page without login
   - [ ] Redirect to login when clicking "Invest Now"
   - [ ] Investment form validates amount
   - [ ] Payment method selection works
   - [ ] Terms acceptance required

2. **Payment Methods**
   - [ ] Crypto payment shows correct address
   - [ ] QR code generates properly
   - [ ] Bank details display correctly
   - [ ] Reference number is unique
   - [ ] Card payment placeholder works

3. **Error Handling**
   - [ ] Invalid amount shows error
   - [ ] Missing payment method shows error
   - [ ] Database errors handled gracefully
   - [ ] Session timeout handled

### Automated Tests

```php
test('can view cgo page', function () {
    $response = $this->get('/cgo');
    $response->assertStatus(200);
    $response->assertSee('Continuous Growth Offering');
});

test('investment requires authentication', function () {
    $response = $this->post('/cgo/invest', [
        'amount' => 1000,
        'payment_method' => 'crypto',
    ]);
    $response->assertRedirect('/login');
});

test('can create investment', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)->post('/cgo/invest', [
        'amount' => 1000,
        'payment_method' => 'crypto',
        'crypto_currency' => 'BTC',
        'accept_terms' => true,
    ]);
    
    $response->assertStatus(200);
    $this->assertDatabaseHas('cgo_investments', [
        'user_id' => $user->id,
        'amount' => 1000,
        'status' => 'pending',
    ]);
});
```

## Configuration

### Environment Variables
```env
# CGO Settings
CGO_MIN_INVESTMENT=100
CGO_MAX_INVESTMENT=1000000
CGO_ENABLED=true

# Crypto Wallets
CGO_BTC_WALLET=bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh
CGO_ETH_WALLET=0x71C7656EC7ab88b098defB751B7401B5f6d8976F
CGO_USDT_WALLET=0x71C7656EC7ab88b098defB751B7401B5f6d8976F

# Bank Details
CGO_BANK_NAME="FinAegis Bank"
CGO_BANK_IBAN="GB29NWBK60161331926819"
CGO_BANK_SWIFT="NWBKGB2L"
```

### Configuration File
`config/cgo.php`
```php
return [
    'enabled' => env('CGO_ENABLED', true),
    'min_investment' => env('CGO_MIN_INVESTMENT', 100),
    'max_investment' => env('CGO_MAX_INVESTMENT', 1000000),
    
    'crypto_wallets' => [
        'BTC' => env('CGO_BTC_WALLET'),
        'ETH' => env('CGO_ETH_WALLET'),
        'USDT' => env('CGO_USDT_WALLET'),
    ],
    
    'bank_details' => [
        'name' => env('CGO_BANK_NAME'),
        'iban' => env('CGO_BANK_IBAN'),
        'swift' => env('CGO_BANK_SWIFT'),
    ],
];
```

## Future Enhancements

1. **Automated Payment Processing**
   - Stripe integration for card payments
   - Crypto payment monitoring via webhooks
   - Bank transfer reconciliation

2. **Investment Management**
   - Investment portfolio dashboard
   - Performance tracking
   - Dividend distribution

3. **Compliance Features**
   - KYC verification for large investments
   - AML checks
   - Investment limits per jurisdiction

4. **Marketing Tools**
   - Referral program
   - Early bird bonuses
   - Volume discounts

## Troubleshooting

### Common Issues

1. **"Investment not working" Error**
   - Check database transaction is committed before redirect
   - Verify payment method routing logic
   - Ensure session is maintained

2. **QR Code Not Displaying**
   - Verify SimpleSoftwareIO/simple-qrcode package installed
   - Check crypto wallet addresses are configured
   - Ensure GD or Imagick PHP extension enabled

3. **Reference Number Issues**
   - Verify uniqueness constraint on database
   - Check reference generation logic
   - Ensure proper error handling

### Debug Steps

1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify database records created
3. Test payment method routing
4. Validate form submissions
5. Monitor browser console for JavaScript errors

## Support

For CGO-related issues:
- Technical Support: dev@finaegis.com
- Investment Questions: invest@finaegis.com
- General Inquiries: support@finaegis.com