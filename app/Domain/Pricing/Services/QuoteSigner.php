<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use RuntimeException;

/**
 * QuoteSigner — HMAC-SHA256 tamper-evidence for price_quotes rows.
 *
 * Signs and verifies quote rows using a canonical pipe-delimited form keyed
 * by PRICING_QUOTE_PEPPER env var. This provides a third layer of protection
 * (on top of userOpHash + DB::lockForUpdate) against a direct DB write that
 * bypasses the application layer and fabricates a valid PriceQuote row.
 *
 * Canonical form:
 *   "{id}|{user_id}|{kind}|{expires_at_unix}|{sha256_of_response_payload}"
 *
 * Deterministic: order-fixed, pipe-delimited, no JSON serialisation ambiguity.
 *
 * Security note: uses PRICING_QUOTE_PEPPER, NOT config('app.key'). Using the
 * Laravel encryption key for HMAC creates key reuse. Generate the pepper with
 * `openssl rand -hex 32` and store as PRICING_QUOTE_PEPPER in .env.
 *
 * ROTATION WARNING: rotating the pepper invalidates all live price_quotes rows
 * because their stored signatures will no longer verify. Coordinate rotation
 * with a pricing:purge-quotes --force-all run or a full quote-TTL drain window.
 *
 * Mirrors TrialFingerprintService's use of TRIAL_FINGERPRINT_PEPPER (slice 1).
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.6
 */
final class QuoteSigner
{
    /**
     * Sign a quote row with HMAC-SHA256 over the canonical form.
     *
     * @param  string  $responsePayloadHash  SHA256 hex of the serialised response JSON
     * @return string  64-char lowercase hex HMAC-SHA256 output
     */
    public function sign(
        string $id,
        string $userId,
        string $kind,
        int $expiresAtUnix,
        string $responsePayloadHash,
    ): string {
        $pepper = $this->pepper();
        $canonical = $this->canonical($id, $userId, $kind, $expiresAtUnix, $responsePayloadHash);

        return hash_hmac('sha256', $canonical, $pepper);
    }

    /**
     * Verify a stored signature using constant-time comparison.
     *
     * @param  string  $storedSignature  64-char hex string from price_quotes.signature
     */
    public function verify(
        string $id,
        string $userId,
        string $kind,
        int $expiresAtUnix,
        string $responsePayloadHash,
        string $storedSignature,
    ): bool {
        $expected = $this->sign($id, $userId, $kind, $expiresAtUnix, $responsePayloadHash);

        // hash_equals() is constant-time — prevents timing-attack signature oracle.
        return hash_equals($expected, $storedSignature);
    }

    /**
     * Compute SHA256 over a serialised JSON payload string.
     *
     * Used to produce the responsePayloadHash argument to sign() / verify()
     * from the raw JSON string stored in price_quotes.response_payload.
     */
    public function payloadHash(string $jsonPayload): string
    {
        return hash('sha256', $jsonPayload);
    }

    // ──────────────────────────────────────────────────────────────────────

    private function canonical(
        string $id,
        string $userId,
        string $kind,
        int $expiresAtUnix,
        string $responsePayloadHash,
    ): string {
        return implode('|', [$id, $userId, $kind, (string) $expiresAtUnix, $responsePayloadHash]);
    }

    private function pepper(): string
    {
        /** @var mixed $pepper */
        $pepper = config('services.pricing.quote_pepper');

        if (! is_string($pepper) || $pepper === '') {
            throw new RuntimeException(
                'PRICING_QUOTE_PEPPER is not configured — refusing to sign with an empty key. '
                . 'Set PRICING_QUOTE_PEPPER=<openssl rand -hex 32> in .env.'
            );
        }

        return $pepper;
    }
}
