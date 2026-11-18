<?php

declare(strict_types=1);

namespace App\Infrastructure\FinCard;

/**
 * Verifies a FinCard (FinHub) webhook signature.
 *
 * Per docs.finhub.cloud/paas/fincard-virtual/webhooks, every webhook carries a
 * signature header containing `SHA256withRSA(rawResponseBody)`, base64-encoded,
 * verified against FinCard's platform RSA public key. Unlike Bridge there is no
 * timestamp element — the signature is over the raw body alone.
 *
 * Header name: FinCard's own materials disagree — the narrative webhooks page
 * uses `X-FC-SIGNATURE`, the API reference and the mock use `X-WSB-SIGNATURE`.
 * We accept EITHER (see {@see signatureFrom}); which one production actually
 * sends is an open item to confirm with FinCard (design spec §14), as is
 * obtaining the public key itself.
 *
 * Fail-closed: with no public key configured we accept in non-production (so the
 * dev loop works against Playground) and hard-fail in production rather than
 * trust an unsigned webhook — the same idiom as {@see \App\Infrastructure\Bridge\BridgeWebhookVerifier}.
 */
final class FinCardWebhookVerifier
{
    /** Webhook signature header names FinCard may send, in preference order. */
    public const SIGNATURE_HEADERS = ['X-FC-SIGNATURE', 'X-WSB-SIGNATURE'];

    private readonly string $publicKey;

    public function __construct(string $publicKey = '')
    {
        $this->publicKey = self::normalizePublicKey($publicKey);
    }

    public static function fromConfig(): self
    {
        return new self((string) config('cardissuance.issuers.fincard.webhook_public_key', ''));
    }

    public function verify(string $rawBody, string $signature): bool
    {
        if ($this->publicKey === '') {
            // No key configured: accept in non-prod (Playground dev loop),
            // hard-fail in production rather than trust an unsigned webhook.
            return ! app()->environment('production');
        }

        if ($signature === '') {
            return false;
        }

        $binarySignature = base64_decode($signature, true);
        if ($binarySignature === false || $binarySignature === '') {
            return false;
        }

        $publicKey = openssl_pkey_get_public($this->publicKey);
        if ($publicKey === false) {
            return false;
        }

        return openssl_verify($rawBody, $binarySignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Resolve the signature value from whichever header FinCard sent.
     *
     * @param  callable(string): (string|null)  $headerLookup  case-insensitive header reader
     */
    public static function signatureFrom(callable $headerLookup): string
    {
        foreach (self::SIGNATURE_HEADERS as $name) {
            $value = (string) ($headerLookup($name) ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Accept the public key as raw PEM, a single-line env value with escaped
     * `\n`, or a base64-encoded PEM blob. Returns '' when nothing usable is set.
     */
    private static function normalizePublicKey(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (strlen($raw) >= 2) {
            $quote = $raw[0];
            if (($quote === '"' || $quote === "'") && str_ends_with($raw, $quote)) {
                $raw = trim(substr($raw, 1, -1));
            }
        }

        if (! str_contains($raw, "\n") && str_contains($raw, '\\n')) {
            $raw = str_replace('\\n', "\n", $raw);
        }

        if (! str_contains($raw, '-----BEGIN')) {
            $decoded = base64_decode($raw, true);
            if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
                $raw = trim($decoded);
            }
        }

        return $raw;
    }
}
