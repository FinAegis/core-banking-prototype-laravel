<?php

namespace App\Services\Cgo;

use App\Models\CgoInvestment;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\PaymentActionRequired;
use Laravel\Cashier\Exceptions\PaymentFailure;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripePaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Create a Stripe Checkout session for CGO investment
     */
    public function createCheckoutSession(CgoInvestment $investment): Session
    {
        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower(config('cashier.currency')),
                        'product_data' => [
                            'name' => 'CGO Investment - ' . $investment->package,
                            'description' => 'Investment in FinAegis Continuous Growth Offering',
                            'metadata' => [
                                'investment_id' => $investment->id,
                                'investment_uuid' => $investment->uuid,
                            ],
                        ],
                        'unit_amount' => $investment->amount * 100, // Amount in cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('cgo.payment.success', ['investment' => $investment->uuid]),
                'cancel_url' => route('cgo.payment.cancel', ['investment' => $investment->uuid]),
                'metadata' => [
                    'investment_id' => $investment->id,
                    'investment_uuid' => $investment->uuid,
                    'user_id' => $investment->user_id,
                    'package' => $investment->package,
                ],
                'customer_email' => $investment->user->email,
                'client_reference_id' => $investment->uuid,
                'payment_intent_data' => [
                    'description' => 'CGO Investment #' . $investment->uuid,
                    'metadata' => [
                        'investment_id' => $investment->id,
                        'investment_uuid' => $investment->uuid,
                    ],
                ],
            ]);

            // Store session ID for later verification
            $investment->update([
                'stripe_session_id' => $session->id,
                'payment_status' => 'checkout_created',
            ]);

            Log::info('Stripe checkout session created', [
                'investment_id' => $investment->id,
                'session_id' => $session->id,
            ]);

            return $session;
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error creating checkout session', [
                'investment_id' => $investment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify payment status from Stripe
     */
    public function verifyPayment(CgoInvestment $investment): bool
    {
        if (!$investment->stripe_session_id) {
            return false;
        }

        try {
            $session = Session::retrieve($investment->stripe_session_id);
            
            if ($session->payment_status === 'paid') {
                $investment->update([
                    'payment_status' => 'completed',
                    'payment_completed_at' => now(),
                    'stripe_payment_intent_id' => $session->payment_intent,
                ]);

                Log::info('Stripe payment verified as completed', [
                    'investment_id' => $investment->id,
                    'payment_intent' => $session->payment_intent,
                ]);

                return true;
            }

            return false;
        } catch (ApiErrorException $e) {
            Log::error('Error verifying Stripe payment', [
                'investment_id' => $investment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle webhook events from Stripe
     */
    public function handleWebhook(array $payload): void
    {
        $event = $payload['type'] ?? null;
        $data = $payload['data']['object'] ?? [];

        switch ($event) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($data);
                break;
            
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($data);
                break;
            
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($data);
                break;
        }
    }

    /**
     * Handle successful checkout completion
     */
    protected function handleCheckoutCompleted(array $session): void
    {
        $investmentUuid = $session['client_reference_id'] ?? null;
        
        if (!$investmentUuid) {
            Log::warning('Checkout completed without investment reference', ['session' => $session]);
            return;
        }

        $investment = CgoInvestment::where('uuid', $investmentUuid)->first();
        
        if (!$investment) {
            Log::error('Investment not found for completed checkout', ['uuid' => $investmentUuid]);
            return;
        }

        $investment->update([
            'payment_status' => 'completed',
            'payment_completed_at' => now(),
            'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
        ]);

        Log::info('CGO investment payment completed via webhook', [
            'investment_id' => $investment->id,
            'payment_intent' => $session['payment_intent'] ?? null,
        ]);
    }

    /**
     * Handle successful payment intent
     */
    protected function handlePaymentSucceeded(array $paymentIntent): void
    {
        $investmentId = $paymentIntent['metadata']['investment_id'] ?? null;
        
        if (!$investmentId) {
            return;
        }

        $investment = CgoInvestment::find($investmentId);
        
        if ($investment && $investment->payment_status !== 'completed') {
            $investment->update([
                'payment_status' => 'completed',
                'payment_completed_at' => now(),
            ]);

            Log::info('CGO investment payment succeeded', [
                'investment_id' => $investment->id,
                'payment_intent' => $paymentIntent['id'],
            ]);
        }
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentFailed(array $paymentIntent): void
    {
        $investmentId = $paymentIntent['metadata']['investment_id'] ?? null;
        
        if (!$investmentId) {
            return;
        }

        $investment = CgoInvestment::find($investmentId);
        
        if ($investment) {
            $investment->update([
                'payment_status' => 'failed',
                'payment_failed_at' => now(),
                'payment_failure_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Unknown error',
            ]);

            Log::warning('CGO investment payment failed', [
                'investment_id' => $investment->id,
                'payment_intent' => $paymentIntent['id'] ?? 'unknown',
                'error' => $paymentIntent['last_payment_error']['message'] ?? 'Unknown',
            ]);
        }
    }

    /**
     * Create a payment intent for direct charge
     */
    public function createPaymentIntent(CgoInvestment $investment): array
    {
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $investment->amount * 100, // Amount in cents
                'currency' => strtolower(config('cashier.currency')),
                'description' => 'CGO Investment #' . $investment->uuid,
                'metadata' => [
                    'investment_id' => $investment->id,
                    'investment_uuid' => $investment->uuid,
                    'user_id' => $investment->user_id,
                ],
                'receipt_email' => $investment->user->email,
            ]);

            $investment->update([
                'stripe_payment_intent_id' => $intent->id,
                'payment_status' => 'intent_created',
            ]);

            return [
                'client_secret' => $intent->client_secret,
                'intent_id' => $intent->id,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Error creating payment intent', [
                'investment_id' => $investment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}