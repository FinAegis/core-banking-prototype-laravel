# CGO Payment Verification

This document describes the payment verification flow of the CGO (Continuous Growth Offering) demo module. The module is reference software and not an offer of securities. The document covers the investor page, the automatic checks and the admin tools.

## Overview

The payment verification flow checks whether a CGO demo investment has been paid. It has three parts:
- An investor page where investors check the status of their own payments and get the payment instructions again.
- Automatic verification of card payments through Stripe and crypto payments through Coinbase Commerce. The `cgo:verify-payments` command runs it on a schedule.
- Manual confirmation of bank transfers by an administrator.

The CGO module is disabled on the production site through `MODULES_DISABLED`, and its scheduled jobs then do not run.

## Admin Verification

The former Filament page `CgoPaymentVerificationDashboard` was removed with the orphaned legacy Filament tree (PR #1141). It had never been registered in a panel. The admin endpoints under `/admin/api/cgo/...` do not exist either. Administrators now work with these tools:
- The **CGO Investments** resource at `/admin/cgo-investments` in the Platform group. It is an empty scaffold today. It has no table columns and no form fields, so it cannot be used to review or confirm payments yet.
- The `cgo:verify-payments` command:

```bash
# Verify all pending payments
php artisan cgo:verify-payments

# Verify one investment
php artisan cgo:verify-payments --investment=<uuid>

# Also handle expired payments
php artisan cgo:verify-payments --expired
```

The scheduler in `routes/console.php` runs `cgo:verify-payments` every 30 minutes and `cgo:verify-payments --expired` every two hours, when the Cgo module is enabled. The `VerifyCgoPayment` job in `app/Domain/Cgo/Jobs/` verifies a single investment on the queue:
```php
// Queues verification job
VerifyCgoPayment::dispatch($investment);
```

To confirm a bank transfer by hand, set `bank_transfer_reference` on the investment and set `payment_status` to `confirmed`, for example in `php artisan tinker`. The next verification run then confirms the investment and updates the pricing round.

### Payment States

`payment_status` is a string column. These are the values the code writes:

| Status | Description | Next Action |
|--------|-------------|-------------|
| `pending` | Payment started but not confirmed | Verify the payment |
| `completed` | Stripe reported the card payment as paid | None |
| `confirmed` | The verification service or the Coinbase webhook confirmed the payment, or an administrator confirmed a bank transfer | None |
| `failed` | The payment failed or was cancelled | Contact the investor |
| `expired` | The payment window passed without a payment | None |
| `verification_failed` | The verification job failed and the payment needs a manual review | Check the logs |
| `refunded` | The payment was refunded | None |

## Investor Payment Verification Interface

### Location
- **URL**: `/cgo/payment-verification`
- **Controller**: `app/Http/Controllers/CgoPaymentVerificationController.php`
- **View**: `resources/views/cgo/payment-verification.blade.php`

### Features

#### 1. Payment Status Display
The page shows all pending payments of the signed-in user, with instructions for each payment method.

#### 2. Self-Service Actions

**Check status** runs the payment verification on request. The investor sees the result at once and is redirected when the payment is verified.

**Resend instructions** works for bank transfer and crypto payments. It sends the payment details to the registered email address again and prevents duplicate sends.

#### 3. Payment Timeline
The page shows a timeline of the payment events, such as initiation, KYC verification and payment confirmation. It loads the timeline over AJAX.

#### 4. Auto-refresh
For card and crypto payments, the page checks the payment status every 30 seconds. Bank transfers are not refreshed automatically.

## Payment Verification Service

### Core Service
`app/Domain/Cgo/Services/PaymentVerificationService.php`

### Methods

The public methods are `verifyPayment()`, `verifyPendingPayments()`, `markPaymentFailed()`, `isPaymentExpired()` and `handleExpiredPayments()`. `verifyPayment()` picks a check by payment method: `card` goes to `verifyStripePayment()`, `crypto` to `verifyCryptoPayment()` and `bank_transfer` to `verifyBankTransfer()`. When a check passes, the service confirms the investment. The examples below are simplified. The real methods are protected and return a boolean, and a crypto payment without a Coinbase charge passes when a transaction hash is recorded.

#### verifyStripePayment()
```php
public function verifyStripePayment(CgoInvestment $investment): array
{
    $paymentIntent = PaymentIntent::retrieve($investment->stripe_payment_intent_id);
    
    if ($paymentIntent->status === 'succeeded') {
        $investment->update([
            'payment_status' => 'confirmed',
            'status' => 'confirmed',
            'payment_completed_at' => now(),
        ]);
        
        return ['verified' => true, 'status' => 'completed'];
    }
    
    return ['verified' => false, 'status' => $paymentIntent->status];
}
```

#### verifyCryptoPayment()
```php
public function verifyCryptoPayment(CgoInvestment $investment): array
{
    $charge = $this->coinbaseService->getCharge($investment->coinbase_charge_id);
    
    if (in_array($charge['timeline'][0]['status'], ['COMPLETED', 'RESOLVED'])) {
        $investment->update([
            'payment_status' => 'confirmed',
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
payment_status VARCHAR(255) DEFAULT 'pending' -- see Payment States above
payment_method VARCHAR(255) -- card, crypto or bank_transfer
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
The admin panel accepts only users with the admin role. Investors can view and verify only their own payments. Actions are logged with the user ID and a timestamp.

### Payment Verification
Stripe webhooks are checked by signature. Coinbase webhooks are checked with a shared secret. Bank transfers need an administrator to confirm them. Every verification attempt is logged.

### Data Protection
API keys are kept in environment variables, and sensitive data is masked in the logs. Card data is handled by Stripe Checkout, and the application stores no card data.

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

# Bank Transfer Details (config/cgo.php, bank_details)
CGO_BANK_NAME="Example Bank"
CGO_BANK_ACCOUNT_NAME="Example CGO Holdings"
CGO_BANK_ACCOUNT_NUMBER="1234567890"
CGO_BANK_ROUTING_NUMBER="021000021"
CGO_BANK_SWIFT_CODE="EXAMPUS33"
CGO_BANK_IBAN=
CGO_BANK_ADDRESS=
```

### Queue Configuration
The `VerifyCgoPayment` job does not set a queue, so it runs on the default queue:
```bash
php artisan queue:work --queue=default
```

## Troubleshooting

### Common Issues

1. **Payment not updating after verification.** Check that the queue workers and the scheduler run, that the API credentials are correct and that the webhook endpoints are reachable.

2. **Scheduled verification not running.** Check that `Cgo` is not listed in `MODULES_DISABLED`. The scheduled jobs are registered only when the module is enabled.

3. **Timeline not updating.** Check that the AJAX endpoint is reachable, that the CSRF token is sent and the browser console for errors.

### Manual Verification Process

For bank transfers that cannot be verified automatically:
1. Get the bank statement or other proof of the transaction.
2. Find the investment by its reference number, for example in `php artisan tinker`.
3. Check that the amount matches.
4. Record the transfer reference and set the payment status to `confirmed`.
5. Add detailed notes for the audit trail.

## API Endpoints

### Investor Endpoints (web session)
- `GET /cgo/payment-verification` lists the pending payments.
- `POST /cgo/payment-verification/{investment}/check` checks the status.
- `POST /cgo/payment-verification/{investment}/resend` resends the instructions.
- `GET /cgo/payment-verification/{investment}/timeline` returns the timeline.

There are no admin API endpoints for payment verification.

## Testing

### Service Tests
```bash
./vendor/bin/pest tests/Domain/Cgo/Services/PaymentVerificationServiceTest.php
```

### Manual Testing Checklist
- [ ] Automatic verification works for Stripe
- [ ] Automatic verification works for Coinbase
- [ ] Manual confirmation of a bank transfer updates the database correctly
- [ ] Investor can check their payment status
- [ ] Timeline updates reflect all events
- [ ] Email notifications are sent correctly
- [ ] `cgo:verify-payments` processes several pending payments

## Future Enhancements

1. **Admin verification page.** Bring back a payment verification page in the active admin panel, with manual confirmation and bulk actions.

2. **Live updates.** Send status changes over WebSockets and push notifications, and notify administrators in chat tools.

3. **Analytics.** Show payment success rates by method, the average verification time and an analysis of failed payments.

4. **Automation.** Read bank statements with OCR, detect fraud with machine learning and reconcile payments automatically.
