<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\CrossChain\Contracts\AssetMapperInterface;
use App\Domain\CrossChain\Services\Adapters\AxelarBridgeAdapter;
use App\Domain\CrossChain\Services\Adapters\CircleCctpBridgeAdapter;
use App\Domain\CrossChain\Services\Adapters\DemoBridgeAdapter;
use App\Domain\CrossChain\Services\Adapters\LayerZeroBridgeAdapter;
use App\Domain\CrossChain\Services\Adapters\WormholeBridgeAdapter;
use App\Domain\CrossChain\Services\BridgeFeeComparisonService;
use App\Domain\CrossChain\Services\BridgeOrchestratorService;
use App\Domain\CrossChain\Services\BridgeTransactionTracker;
use App\Domain\CrossChain\Services\CrossChainAssetRegistryService;
use App\Domain\CrossChain\Services\CrossChainSwapSaga;
use App\Domain\CrossChain\Services\CrossChainSwapService;
use App\Domain\CrossChain\Services\CrossChainTokenMapService;
use App\Domain\CrossChain\Services\CrossChainYieldService;
use App\Domain\CrossChain\Services\MultiChainPortfolioService;
use App\Domain\DeFi\Contracts\LendingProtocolInterface;
use App\Domain\DeFi\Contracts\LiquidStakingInterface;
use App\Domain\DeFi\Services\DeFiPortfolioService;
use App\Domain\DeFi\Services\SwapRouterService;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the CrossChain domain (cross-chain bridges & asset management).
 */
class CrossChainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/crosschain.php',
            'crosschain',
        );

        $this->app->singleton(CrossChainAssetRegistryService::class);

        $this->app->bind(AssetMapperInterface::class, CrossChainAssetRegistryService::class);

        $this->app->singleton(BridgeTransactionTracker::class);

        $this->app->singleton(BridgeOrchestratorService::class, function ($app) {
            $orchestrator = new BridgeOrchestratorService();

            // Always register demo adapter
            $orchestrator->registerAdapter(new DemoBridgeAdapter());

            // Register production adapters when enabled
            if (config('crosschain.wormhole.enabled', false)) {
                $orchestrator->registerAdapter(new WormholeBridgeAdapter());
            }

            if (config('crosschain.layerzero.enabled', false)) {
                $orchestrator->registerAdapter(new LayerZeroBridgeAdapter());
            }

            if (config('crosschain.axelar.enabled', false)) {
                $orchestrator->registerAdapter(new AxelarBridgeAdapter());
            }

            if (config('crosschain.circle_cctp.enabled', false)) {
                $orchestrator->registerAdapter(new CircleCctpBridgeAdapter());
            }

            return $orchestrator;
        });

        $this->app->singleton(BridgeFeeComparisonService::class, function ($app) {
            return new BridgeFeeComparisonService(
                $app->make(BridgeOrchestratorService::class),
            );
        });

        $this->app->singleton(CrossChainTokenMapService::class, function ($app) {
            return new CrossChainTokenMapService(
                $app->make(CrossChainAssetRegistryService::class),
            );
        });

        // Cross-chain + DeFi integration services
        $this->app->singleton(CrossChainSwapSaga::class, function ($app) {
            return new CrossChainSwapSaga(
                $app->make(BridgeOrchestratorService::class),
                $app->make(SwapRouterService::class),
                $app->make(BridgeTransactionTracker::class),
            );
        });

        $this->app->singleton(CrossChainSwapService::class, function ($app) {
            return new CrossChainSwapService(
                $app->make(BridgeOrchestratorService::class),
                $app->make(SwapRouterService::class),
                $app->make(CrossChainSwapSaga::class),
            );
        });

        $this->app->singleton(CrossChainYieldService::class, function ($app) {
            return new CrossChainYieldService(
                $app->make(DeFiPortfolioService::class),
                $app->make(BridgeOrchestratorService::class),
                $app->make(LendingProtocolInterface::class),
                $app->make(LiquidStakingInterface::class),
            );
        });

        $this->app->singleton(MultiChainPortfolioService::class, function ($app) {
            return new MultiChainPortfolioService(
                $app->make(DeFiPortfolioService::class),
                $app->make(BridgeTransactionTracker::class),
                $app->make(CrossChainAssetRegistryService::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
