<?php

declare(strict_types=1);

namespace App\Domain\MachinePay\Services\Rails;

use App\Domain\MachinePay\Contracts\PaymentRailInterface;
use App\Domain\MachinePay\DataObjects\MppCredential;
use App\Domain\MachinePay\DataObjects\MppReceipt;
use App\Domain\MachinePay\Enums\PaymentRail;
use App\Domain\MachinePay\Exceptions\MppException;
use App\Domain\MachinePay\Exceptions\MppSettlementException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stripe SPT (Payment Token) rail adapter.
 *
 * Processes payments using single-use Stripe Payment Tokens (spt_).
 * In production, creates a PaymentIntent with confirm:true and
 * transfer_data for Stripe Connect (funds go directly to service provider).
 * Demo mode returns simulated responses.
 */
class StripeRailAdapter implements PaymentRailInterface
{
    public function processPayment(MppCredential $credential, array $context = []): MppReceipt
    {
        $spt = $credential->proofOfPayment['spt'] ?? null;

        if (! is_string($spt) || ! str_starts_with($spt, 'spt_')) {
            throw MppSettlementException::verificationFailed('Invalid Stripe Payment Token format.');
        }

        if (! app()->environment('production')) {
            return $this->demoReceipt($credential, $context);
        }

        return $this->processProductionPayment($spt, $credential, $context);
    }

    public function verifyPayment(MppCredential $credential): bool
    {
        $spt = $credential->proofOfPayment['spt'] ?? null;

        return is_string($spt) && str_starts_with($spt, 'spt_');
    }

    public function refund(string $settlementReference, int $amountCents): bool
    {
        if (! app()->environment('production')) {
            return true;
        }

        $apiKey = (string) config('machinepay.rails.stripe.api_key_id', '');
        if ($apiKey === '') {
            throw new MppException('Stripe API key not configured.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.stripe.com/v1/refunds', [
                    'payment_intent' => $settlementReference,
                    'amount'         => $amountCents,
                ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::error('Stripe: Refund failed', [
                'settlement_ref' => $settlementReference,
                'error'          => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getRailIdentifier(): PaymentRail
    {
        return PaymentRail::STRIPE_SPT;
    }

    public function isAvailable(): bool
    {
        return config('machinepay.rails.stripe.api_key_id') !== null
            || ! app()->environment('production');
    }

    /**
     * Process a real Stripe payment via PaymentIntent + Connect.
     *
     * @param array<string, mixed> $context
     */
    private function processProductionPayment(string $spt, MppCredential $credential, array $context): MppReceipt
    {
        $apiKey = (string) config('machinepay.rails.stripe.api_key_id', '');
        if ($apiKey === '') {
            throw new MppException('Stripe API key not configured for production.');
        }

        $amountCents = (int) ($context['amount_cents'] ?? 0);
        $currency = strtolower((string) ($context['currency'] ?? 'usd'));
        $connectedAccountId = $context['stripe_connected_account'] ?? null;

        $params = [
            'amount'                 => $amountCents,
            'currency'               => $currency,
            'payment_method'         => $spt,
            'confirm'                => 'true',
            'payment_method_types[]' => 'card',
        ];

        // Stripe Connect: transfer funds directly to service provider
        if (is_string($connectedAccountId) && $connectedAccountId !== '') {
            $platformFee = (int) ceil($amountCents * 0.02); // 2% platform fee
            $params['transfer_data'] = [
                'destination' => $connectedAccountId,
            ];
            $params['application_fee_amount'] = $platformFee;
        }

        try {
            $response = Http::asForm()
                ->withToken($apiKey)
                ->timeout(30)
                ->post('https://api.stripe.com/v1/payment_intents', $params);

            if (! $response->successful()) {
                Log::error('Stripe: PaymentIntent creation failed', [
                    'status' => $response->status(),
                    'error'  => $response->json('error.message', 'unknown'),
                ]);

                return new MppReceipt(
                    receiptId: 'rcpt_stripe_' . Str::random(16),
                    challengeId: $credential->challengeId,
                    rail: PaymentRail::STRIPE_SPT->value,
                    settlementReference: '',
                    settledAt: gmdate('c'),
                    amountCents: $amountCents,
                    currency: strtoupper($currency),
                    status: 'error',
                );
            }

            $pi = $response->json();
            $piId = (string) ($pi['id'] ?? '');

            Log::info('Stripe: Payment settled', [
                'payment_intent' => $piId,
                'amount'         => $amountCents,
                'connected_acct' => $connectedAccountId,
            ]);

            return new MppReceipt(
                receiptId: 'rcpt_stripe_' . Str::random(16),
                challengeId: $credential->challengeId,
                rail: PaymentRail::STRIPE_SPT->value,
                settlementReference: $piId,
                settledAt: gmdate('c'),
                amountCents: $amountCents,
                currency: strtoupper($currency),
            );
        } catch (Throwable $e) {
            Log::error('Stripe: Payment processing exception', ['error' => $e->getMessage()]);

            throw MppSettlementException::settlementFailed(
                'stripe',
                $e->getMessage(),
            );
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function demoReceipt(MppCredential $credential, array $context): MppReceipt
    {
        return new MppReceipt(
            receiptId: 'rcpt_stripe_' . Str::random(16),
            challengeId: $credential->challengeId,
            rail: PaymentRail::STRIPE_SPT->value,
            settlementReference: 'pi_demo_' . Str::random(20),
            settledAt: gmdate('c'),
            amountCents: (int) ($context['amount_cents'] ?? 0),
            currency: (string) ($context['currency'] ?? 'USD'),
        );
    }
}
