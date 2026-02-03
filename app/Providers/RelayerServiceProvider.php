<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\KeyManagement\HSM\HsmIntegrationService;
use App\Domain\Mobile\Contracts\BiometricJWTServiceInterface;
use App\Domain\Mobile\Services\BiometricJWTService;
use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Contracts\SmartAccountFactoryInterface;
use App\Domain\Relayer\Contracts\UserOperationSignerInterface;
use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Services\DemoBundlerService;
use App\Domain\Relayer\Services\DemoPaymasterService;
use App\Domain\Relayer\Services\DemoSmartAccountFactory;
use App\Domain\Relayer\Services\DemoWalletBalanceService;
use App\Domain\Relayer\Services\GasStationService;
use App\Domain\Relayer\Services\UserOperationSigningService;
use App\Domain\Relayer\Services\WalletBalanceService;
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
        // Bind BiometricJWTService for mobile authentication
        $this->app->bind(BiometricJWTServiceInterface::class, BiometricJWTService::class);

        // Bind the wallet balance provider based on configuration
        $this->app->bind(WalletBalanceProviderInterface::class, function ($app) {
            $provider = config('relayer.balance_checking.provider', 'demo');

            return match ($provider) {
                'demo'  => new DemoWalletBalanceService(),
                default => new WalletBalanceService(),
            };
        });

        // Bind GasStationService with balance provider
        $this->app->bind(GasStationService::class, function ($app) {
            return new GasStationService(
                $app->make(PaymasterInterface::class),
                $app->make(BundlerInterface::class),
                $app->make(WalletBalanceProviderInterface::class)
            );
        });

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
        // Integrates with HSM and BiometricJWT for production-ready signing
        $this->app->bind(UserOperationSignerInterface::class, function ($app) {
            // Only inject JWT service when strict mode is enabled
            $jwtService = config('mobile.biometric_jwt.strict_mode', false)
                ? $app->make(BiometricJWTServiceInterface::class)
                : null;

            return new UserOperationSigningService(
                $app->make(HsmIntegrationService::class),
                $jwtService
            );
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
