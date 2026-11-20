<?php

declare(strict_types=1);

namespace App\Infrastructure\Lepo;

/**
 * Verifies Lepo's webhook signature: header `X-LepoFinance-Signature` is the
 * lowercase hex HMAC-SHA256 of the exact raw body, keyed by the webhook secret
 * set in the Lepo partner portal.
 *
 * Without a secret, unsigned webhooks are accepted only in local and testing;
 * every other environment rejects them.
 */
final class LepoWebhookVerifier
{
    public function __construct(private readonly string $secret = '')
    {
    }

    public static function fromConfig(): self
    {
        return new self((string) config('cardissuance.issuers.lepo.webhook_secret', ''));
    }

    public function verify(string $rawBody, string $signatureHeader): bool
    {
        if ($this->secret === '') {
            return app()->environment(['local', 'testing']);
        }

        $signature = strtolower(trim($signatureHeader));
        if ($signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $this->secret), $signature);
    }
}
