<?php

namespace App\Domain\Cgo\Services;

use App\Domain\Cgo\Mail\CgoInvestmentConfirmed;
use App\Domain\Cgo\Models\CgoInvestment;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentVerificationService
{
    protected StripePaymentService $stripeService;

    protected CoinbaseCommerceService $coinbaseService;

    public function __construct(
        StripePaymentService $stripeService,
        CoinbaseCommerceService $coinbaseService
    ) {
        $this->stripeService = $stripeService;
        $this->coinbaseService = $coinbaseService;
    }

    /**
     * Verify a payment for an investment.
     */
    public function verifyPayment(CgoInvestment $investment): bool
    {
        Log::info(
            'Verifying payment for investment',
            [
            'investment_id'  => $investment->id,
            'payment_method' => $investment->payment_method,
            'status'         => $investment->status,
            ]
        );

        // Don't verify if already confirmed
        if ($investment->status === 'confirmed') {
            return true;
        }

        $verified = false;

        switch ($investment->payment_method) {
            case 'card':
                $verified = $this->verifyStripePayment($investment);
                break;

            case 'crypto':
                $verified = $this->verifyCryptoPayment($investment);
                break;

            case 'bank_transfer':
                $verified = $this->verifyBankTransfer($investment);
                break;

            default:
                Log::warning(
                    'Unknown payment method for verification',
                    [
                    'investment_id'  => $investment->id,
                    'payment_method' => $investment->payment_method,
                    ]
                );
        }

        if ($verified) {
            $this->confirmInvestment($investment);
        }

        return $verified;
    }

    /**
     * Verify a Stripe payment.
     */
    protected function verifyStripePayment(CgoInvestment $investment): bool
    {
        if (! $investment->stripe_session_id) {
            return false;
        }

        try {
            return $this->stripeService->verifyPayment($investment);
        } catch (Exception $e) {
            Log::error(
                'Stripe payment verification failed',
                [
                'investment_id' => $investment->id,
                'error'         => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Verify a crypto payment via Coinbase Commerce.
     */
    protected function verifyCryptoPayment(CgoInvestment $investment): bool
    {
        if (! $investment->coinbase_charge_id) {
            // Manual crypto payment - check if transaction hash is provided
            return ! empty($investment->crypto_transaction_hash);
        }

        try {
            $charge = $this->coinbaseService->getCharge($investment->coinbase_charge_id);

            // Check if charge is confirmed
            if (isset($charge['timeline'])) {
                foreach ($charge['timeline'] as $event) {
                    if ($event['status'] === 'COMPLETED') {
                        return true;
                    }
                }
            }

            return false;
        } catch (Exception $e) {
            Log::error(
                'Coinbase payment verification failed',
                [
                'investment_id' => $investment->id,
                'error'         => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Verify a bank transfer payment.
     */
    protected function verifyBankTransfer(CgoInvestment $investment): bool
    {
        // For bank transfers, we rely on manual verification
        // Check if admin has marked it as verified
        return ! empty($investment->bank_transfer_reference) &&
               $investment->payment_status === 'confirmed';
    }

    /**
     * Confirm an investment after successful payment verification.
     */
    protected function confirmInvestment(CgoInvestment $investment): void
    {
        $investment->update(
            [
            'status'               => 'confirmed',
            'payment_status'       => 'confirmed',
            'payment_completed_at' => now(),
            ]
        );

        // Generate certificate number if not already set
        if (! $investment->certificate_number) {
            $investment->update(
                [
                'certificate_number'    => $investment->generateCertificateNumber(),
                'certificate_issued_at' => now(),
                ]
            );
        }

        // Update pricing round stats
        if ($investment->round) {
            $investment->round->increment('shares_sold', $investment->shares_purchased);
            $investment->round->increment('total_raised', $investment->amount);
        }

        // Send confirmation email
        try {
            Mail::to($investment->email)->send(new CgoInvestmentConfirmed($investment));
        } catch (Exception $e) {
            Log::error(
                'Failed to send investment confirmation email',
                [
                'investment_id' => $investment->id,
                'error'         => $e->getMessage(),
                ]
            );
        }

        Log::info(
            'Investment confirmed',
            [
            'investment_id'      => $investment->id,
            'certificate_number' => $investment->certificate_number,
            ]
        );
    }

    /**
     * Verify all pending payments.
     */
    public function verifyPendingPayments(): int
    {
        $count = 0;

        $pendingInvestments = CgoInvestment::where('status', 'pending')
            ->where('created_at', '>', now()->subDays(7)) // Only check recent investments
            ->get();

        foreach ($pendingInvestments as $investment) {
            if ($this->verifyPayment($investment)) {
                $count++;
            }
        }

        Log::info(
            'Batch payment verification completed',
            [
            'total_checked' => $pendingInvestments->count(),
            'confirmed'     => $count,
            ]
        );

        return $count;
    }

    /**
     * Mark a payment as failed.
     */
    public function markPaymentFailed(CgoInvestment $investment, string $reason): void
    {
        $investment->update(
            [
            'payment_status'         => 'failed',
            'payment_failed_at'      => now(),
            'payment_failure_reason' => $reason,
            ]
        );

        Log::warning(
            'Investment payment marked as failed',
            [
            'investment_id' => $investment->id,
            'reason'        => $reason,
            ]
        );
    }

    /**
     * Check if payment is expired.
     */
    public function isPaymentExpired(CgoInvestment $investment): bool
    {
        // Different expiration times for different payment methods
        $expirationHours = match ($investment->payment_method) {
            'card'          => 1,      // 1 hour for card payments
            'crypto'        => 24,   // 24 hours for crypto
            'bank_transfer' => 72, // 3 days for bank transfers
            default         => 24,
        };

        return $investment->created_at->addHours($expirationHours)->isPast();
    }

    /**
     * Handle expired payments.
     */
    public function handleExpiredPayments(): int
    {
        $count = 0;

        $pendingInvestments = CgoInvestment::where('status', 'pending')
            ->whereNull('payment_completed_at')
            ->get();

        foreach ($pendingInvestments as $investment) {
            if ($this->isPaymentExpired($investment)) {
                $investment->update(
                    [
                    'status'                 => 'cancelled',
                    'cancelled_at'           => now(),
                    'payment_status'         => 'expired',
                    'payment_failure_reason' => 'Payment window expired',
                    ]
                );
                $count++;
            }
        }

        if ($count > 0) {
            Log::info('Expired payments handled', ['count' => $count]);
        }

        return $count;
    }
}
