<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Contracts\SmartAccountFactoryInterface;
use App\Domain\Relayer\Contracts\UserOperationSignerInterface;
use App\Domain\Relayer\Services\DemoBundlerService;
use App\Domain\Relayer\Services\DemoPaymasterService;
use App\Domain\Relayer\Services\DemoSmartAccountFactory;
use App\Domain\Relayer\Services\UserOperationSigningService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Relayer domain (ERC-4337 Gas Abstraction).
 *
 * Binds bundler, paymaster, and smart account factory contracts to implementations.
 * In production, swap with real ERC-4337 bundler services like:
 * - Pimlico, Stackup, Alchemy Account Abstraction
 */
class RelayerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the bundler interface to the demo implementation
        // For production, create AlchemyBundlerService, PimlicoBundlerService, etc.
        $this->app->bind(BundlerInterface::class, function ($app) {
            return new DemoBundlerService();
        });

        // Bind the paymaster interface to the demo implementation
        // For production, integrate with your actual paymaster contract
        $this->app->bind(PaymasterInterface::class, function ($app) {
            return new DemoPaymasterService();
        });

        // Bind the smart account factory interface (v2.6.0)
        // For production, implement with actual factory contract calls
        $this->app->bind(SmartAccountFactoryInterface::class, function ($app) {
            return new DemoSmartAccountFactory();
        });

        // Bind the UserOperation signer interface for auth shard signing
        // For production, integrate with HSM-backed key management
        $this->app->bind(UserOperationSignerInterface::class, function ($app) {
            return new UserOperationSigningService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
