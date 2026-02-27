<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Privacy\Contracts\MerkleTreeServiceInterface;
use App\Domain\Privacy\Contracts\ZkProverInterface;
use App\Domain\Privacy\Services\DemoMerkleTreeService;
use App\Domain\Privacy\Services\DemoZkProver;
use App\Domain\Privacy\Services\MerkleTreeService;
use App\Domain\Privacy\Services\RailgunBridgeClient;
use App\Domain\Privacy\Services\RailgunMerkleTreeService;
use App\Domain\Privacy\Services\RailgunPrivacyService;
use App\Domain\Privacy\Services\RailgunZkProverService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Privacy domain.
 *
 * Binds ZK prover and Merkle tree service contracts to implementations.
 * Supports three providers: demo, production (snarkjs), railgun (RAILGUN SDK).
 */
class PrivacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the RAILGUN bridge client singleton
        $this->app->singleton(RailgunBridgeClient::class, function () {
            return new RailgunBridgeClient(
                baseUrl: (string) config('privacy.railgun.bridge_url', 'http://127.0.0.1:3100'),
                secret: (string) config('privacy.railgun.bridge_secret', ''),
                timeout: (int) config('privacy.railgun.bridge_timeout', 30),
            );
        });

        // Bind the ZK prover interface based on configuration
        $this->app->bind(ZkProverInterface::class, function ($app) {
            $provider = config('privacy.zk.provider', 'demo');

            return match ($provider) {
                'railgun' => $app->make(RailgunZkProverService::class),
                'demo'    => new DemoZkProver(),
                default   => new DemoZkProver(),
            };
        });

        // Bind the Merkle tree service interface based on configuration
        $this->app->bind(MerkleTreeServiceInterface::class, function ($app) {
            $provider = config('privacy.merkle.provider', 'demo');

            return match ($provider) {
                'railgun'    => $app->make(RailgunMerkleTreeService::class),
                'production' => new MerkleTreeService(),
                'demo'       => new DemoMerkleTreeService(),
                default      => new DemoMerkleTreeService(),
            };
        });

        // Register the RAILGUN privacy service (available when provider is railgun)
        $this->app->singleton(RailgunPrivacyService::class, function ($app) {
            return new RailgunPrivacyService(
                bridge: $app->make(RailgunBridgeClient::class),
                merkleService: $app->make(MerkleTreeServiceInterface::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
