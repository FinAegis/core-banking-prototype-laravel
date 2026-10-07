<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

            // Marqeta authenticates with HTTP Basic credentials: answer 401.
            if ($provider === 'marqeta') {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Replay protection for providers that don't embed timestamps in their signatures
        if (in_array($provider, ['coinbase', 'paysera'], true)) {
            $replayCheck = $this->checkReplayProtection($request);
            if ($replayCheck !== null) {
                return $replayCheck;
            }
        }

        return $next($request);
    }

    /**
     * Check for duplicate webhook delivery (replay attack protection).
     *
     * Returns a 409 response if the delivery has already been seen, or null to proceed.
     */
    private function checkReplayProtection(Request $request): ?Response
    {
        $deliveryId = $request->header('X-Webhook-Delivery-Id')
            ?? $request->header('X-Request-Id')
            ?? hash('sha256', $request->getContent());

        $cacheKey = "webhook_seen:{$deliveryId}";

        if (Cache::has($cacheKey)) {
            Log::warning('Duplicate webhook delivery rejected', [
                'delivery_id' => $deliveryId,
                'url'         => $request->url(),
                'ip'          => $request->ip(),
            ]);

            return response()->json(['error' => 'Duplicate webhook delivery'], 409);
        }

        Cache::put($cacheKey, true, 86400); // 24-hour dedup window

        return null;
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
     *
     * Uses a Cache-based nonce rather than session state, so the state token
     * survives across processes and cannot be fixed by a session-fixation attack.
     */
    private function validateOpenBankingSignature(Request $request): bool
    {
        $state = (string) ($request->query('state') ?? '');

        if ($state === '' || strlen($state) < 32) {
            return false;
        }

        // Cache::pull atomically reads and deletes — single-use nonce
        $stateData = Cache::pull("ob_state:{$state}");

        return $stateData !== null;
    }

    /**
     * Validate a Marqeta card issuer webhook (JIT authorization, settlement,
     * transaction events).
     *
     * - Basic Auth (MARQETA_WEBHOOK_USERNAME + MARQETA_WEBHOOK_PASSWORD) is
     *   enforced when configured; configuring only one of the two is treated
     *   as a misconfiguration and rejected.
     * - HMAC (MARQETA_WEBHOOK_SECRET, or MARQETA_HMAC_SECRET): when a secret
     *   is configured the X-Marqeta-Webhook-Signature header is required and
     *   must be the hex HMAC-SHA256 of the raw body.
     * - With neither configured the request is accepted only in the local
     *   and testing environments; everywhere else it is rejected.
     */
    private function validateMarqetaWebhook(Request $request): bool
    {
        $expectedUsername = (string) (config('cardissuance.issuers.marqeta.webhook_username') ?? '');
        $expectedPassword = (string) (config('cardissuance.issuers.marqeta.webhook_password') ?? '');
        $secret = (string) (config('cardissuance.issuers.marqeta.webhook_secret') ?: config('cardissuance.issuers.marqeta.hmac_secret') ?: '');

        $basicAuthConfigured = $expectedUsername !== '' && $expectedPassword !== '';
        $hmacConfigured = $secret !== '';

        if (! $basicAuthConfigured && ! $hmacConfigured) {
            if ($expectedUsername !== '' || $expectedPassword !== '') {
                return false;
            }

            return app()->environment(['local', 'testing']);
        }

        if (($expectedUsername !== '') !== ($expectedPassword !== '')) {
            return false;
        }

        if ($basicAuthConfigured) {
            $providedUsername = (string) ($request->getUser() ?? '');
            $providedPassword = (string) ($request->getPassword() ?? '');

            if (
                ! hash_equals($expectedUsername, $providedUsername)
                || ! hash_equals($expectedPassword, $providedPassword)
            ) {
                return false;
            }
        }

        if ($hmacConfigured) {
            $signature = $request->header('X-Marqeta-Webhook-Signature');

            if (! is_string($signature) || $signature === '') {
                return false;
            }

            $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

            if (! hash_equals($expectedSignature, strtolower(trim($signature)))) {
                return false;
            }
        }

        return true;
    }
}
