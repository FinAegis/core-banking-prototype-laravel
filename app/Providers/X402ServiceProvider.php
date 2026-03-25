<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\X402\Contracts\FacilitatorClientInterface;
use App\Domain\X402\Contracts\X402SignerInterface;
use App\Domain\X402\Services\HttpFacilitatorClient;
use App\Domain\X402\Services\X402ClientService;
use App\Domain\X402\Services\X402DiscoveryService;
use App\Domain\X402\Services\X402EIP712SignerService;
use App\Domain\X402\Services\X402HeaderCodecService;
use App\Domain\X402\Services\X402PaymentVerificationService;
use App\Domain\X402\Services\X402PricingService;
use App\Domain\X402\Services\X402SettlementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the X402 Payment Protocol domain.
 *
 * Binds facilitator client, signer, and core services for both
 * resource-server (monetize APIs) and client (AI agent payments) modes.
 */
class X402ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/x402.php',
            'x402'
        );

        // Bind the facilitator HTTP client as singleton (shared by verification and settlement)
        $this->app->singleton(FacilitatorClientInterface::class, function ($app) {
            return new HttpFacilitatorClient(
                http: Http::getFacadeRoot(),
                facilitatorUrl: (string) config('x402.facilitator.url', 'https://x402.org/facilitator'),
                timeoutSeconds: (int) config('x402.facilitator.timeout_seconds', 30),
            );
        });

        // Bind the EIP-712 signer (demo mode)
        // For production, swap with HSM-backed signer via KeyManagement domain
        $this->app->bind(X402SignerInterface::class, function ($app) {
            return new X402EIP712SignerService(
                signerKeyId: (string) config('x402.client.signer_key_id', 'default'),
            );
        });

        // Register core services as singletons
        $this->app->singleton(X402DiscoveryService::class);
        $this->app->singleton(X402HeaderCodecService::class);
        $this->app->singleton(X402PricingService::class);

        $this->app->singleton(X402PaymentVerificationService::class, function ($app) {
            return new X402PaymentVerificationService(
                facilitator: $app->make(FacilitatorClientInterface::class),
                pricingService: $app->make(X402PricingService::class),
            );
        });

        $this->app->singleton(X402SettlementService::class, function ($app) {
            return new X402SettlementService(
                facilitator: $app->make(FacilitatorClientInterface::class),
                verificationService: $app->make(X402PaymentVerificationService::class),
            );
        });

        $this->app->singleton(X402ClientService::class, function ($app) {
            return new X402ClientService(
                signer: $app->make(X402SignerInterface::class),
                codec: $app->make(X402HeaderCodecService::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
