<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\CardIssuance\Adapters\DemoCardIssuerAdapter;
use App\Domain\CardIssuance\Adapters\MarqetaCardIssuerAdapter;
use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Infrastructure\FinCard\FinCardClient;
use App\Infrastructure\FinCard\FinCardWebhookVerifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the CardIssuance domain.
 *
 * Binds card issuer contracts to implementations based on configuration.
 * Supports demo, Marqeta, and future adapters (Lithic, Stripe Issuing).
 */
class CardIssuanceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CardIssuerInterface::class, function ($app) {
            $issuer = config('cardissuance.default_issuer', 'demo');

            return match ($issuer) {
                'marqeta' => new MarqetaCardIssuerAdapter(
                    config: (array) config('cardissuance.issuers.marqeta', []),
                ),
                default => new DemoCardIssuerAdapter(),
            };
        });

        // FinCard infrastructure — the outbound client and the inbound webhook
        // verifier. Bound as singletons so the cached session JWT is shared
        // across a request lifecycle. The `fincard` CardIssuerInterface match
        // arm is added when the adapter lands (later phases); Phase 1 only needs
        // the client (reference data) and the verifier (webhook endpoint).
        $this->app->singleton(FinCardClient::class, static fn () => FinCardClient::fromConfig());
        $this->app->singleton(FinCardWebhookVerifier::class, static fn () => FinCardWebhookVerifier::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Finding #15: Warn in production when the Marqeta HMAC secret is absent.
        // Without this secret, incoming webhook payloads cannot be signature-verified,
        // leaving the authorization endpoint open to spoofed requests.
        if (
            $this->app->environment('production')
            && config('cardissuance.default_issuer') === 'marqeta'
            && empty(config('cardissuance.issuers.marqeta.hmac_secret'))
        ) {
            Log::debug('Marqeta HMAC secret not configured — sandbox mode, webhook signature validation relaxed');
        }

        // FinCard webhooks are RSA-signed; without the platform public key the
        // verifier fails closed in production (rejecting every event). Warn loudly
        // so a misconfigured deploy is caught. ops:verify-env FAILs on this too.
        if (
            $this->app->environment('production')
            && config('cardissuance.default_issuer') === 'fincard'
            && empty(config('cardissuance.issuers.fincard.webhook_public_key'))
        ) {
            Log::warning('FinCard webhook public key not configured — all inbound FinCard webhooks will be rejected in production');
        }
    }
}
