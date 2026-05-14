<?php

/**
 * AppleReceiptVerifier — StoreKit 2 JWS local verification.
 *
 * Mobile-dev confirmed: expo-iap 4.2.3 + Expo SDK 54 + iOS 15.1+ → StoreKit 2
 * is always used. Mobile sends a JWS-signed transaction string; we decode the
 * payload (and the optional x5c cert chain) locally rather than calling the
 * deprecated `verifyReceipt` endpoint.
 *
 * For local/testing with no Apple cert chain configured we follow the
 * CLAUDE.md webhook-auth-bypass pattern: gated explicitly on env+empty key,
 * never `return true` unconditionally.
 *
 * NB: A full production verifier needs the Apple WWDR G6 intermediate cert +
 * Apple Root CA G3 to chain-validate the x5c. For slice 2 we ship the
 * verifier in a pluggable form (extracts the payload + checks the bundle id +
 * basic shape checks) so it can be hardened later. The deprecated
 * `POST https://buy.itunes.apple.com/verifyReceipt` endpoint is NEVER called.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.7 / §8.1
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AppleReceiptVerifier
{
    /**
     * Decode a StoreKit 2 JWS and return a typed transaction object.
     *
     * @throws IapVerificationException
     */
    public function verify(string $jws, string $expectedOriginalTransactionId): AppleVerifiedTransaction
    {
        $payload = $this->decodeJwsPayload($jws);

        $bundleId = (string) config('subscription.iap.apple.bundle_id', '');
        $receivedBundleId = (string) ($payload['bundleId'] ?? '');

        // In local/testing with an empty configured bundle id, skip the bundle
        // check (matches the CLAUDE.md bypass pattern: gated explicitly + empty
        // config — never `return true`).
        if ($bundleId !== '' && $receivedBundleId !== '' && $receivedBundleId !== $bundleId) {
            throw new IapVerificationException(
                "Apple JWS bundleId mismatch: expected '{$bundleId}', got '{$receivedBundleId}'."
            );
        }

        $originalTransactionId = (string) ($payload['originalTransactionId'] ?? '');
        if ($originalTransactionId === '') {
            throw new IapVerificationException('Apple JWS missing originalTransactionId.');
        }

        // Mobile sends the StoreKit 2 originalTransactionId in the request body;
        // it MUST match what's inside the JWS. Drift indicates tampering or a
        // mobile bug.
        if ($expectedOriginalTransactionId !== '' && $originalTransactionId !== $expectedOriginalTransactionId) {
            throw new IapVerificationException(
                'Apple JWS originalTransactionId does not match request body.'
            );
        }

        $transactionId = (string) ($payload['transactionId'] ?? $originalTransactionId);
        $productId = (string) ($payload['productId'] ?? '');
        if ($productId === '') {
            throw new IapVerificationException('Apple JWS missing productId.');
        }

        $appAccountToken = isset($payload['appAccountToken']) && is_string($payload['appAccountToken'])
            ? (string) $payload['appAccountToken']
            : null;

        // Apple's `price` field for subscriptions is the storefront currency
        // amount in minor units (integer micros for some plans, plain integer
        // for storefronts with implicit 2 decimals — Apple is inconsistent).
        // We normalise to minor units (decimals=2) when the value looks like
        // a plain integer; if Apple emits `priceAmountMicros` we use 6 decimals.
        [$amountSmallestUnit, $amountDecimals] = $this->extractPrice($payload);

        $amountCurrency = strtoupper((string) ($payload['currency'] ?? 'EUR'));

        $purchaseDate = $this->parseAppleEpochMillis($payload['purchaseDate'] ?? null);
        $originalPurchaseDate = $this->parseAppleEpochMillis($payload['originalPurchaseDate'] ?? null);
        $expiresDate = $this->parseAppleEpochMillis($payload['expiresDate'] ?? null);

        // Apple sets `inAppOwnershipType` = `FAMILY_SHARED` for sharing-derived
        // receipts (we reject these at the verify endpoint with ERR_SUB_008).
        $ownershipType = (string) ($payload['inAppOwnershipType'] ?? 'PURCHASED');
        $isFamilyShared = $ownershipType === 'FAMILY_SHARED';

        $environment = (string) ($payload['environment'] ?? 'Production');
        $isSandbox = strtolower($environment) === 'sandbox';

        return new AppleVerifiedTransaction(
            originalTransactionId: $originalTransactionId,
            transactionId: $transactionId,
            productId: $productId,
            bundleId: $receivedBundleId,
            appAccountToken: $appAccountToken,
            amountSmallestUnit: $amountSmallestUnit,
            amountDecimals: $amountDecimals,
            amountCurrency: $amountCurrency,
            purchaseDate: $purchaseDate,
            originalPurchaseDate: $originalPurchaseDate,
            expiresDate: $expiresDate,
            isInIntroOfferPeriod: (bool) ($payload['offerType'] ?? false),
            isTrialPeriod: (string) ($payload['type'] ?? '') === 'Auto-Renewable Subscription'
                && isset($payload['offerType']),
            isFamilyShared: $isFamilyShared,
            isSandbox: $isSandbox,
            rawJws: $jws,
        );
    }

    /**
     * Decode an Apple Server Notifications V2 envelope (the outer
     * `signedPayload` JWS). Returns the decoded payload array.
     *
     * @return array<string, mixed>
     *
     * @throws IapVerificationException
     */
    public function decodeNotificationPayload(string $signedPayload): array
    {
        return $this->decodeJwsPayload($signedPayload);
    }

    /**
     * Decode the inner `signedTransactionInfo` JWS embedded in a notification.
     *
     * @return array<string, mixed>
     *
     * @throws IapVerificationException
     */
    public function decodeSignedTransactionInfo(string $signedTransactionInfo): array
    {
        return $this->decodeJwsPayload($signedTransactionInfo);
    }

    /**
     * Decode a JWS payload — strict in production, JSON-decoded in local/testing.
     *
     * @return array<string, mixed>
     *
     * @throws IapVerificationException
     */
    private function decodeJwsPayload(string $jws): array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            throw new IapVerificationException('Apple JWS malformed: expected 3 segments.');
        }

        $payloadJson = $this->base64UrlDecode($parts[1]);
        if ($payloadJson === false) {
            throw new IapVerificationException('Apple JWS payload could not be base64url-decoded.');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payloadJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new IapVerificationException('Apple JWS payload is not valid JSON: ' . $e->getMessage());
        }

        if (! is_array($decoded)) {
            throw new IapVerificationException('Apple JWS payload is not a JSON object.');
        }

        // Signature verification path: in production we MUST validate the x5c
        // certificate chain against Apple Root CA G3. In local/testing we
        // deliberately skip (CLAUDE.md bypass pattern). In every other
        // environment, the verifier must FAIL CLOSED — accepting an unverified
        // JWS in prod would allow any authenticated user to forge an
        // originalTransactionId / productId / expiresDate and grant themselves
        // a Pro subscription. The real chain-validation implementation is a
        // tracked follow-up; until it lands, the explicit operator escape hatch
        // is APPLE_JWS_VERIFICATION_BYPASS=true (intended for staging only).
        if (app()->environment('local', 'testing')) {
            /** @var array<string, mixed> $decoded */
            return $decoded;
        }

        $bypass = (bool) config('subscription.iap.apple_jws_verification_bypass', false);
        if (! $bypass) {
            $this->verifyJwsChain($parts[0], $parts[1], $parts[2]);
        } else {
            // Bypass flag is set — operator has explicitly accepted the risk
            // (typically for a staging environment without provisioned certs).
            // We log every bypass so it's visible in audit logs.
            Log::warning('iap.apple.jws.signature_verify_bypassed', [
                'environment' => app()->environment(),
                'reason'      => 'APPLE_JWS_VERIFICATION_BYPASS=true',
            ]);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Production JWS-chain verification. Implementations should:
     *   1. Base64url-decode the header to read the x5c array
     *   2. Chain-validate x5c[2] → Apple Root CA G3 (pinned fingerprint)
     *   3. Verify the ES256 signature against the leaf public key (x5c[0])
     *
     * Until the real implementation lands, this throws so the verifier fails
     * closed in production. The explicit operator opt-out is
     * APPLE_JWS_VERIFICATION_BYPASS=true (see decodeJwsPayload above), intended
     * for staging environments without provisioned certs — never production.
     */
    private function verifyJwsChain(string $headerB64, string $payloadB64, string $signatureB64): void
    {
        Log::error('iap.apple.jws.chain_validation.not_implemented', [
            'header_bytes'    => strlen($headerB64),
            'payload_bytes'   => strlen($payloadB64),
            'signature_bytes' => strlen($signatureB64),
        ]);

        throw new IapVerificationException(
            'Apple JWS chain validation is not yet implemented. '
            . 'Set APPLE_JWS_VERIFICATION_BYPASS=true to explicitly accept '
            . 'unverified JWS payloads (non-production only).',
        );
    }

    /**
     * Extract the price from an Apple JWS payload and normalise to
     * (smallest_unit_integer, decimals).
     *
     * Apple `price` is a number-or-string; `priceAmountMicros` (when present)
     * is integer-string with 6 decimals.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{0: int, 1: int}
     */
    private function extractPrice(array $payload): array
    {
        if (isset($payload['priceAmountMicros'])) {
            $raw = (string) $payload['priceAmountMicros'];

            return [(int) $raw, 6];
        }

        if (isset($payload['price']) && is_numeric($payload['price'])) {
            // Apple's `price` is documented to be the storefront amount in
            // minor units (decimals=2 for most storefronts). Avoid float casts:
            // operate on the string form and treat it as a minor-unit integer.
            $raw = (string) $payload['price'];
            // Strip any decimal portion (Apple emits ints in newer payloads;
            // older sandbox payloads emit decimals).
            if (str_contains($raw, '.')) {
                $raw = bcmul($raw, '100', 0);
            }

            return [(int) $raw, 2];
        }

        return [0, 2];
    }

    private function parseAppleEpochMillis(mixed $value): ?Carbon
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        $millis = (int) $value;

        return Carbon::createFromTimestampMs($millis);
    }

    private function base64UrlDecode(string $input): string|false
    {
        $padded = strtr($input, '-_', '+/');
        $padLen = 4 - (strlen($padded) % 4);
        if ($padLen < 4) {
            $padded .= str_repeat('=', $padLen);
        }

        return base64_decode($padded, true);
    }
}
