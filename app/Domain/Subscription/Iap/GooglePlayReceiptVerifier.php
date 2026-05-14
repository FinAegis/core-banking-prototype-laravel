<?php

/**
 * GooglePlayReceiptVerifier — Play Developer API `purchases.subscriptionsv2.get`.
 *
 * Mobile sends `purchaseToken` verbatim; we call the v3 Play Developer API
 * server-side to obtain the canonical subscription record. The returned
 * resource id (`linkedPurchaseToken` chain root) is stored as
 * `iap_subscriptions.play_subscription_resource_id` — the stable Google PK
 * across renewal/cancel/resubscribe (§8.7 decision).
 *
 * We deliberately avoid adding `google/apiclient` as a dependency: it brings
 * an enormous generated client tree. Instead we use `google/auth` (already in
 * vendor) for OAuth2 service-account tokens and Guzzle for the REST call.
 *
 * In local/testing with no service account configured we follow the CLAUDE.md
 * bypass pattern — derive a synthetic verified subscription from the request
 * body. This is gated on env+empty config, NEVER `return true`.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.7 / §8.7
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GooglePlayReceiptVerifier
{
    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    private const API_BASE = 'https://androidpublisher.googleapis.com/androidpublisher/v3';

    /**
     * @throws IapVerificationException
     */
    public function verify(string $purchaseToken, string $productId): GoogleVerifiedSubscription
    {
        $packageName = (string) config('subscription.iap.google.package_name', '');

        // Local/testing bypass: synthesise a verified subscription from the
        // mobile-provided fields. Gated explicitly + on missing credentials.
        if ($this->shouldBypass()) {
            return $this->buildSyntheticVerification($purchaseToken, $productId, $packageName);
        }

        if ($packageName === '') {
            throw new IapVerificationException('GooglePlayReceiptVerifier: package_name not configured.');
        }

        $token = $this->fetchAccessToken();

        $url = sprintf(
            '%s/applications/%s/purchases/subscriptionsv2/tokens/%s',
            self::API_BASE,
            rawurlencode($packageName),
            rawurlencode($purchaseToken),
        );

        try {
            $client = new Client(['timeout' => 10.0]);
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            throw new IapVerificationException(
                'GooglePlayReceiptVerifier: API request failed: ' . $e->getMessage()
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode !== 200) {
            throw new IapVerificationException(
                "Google Play API rejected purchaseToken (HTTP {$statusCode}): {$body}"
            );
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new IapVerificationException('Google Play API response is not valid JSON.');
        }

        if (! is_array($decoded)) {
            throw new IapVerificationException('Google Play API response is not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $this->buildFromApiResponse($purchaseToken, $productId, $packageName, $decoded);
    }

    /**
     * @throws IapVerificationException
     */
    private function fetchAccessToken(): string
    {
        $keyMaterial = $this->resolveServiceAccountKey();

        try {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $keyMaterial);
            /** @var array<string, mixed> $tokenArray */
            $tokenArray = $credentials->fetchAuthToken();
        } catch (Throwable $e) {
            throw new IapVerificationException(
                'GooglePlayReceiptVerifier: token fetch failed: ' . $e->getMessage()
            );
        }

        if (! isset($tokenArray['access_token']) || ! is_string($tokenArray['access_token'])) {
            throw new IapVerificationException('GooglePlayReceiptVerifier: no access_token returned.');
        }

        return (string) $tokenArray['access_token'];
    }

    /**
     * Resolve the service account credential to a value `ServiceAccountCredentials`
     * accepts: either an absolute path to a JSON file, or an array decoded from
     * raw JSON / base64-encoded JSON.
     *
     * @return string|array<string, mixed>
     *
     * @throws IapVerificationException
     */
    private function resolveServiceAccountKey(): string|array
    {
        $path = config('subscription.iap.google.service_account_path');

        if (is_string($path) && $path !== '' && is_readable($path)) {
            return $path;
        }

        $raw = config('subscription.iap.google.service_account_json');

        if (is_string($raw) && $raw !== '') {
            // Accept either raw JSON or base64-encoded JSON.
            $candidate = $raw;
            if (! str_starts_with(ltrim($raw), '{')) {
                $decoded = base64_decode($raw, true);
                if ($decoded !== false) {
                    $candidate = $decoded;
                }
            }

            try {
                /** @var mixed $parsed */
                $parsed = json_decode($candidate, true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                throw new IapVerificationException(
                    'GooglePlayReceiptVerifier: service account JSON is invalid: ' . $e->getMessage()
                );
            }

            if (! is_array($parsed)) {
                throw new IapVerificationException('GooglePlayReceiptVerifier: service account JSON is not an object.');
            }

            /** @var array<string, mixed> $parsed */
            return $parsed;
        }

        throw new IapVerificationException(
            'GooglePlayReceiptVerifier: no service account credentials configured. Set '
            . 'GOOGLE_PLAY_SERVICE_ACCOUNT_PATH or GOOGLE_PLAY_SERVICE_ACCOUNT_JSON.'
        );
    }

    /**
     * @param array<string, mixed> $apiResponse
     */
    private function buildFromApiResponse(
        string $purchaseToken,
        string $productId,
        string $packageName,
        array $apiResponse,
    ): GoogleVerifiedSubscription {
        // SubscriptionsV2 response root fields of interest:
        //   subscriptionState, latestOrderId, linkedPurchaseToken,
        //   lineItems[0].productId, lineItems[0].expiryTime,
        //   acknowledgementState, externalAccountIdentifiers.obfuscatedExternalAccountId
        $state = (string) ($apiResponse['subscriptionState'] ?? 'SUBSCRIPTION_STATE_UNSPECIFIED');

        // The v2 API does not return a single "resource id" field — the stable
        // chain root is the latestOrderId stripped of its renewal suffix
        // (".."), OR the linkedPurchaseToken's chain root. We use the
        // linkedPurchaseToken when present (it always points to the previous
        // token in the renewal chain), else fall back to the latestOrderId
        // base. The `subscriptionResourceId` field below is what we persist
        // in `iap_subscriptions.play_subscription_resource_id`.
        $resourceId = (string) ($apiResponse['linkedPurchaseToken'] ?? '');
        if ($resourceId === '') {
            $latestOrderId = (string) ($apiResponse['latestOrderId'] ?? '');
            // Strip Google's renewal suffix (..0, ..1, …) to get the root.
            $resourceId = $latestOrderId !== ''
                ? explode('..', $latestOrderId, 2)[0]
                : hash('sha256', $purchaseToken);
        }

        $lineItems = (array) ($apiResponse['lineItems'] ?? []);
        $firstLine = is_array($lineItems[0] ?? null) ? (array) $lineItems[0] : [];
        $responseProductId = (string) ($firstLine['productId'] ?? $productId);
        $expiryIso = (string) ($firstLine['expiryTime'] ?? '');

        $startTimeIso = (string) ($apiResponse['startTime'] ?? '');

        $accountIds = (array) ($apiResponse['externalAccountIdentifiers'] ?? []);
        $obfuscatedAccountId = isset($accountIds['obfuscatedExternalAccountId'])
            && is_string($accountIds['obfuscatedExternalAccountId'])
            ? (string) $accountIds['obfuscatedExternalAccountId']
            : null;

        $autoRenewing = ($state === 'SUBSCRIPTION_STATE_ACTIVE' || $state === 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD')
            && (bool) ($apiResponse['paused'] ?? false) === false;

        $ackState = (string) ($apiResponse['acknowledgementState'] ?? '');
        $isAcknowledged = $ackState === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED';

        // Price extraction — v2 may include `latestPaymentDetails.priceAmountMicros`
        // and `latestPaymentDetails.priceCurrencyCode`. Defensive defaults.
        $latestPayment = (array) ($apiResponse['latestPaymentDetails'] ?? []);
        $micros = (string) ($latestPayment['priceAmountMicros'] ?? '0');
        $amountSmallestUnit = ctype_digit(ltrim($micros, '-')) ? (int) $micros : 0;
        $amountCurrency = strtoupper((string) ($latestPayment['priceCurrencyCode'] ?? 'EUR'));

        return new GoogleVerifiedSubscription(
            purchaseToken: $purchaseToken,
            productId: $responseProductId,
            packageName: $packageName,
            subscriptionResourceId: $resourceId,
            obfuscatedAccountId: $obfuscatedAccountId,
            amountSmallestUnit: $amountSmallestUnit,
            amountDecimals: 6,
            amountCurrency: $amountCurrency,
            startTime: $startTimeIso !== '' ? Carbon::parse($startTimeIso) : null,
            expiryTime: $expiryIso !== '' ? Carbon::parse($expiryIso) : null,
            autoRenewing: $autoRenewing,
            isTrialPeriod: $state === 'SUBSCRIPTION_STATE_IN_FREE_TRIAL',
            state: $state,
            isAcknowledged: $isAcknowledged,
            linkedPurchaseToken: isset($apiResponse['linkedPurchaseToken'])
                && is_string($apiResponse['linkedPurchaseToken'])
                ? (string) $apiResponse['linkedPurchaseToken']
                : null,
        );
    }

    private function shouldBypass(): bool
    {
        if (! app()->environment('local', 'testing')) {
            return false;
        }

        $path = config('subscription.iap.google.service_account_path');
        $json = config('subscription.iap.google.service_account_json');

        return (! is_string($path) || $path === '')
            && (! is_string($json) || $json === '');
    }

    private function buildSyntheticVerification(
        string $purchaseToken,
        string $productId,
        string $packageName,
    ): GoogleVerifiedSubscription {
        Log::info('iap.google.verifier.bypassed', [
            'reason' => 'local_or_testing_no_credentials',
        ]);

        return new GoogleVerifiedSubscription(
            purchaseToken: $purchaseToken,
            productId: $productId,
            packageName: $packageName === '' ? 'app.zelta' : $packageName,
            subscriptionResourceId: 'synthetic-' . hash('sha256', $purchaseToken),
            obfuscatedAccountId: null,
            amountSmallestUnit: 4_990_000, // 4.99 EUR in micros
            amountDecimals: 6,
            amountCurrency: 'EUR',
            startTime: Carbon::now(),
            expiryTime: Carbon::now()->addMonth(),
            autoRenewing: true,
            isTrialPeriod: false,
            state: 'SUBSCRIPTION_STATE_ACTIVE',
            isAcknowledged: false,
            linkedPurchaseToken: null,
        );
    }
}
