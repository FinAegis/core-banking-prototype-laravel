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
use App\Domain\Relayer\Events\TransactionSponsored;
use App\Domain\Relayer\Listeners\RecordGenericSponsorshipSpend;
use App\Domain\Relayer\Services\DemoBundlerService;
use App\Domain\Relayer\Services\DemoPaymasterService;
use App\Domain\Relayer\Services\DemoSmartAccountFactory;
use App\Domain\Relayer\Services\DemoWalletBalanceService;
use App\Domain\Relayer\Services\EthRpcClient;
use App\Domain\Relayer\Services\GasStationService;
use App\Domain\Relayer\Services\PimlicoBundlerService;
use App\Domain\Relayer\Services\PimlicoPaymasterService;
use App\Domain\Relayer\Services\ProductionSmartAccountFactory;
use App\Domain\Relayer\Services\UserOperationSigningService;
use App\Domain\Relayer\Services\WalletBalanceService;
use App\Http\Middleware\TransactionRateLimitMiddleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        // Shared RPC client singleton (used by production bundler, paymaster, factory)
        $this->app->singleton(EthRpcClient::class);

        // Bind the wallet balance provider based on configuration
        $this->app->bind(WalletBalanceProviderInterface::class, function ($app) {
            $provider = config('relayer.balance_checking.provider', 'demo');

            return match ($provider) {
                'demo'  => new DemoWalletBalanceService(),
                default => new WalletBalanceService(),
            };
        });

        // Bind the bundler interface based on config driver
        $this->app->bind(BundlerInterface::class, function ($app) {
            $driver = config('relayer.bundler.driver', 'demo');

            return match ($driver) {
                'pimlico' => new PimlicoBundlerService($app->make(EthRpcClient::class)),
                default   => new DemoBundlerService(),
            };
        });

        // Bind the paymaster interface based on config driver
        $this->app->bind(PaymasterInterface::class, function ($app) {
            $driver = config('relayer.bundler.driver', 'demo');

            return match ($driver) {
                'pimlico' => new PimlicoPaymasterService($app->make(EthRpcClient::class)),
                default   => new DemoPaymasterService(),
            };
        });

        // Bind the smart account factory interface based on config driver
        $this->app->bind(SmartAccountFactoryInterface::class, function ($app) {
            $driver = config('relayer.bundler.driver', 'demo');

            return match ($driver) {
                'demo'  => new DemoSmartAccountFactory(),
                default => new ProductionSmartAccountFactory($app->make(EthRpcClient::class)),
            };
        });

        // Bind GasStationService with balance provider and optional RPC client
        $this->app->bind(GasStationService::class, function ($app) {
            $driver = config('relayer.bundler.driver', 'demo');
            $rpcClient = $driver !== 'demo' ? $app->make(EthRpcClient::class) : null;

            return new GasStationService(
                $app->make(PaymasterInterface::class),
                $app->make(BundlerInterface::class),
                $app->make(WalletBalanceProviderInterface::class),
                $rpcClient
            );
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
        // Generic sponsorship spends platform gas: charge it to the shared
        // daily USD sponsorship budget (wallet.sponsorship.daily_budget_usd).
        Event::listen(TransactionSponsored::class, RecordGenericSponsorshipSpend::class);

        // Per-user limit for the GraphQL sponsorTransaction mutation
        // (@throttle(name: "relayer-sponsor") in graphql/relayer.graphql):
        // the hourly and daily counts that REST POST /api/v1/relayer/sponsor
        // gets from transaction.rate_limit:relayer, with the same switches.
        RateLimiter::for('relayer-sponsor', function (Request $request): Limit|array {
            if (
                ! (bool) config('rate_limiting.enabled', true)
                || ($this->app->environment('testing') && ! (bool) config('rate_limiting.force_in_tests', false))
            ) {
                return Limit::none();
            }

            /** @var array{limit: int, daily_limit: int} $limits */
            $limits = TransactionRateLimitMiddleware::getTransactionLimits()['relayer'];
            $key = 'relayer-sponsor:' . ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [
                Limit::perHour($limits['limit'])->by($key . ':hour'),
                Limit::perDay($limits['daily_limit'])->by($key . ':day'),
            ];
        });
    }
}
