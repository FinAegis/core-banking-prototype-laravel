<?php

declare(strict_types=1);

namespace App\Infrastructure\Bridge;

/**
 * Verifies a Bridge.xyz webhook signature.
 *
 * Bridge's current "Bridge by Stripe" platform signs webhooks ASYMMETRICALLY:
 * header `X-Webhook-Signature: t=<unix_ms>,v0=<base64(RSA-SHA256 signature)>`,
 * where the signature is computed over `<timestamp>.<raw_body>` and verified
 * against a per-endpoint RSA public key issued by Bridge (there is no shared
 * secret in this model — the dashboard only exposes a public key).
 *
 * For backward compatibility (and the test suite) the verifier also accepts a
 * legacy HMAC scheme: `t=<unix_sec>,v1=<hex_hmac_sha256>` keyed by a shared
 * secret. The scheme is auto-detected from the signature element present (v0
 * vs v1) AND the credential configured (public key vs secret). In production
 * we configure only the public key, so the HMAC path is inert there.
 *
 * Anti-replay: rejects timestamps drifted more than the scheme's tolerance
 * from the current time. Bridge advises discarding events older than ~10 min
 * for the v0 scheme; the legacy HMAC path keeps Stripe's 5-minute default.
 *
 * @see https://apidocs.bridge.xyz/platform/additional-information/webhooks/signature
 */
final class BridgeWebhookVerifier
{
    /** Replay window for the asymmetric (v0) scheme — Bridge advises ~10 min. */
    private const ASYMMETRIC_TOLERANCE_SECONDS = 600;

    /** Replay window for the legacy HMAC (v1) scheme — matches Stripe's default. */
    private const HMAC_TOLERANCE_SECONDS = 300;

    /** Timestamps above this are treated as milliseconds, below as seconds. */
    private const MILLISECONDS_THRESHOLD = 100_000_000_000;

    private readonly string $publicKey;

    public function __construct(
        private readonly string $secret = '',
        string $publicKey = '',
    ) {
        $this->publicKey = self::normalizePublicKey($publicKey);
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) config('kyc.providers.bridge.webhook_secret', ''),
            (string) config('kyc.providers.bridge.webhook_public_key', ''),
        );
    }

    public function verify(string $rawBody, string $signatureHeader, ?int $now = null): bool
    {
        if ($this->secret === '' && $this->publicKey === '') {
            // No credentials configured: in non-production we accept any
            // well-formed request so the dev loop works; in production we
            // hard-fail rather than trust an unsigned webhook.
            return ! app()->environment('production');
        }

        if ($signatureHeader === '') {
            return false;
        }

        $parts = $this->parseHeader($signatureHeader);

        $timestampRaw = $parts['t'][0] ?? '';
        if ($timestampRaw === '' || ! ctype_digit($timestampRaw)) {
            return false;
        }

        // v0 = asymmetric RSA (Bridge's current platform). Preferred whenever a
        // public key is configured and the header carries a v0 element.
        $v0 = $parts['v0'] ?? [];
        if ($v0 !== [] && $this->publicKey !== '') {
            return $this->verifyAsymmetric($rawBody, $timestampRaw, $v0, $now);
        }

        // v1 = legacy HMAC. Only usable when a shared secret is configured.
        $v1 = $parts['v1'] ?? [];
        if ($v1 !== [] && $this->secret !== '') {
            return $this->verifyHmac($rawBody, $timestampRaw, $v1, $now);
        }

        return false;
    }

    /**
     * @param  list<string>  $signatures
     */
    private function verifyAsymmetric(string $rawBody, string $timestampRaw, array $signatures, ?int $now): bool
    {
        if (! $this->withinTolerance($timestampRaw, self::ASYMMETRIC_TOLERANCE_SECONDS, $now)) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($this->publicKey);
        if ($publicKey === false) {
            return false;
        }

        $message = $timestampRaw . '.' . $rawBody;

        foreach ($signatures as $candidate) {
            $binarySignature = base64_decode($candidate, true);
            if ($binarySignature === false || $binarySignature === '') {
                continue;
            }

            if (openssl_verify($message, $binarySignature, $publicKey, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $signatures
     */
    private function verifyHmac(string $rawBody, string $timestampRaw, array $signatures, ?int $now): bool
    {
        if (! $this->withinTolerance($timestampRaw, self::HMAC_TOLERANCE_SECONDS, $now)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestampRaw . '.' . $rawBody, $this->secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function withinTolerance(string $timestampRaw, int $tolerance, ?int $now): bool
    {
        $timestamp = (int) $timestampRaw;
        // Bridge's v0 scheme uses millisecond timestamps; the HMAC path uses
        // seconds. Normalise so the replay window comparison is unit-correct
        // regardless of which scheme delivered the event.
        if ($timestamp > self::MILLISECONDS_THRESHOLD) {
            $timestamp = intdiv($timestamp, 1000);
        }

        if ($timestamp <= 0) {
            return false;
        }

        return abs(($now ?? time()) - $timestamp) <= $tolerance;
    }

    /**
     * Parse a `t=...,vN=...` signature header into element => [values].
     *
     * @return array<string, list<string>>
     */
    private function parseHeader(string $signatureHeader): array
    {
        /** @var array<string, list<string>> $parts */
        $parts = [];
        foreach (explode(',', $signatureHeader) as $element) {
            $element = trim($element);
            if ($element === '') {
                continue;
            }
            $pair = array_pad(explode('=', $element, 2), 2, '');
            // Trim the element name so a stray space (e.g. "v0 =sig") can't
            // silently drop a signature element. Verification still gates the
            // outcome, so this only affects tolerance to malformed headers.
            $parts[trim($pair[0])][] = $pair[1];
        }

        return $parts;
    }

    /**
     * Accept the public key as a raw PEM, a single-line env value with escaped
     * newlines (`\n`), or a base64-encoded PEM blob (the friendliest single-line
     * .env form). Returns '' when nothing usable is configured.
     */
    private static function normalizePublicKey(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Tolerate a value pasted with surrounding quotes. dotenv normally
        // strips these, but a stray quote would otherwise corrupt the key.
        if (strlen($raw) >= 2) {
            $quote = $raw[0];
            if (($quote === '"' || $quote === "'") && str_ends_with($raw, $quote)) {
                $raw = trim(substr($raw, 1, -1));
            }
        }

        // Env vars often carry the PEM on one line with literal "\n" escapes.
        if (! str_contains($raw, "\n") && str_contains($raw, '\\n')) {
            $raw = str_replace('\\n', "\n", $raw);
        }

        // Allow a base64-encoded PEM blob (no PEM armour visible yet).
        if (! str_contains($raw, '-----BEGIN')) {
            $decoded = base64_decode($raw, true);
            if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
                $raw = trim($decoded);
            }
        }

        return $raw;
    }
}
