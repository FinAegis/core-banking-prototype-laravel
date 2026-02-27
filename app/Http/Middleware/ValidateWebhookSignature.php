<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $provider  The webhook provider (stripe, coinbase, paysera, etc.)
     */
    public function handle(Request $request, Closure $next, string $provider): Response
    {
        $isValid = match ($provider) {
            'stripe'      => $this->validateStripeSignature($request),
            'coinbase'    => $this->validateCoinbaseSignature($request),
            'paysera'     => $this->validatePayseraSignature($request),
            'santander'   => $this->validateSantanderSignature($request),
            'openbanking' => $this->validateOpenBankingSignature($request),
            'marqeta'     => $this->validateMarqetaWebhook($request),
            default       => false,
        };

        if (! $isValid) {
            Log::warning('Webhook signature validation failed', [
                'provider' => $provider,
                'url'      => $request->url(),
                'ip'       => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return $next($request);
    }

    /**
     * Validate Stripe webhook signature.
     */
    private function validateStripeSignature(Request $request): bool
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (! $signature || ! $secret) {
            return false;
        }

        // Stripe uses a timestamp-based signature
        $elements = explode(',', $signature);
        $timestamp = null;
        $signatures = [];

        foreach ($elements as $element) {
            $parts = explode('=', $element, 2);
            if ($parts[0] === 't') {
                $timestamp = $parts[1];
            } elseif ($parts[0] === 'v1') {
                $signatures[] = $parts[1];
            }
        }

        if (! $timestamp || empty($signatures)) {
            return false;
        }

        // Check timestamp tolerance (5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        // Check if any of the signatures match
        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate Coinbase Commerce webhook signature.
     */
    private function validateCoinbaseSignature(Request $request): bool
    {
        $payload = $request->getContent();
        $signature = $request->header('X-CC-Webhook-Signature');
        $secret = config('services.coinbase_commerce.webhook_secret');

        if (! $signature || ! $secret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate Paysera webhook signature.
     */
    private function validatePayseraSignature(Request $request): bool
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Paysera-Signature');
        $secret = config('custodians.connectors.paysera.webhook_secret');

        if (! $signature || ! $secret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate Santander webhook signature.
     */
    private function validateSantanderSignature(Request $request): bool
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Santander-Signature');
        $timestamp = $request->header('X-Santander-Timestamp');
        $secret = config('custodians.connectors.santander.webhook_secret');

        if (! $signature || ! $timestamp || ! $secret) {
            return false;
        }

        // Check timestamp tolerance (5 minutes)
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        // Santander includes timestamp in signature
        $dataToSign = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha512', $dataToSign, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate Open Banking webhook signature.
     */
    private function validateOpenBankingSignature(Request $request): bool
    {
        // For Open Banking callbacks, we might use OAuth state parameter validation
        // or JWT token validation depending on the provider
        $state = $request->query('state');
        $expectedState = session('openbanking_state');

        if (! $state || ! $expectedState) {
            return false;
        }

        // Clear the state after validation to prevent replay
        session()->forget('openbanking_state');

        return hash_equals($expectedState, $state);
    }

    /**
     * Validate Marqeta card issuer webhook via Basic Auth + optional HMAC signature.
     *
     * Marqeta sends webhooks with HTTP Basic Auth credentials configured in the
     * Marqeta dashboard. Optionally, it also includes an HMAC signature header.
     */
    private function validateMarqetaWebhook(Request $request): bool
    {
        $expectedUsername = config('cardissuance.issuers.marqeta.webhook_username');
        $expectedPassword = config('cardissuance.issuers.marqeta.webhook_password');

        // Basic Auth is required when credentials are configured
        if ($expectedUsername !== null && $expectedUsername !== '') {
            $providedUsername = $request->getUser() ?? '';
            $providedPassword = $request->getPassword() ?? '';

            if (
                ! hash_equals((string) $expectedUsername, $providedUsername)
                || ! hash_equals((string) $expectedPassword, $providedPassword)
            ) {
                return false;
            }
        }

        // HMAC signature check (optional but recommended for additional security)
        $secret = config('cardissuance.issuers.marqeta.webhook_secret');
        $signature = $request->header('X-Marqeta-Webhook-Signature');

        if ($secret !== null && $secret !== '' && $signature !== null) {
            $expectedSignature = hash_hmac('sha256', $request->getContent(), (string) $secret);

            if (! hash_equals($expectedSignature, $signature)) {
                return false;
            }
        }

        return true;
    }
}
