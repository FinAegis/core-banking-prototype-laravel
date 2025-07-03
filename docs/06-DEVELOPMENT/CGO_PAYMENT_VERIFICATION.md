# CGO Payment Verification Dashboard

This document describes the payment verification system for CGO (Continuous Growth Offering) investments, including both admin and investor interfaces.

## Overview

The payment verification system provides:
- **Admin Dashboard**: Centralized interface for verifying and managing pending payments
- **Investor Dashboard**: Self-service payment status checking and instruction retrieval
- **Automated Verification**: Integration with Stripe and Coinbase Commerce APIs
- **Manual Verification**: Bank transfer confirmation workflow

## Admin Payment Verification Dashboard

### Location
- **URL**: `/admin/cgo-payment-verification-dashboard`
- **Menu**: CGO Management → Payment Verification
- **File**: `app/Filament/Pages/CgoPaymentVerificationDashboard.php`

### Features

#### 1. Real-time Payment Overview
- Displays all pending and processing payments
- Auto-refreshes every 10 seconds
- Shows urgent payments (>24 hours old) with visual indicators

#### 2. Payment Status Widget
- **Pending Verifications**: Count of payments awaiting verification
- **Pending Amount**: Total value of unverified payments
- **Urgent Payments**: Payments older than 24 hours
- **Payment Method Breakdown**: Distribution by card/crypto/bank

#### 3. Verification Actions

**Automatic Verification** (Stripe & Crypto):
```php
// Queues verification job
Queue::push(new VerifyCgoPayment($investment));
```

**Manual Verification** (Bank Transfers):
- Record transaction reference
- Confirm amount received
- Add verification notes
- Updates investment status and pricing round

#### 4. Bulk Operations
- Select multiple payments for batch verification
- Useful for processing multiple card/crypto payments

### Payment States

| Status | Description | Next Action |
|--------|-------------|-------------|
| `pending` | Payment initiated but not confirmed | Verify payment |
| `processing` | Verification in progress | Wait for completion |
| `completed` | Payment confirmed | None required |
| `failed` | Payment failed or cancelled | Contact investor |

## Investor Payment Verification Interface

### Location
- **URL**: `/cgo/payment-verification`
- **Menu**: My Investments → Payment Verification
- **File**: `resources/views/cgo/payment-verification.blade.php`

### Features

#### 1. Payment Status Display
- Shows all pending payments for the logged-in user
- Displays payment method-specific instructions
- Real-time status updates

#### 2. Self-Service Actions

**Check Status**:
- Manually trigger payment verification
- Receives immediate feedback
- Auto-redirects on successful verification

**Resend Instructions**:
- Available for bank transfer and crypto payments
- Sends payment details to registered email
- Prevents duplicate sends

#### 3. Payment Timeline
- Visual timeline of payment events
- Shows: initiation, KYC verification, payment confirmation
- Updates dynamically via AJAX

#### 4. Auto-refresh
- Automatically checks payment status every 30 seconds
- Only for card and crypto payments (not bank transfers)
- Prevents manual refresh spam

## Payment Verification Service

### Core Service
`app/Services/Cgo/PaymentVerificationService.php`

### Methods

#### verifyStripePayment()
```php
public function verifyStripePayment(CgoInvestment $investment): array
{
    $paymentIntent = PaymentIntent::retrieve($investment->stripe_payment_intent_id);
    
    if ($paymentIntent->status === 'succeeded') {
        $investment->update([
            'payment_status' => 'completed',
            'status' => 'confirmed',
            'payment_completed_at' => now(),
        ]);
        
        return ['verified' => true, 'status' => 'completed'];
    }
    
    return ['verified' => false, 'status' => $paymentIntent->status];
}
```

#### verifyCoinbasePayment()
```php
public function verifyCoinbasePayment(CgoInvestment $investment): array
{
    $charge = $this->coinbaseService->getCharge($investment->coinbase_charge_id);
    
    if (in_array($charge['timeline'][0]['status'], ['COMPLETED', 'RESOLVED'])) {
        $investment->update([
            'payment_status' => 'completed',
            'status' => 'confirmed',
            'payment_completed_at' => now(),
        ]);
        
        return ['verified' => true, 'status' => 'completed'];
    }
    
    return ['verified' => false, 'status' => $charge['timeline'][0]['status']];
}
```

## Database Schema

### Key Fields for Payment Tracking

```sql
-- cgo_investments table
payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded')
payment_method ENUM('stripe', 'crypto', 'bank_transfer')
payment_completed_at TIMESTAMP NULL
payment_failed_at TIMESTAMP NULL
payment_failure_reason TEXT NULL

-- Payment method specific fields
stripe_session_id VARCHAR(255) NULL
stripe_payment_intent_id VARCHAR(255) NULL
coinbase_charge_id VARCHAR(255) NULL
coinbase_charge_code VARCHAR(255) NULL
bank_transfer_reference VARCHAR(255) NULL
crypto_tx_hash VARCHAR(255) NULL
amount_paid INTEGER NULL -- Amount actually received (in cents)
```

## Security Considerations

### Access Control
- Admin dashboard requires authentication and admin role
- Investors can only view/verify their own payments
- All actions are logged with user ID and timestamp

### Payment Verification
- Stripe webhooks use signature verification
- Coinbase webhooks use shared secret validation
- Manual verification requires admin approval
- All verification attempts are logged

### Data Protection
- Payment details are encrypted at rest
- API keys stored in environment variables
- Sensitive data masked in logs
- PCI compliance for card payments (via Stripe)

## Configuration

### Environment Variables
```env
# Stripe Configuration
STRIPE_KEY=sk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Coinbase Commerce
COINBASE_COMMERCE_API_KEY=...
COINBASE_COMMERCE_WEBHOOK_SECRET=...

# Bank Transfer Details
CGO_BANK_NAME="Example Bank"
CGO_BANK_ACCOUNT="1234567890"
CGO_BANK_ROUTING="021000021"
CGO_BANK_SWIFT="EXAMPUS33"
```

### Queue Configuration
Payment verification jobs run on the `payments` queue:
```bash
php artisan queue:work --queue=payments,default
```

## Troubleshooting

### Common Issues

1. **Payment not updating after verification**
   - Check queue workers are running
   - Verify API credentials are correct
   - Check webhook endpoints are accessible

2. **Bulk verification not working**
   - Ensure selected payments are eligible (not bank transfers)
   - Check for API rate limits
   - Verify queue processing

3. **Timeline not updating**
   - Check AJAX endpoints are accessible
   - Verify CSRF token is included
   - Check browser console for errors

### Manual Verification Process

For bank transfers that cannot be auto-verified:
1. Obtain bank statement or transaction proof
2. Locate payment by reference number
3. Verify amount matches expected
4. Use manual verification form
5. Add detailed notes for audit trail

## API Endpoints

### Admin API (Internal)
- `POST /admin/api/cgo/verify-payment/{id}` - Trigger verification
- `POST /admin/api/cgo/manual-verify/{id}` - Manual verification
- `POST /admin/api/cgo/mark-failed/{id}` - Mark as failed

### Public API (Investor)
- `GET /cgo/payment-verification` - List pending payments
- `POST /cgo/payment-verification/{id}/check` - Check status
- `POST /cgo/payment-verification/{id}/resend` - Resend instructions
- `GET /cgo/payment-verification/{id}/timeline` - Get timeline

## Testing

### Unit Tests
```bash
./vendor/bin/pest tests/Feature/CgoPaymentVerificationTest.php
```

### Manual Testing Checklist
- [ ] Admin can view all pending payments
- [ ] Automatic verification works for Stripe
- [ ] Automatic verification works for Coinbase
- [ ] Manual verification updates database correctly
- [ ] Investor can check their payment status
- [ ] Timeline updates reflect all events
- [ ] Email notifications are sent correctly
- [ ] Bulk verification processes multiple payments

## Future Enhancements

1. **Webhook Integration**
   - Real-time updates via websockets
   - Push notifications for status changes
   - Slack/Discord integration for admins

2. **Advanced Analytics**
   - Payment success rates by method
   - Average verification time
   - Failed payment analysis

3. **Automation**
   - OCR for bank statement processing
   - Machine learning for fraud detection
   - Automated reconciliation

4. **Mobile App**
   - Native payment status checking
   - Push notifications
   - QR code for bank reference