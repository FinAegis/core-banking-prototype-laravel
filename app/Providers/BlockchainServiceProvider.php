<?php

namespace App\Providers;

use App\Domain\Wallet\Connectors\SimpleBitcoinConnector;
use App\Domain\Wallet\Connectors\EthereumConnector;
use App\Domain\Wallet\Connectors\PolygonConnector;
use App\Domain\Wallet\Services\KeyManagementService;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Workflows\BlockchainDepositActivities;
use App\Workflows\BlockchainWithdrawalActivities;
use Illuminate\Support\ServiceProvider;

class BlockchainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register key management service as singleton
        $this->app->singleton(KeyManagementService::class, function ($app) {
            return new KeyManagementService();
        });
        
        // Register blockchain connectors
        $this->app->bind('blockchain.connectors', function ($app) {
            return [
                'ethereum' => new EthereumConnector([
                    'rpc_url' => config('blockchain.ethereum.rpc_url'),
                    'chain_id' => config('blockchain.ethereum.chain_id'),
                ]),
                'polygon' => new PolygonConnector([
                    'rpc_url' => config('blockchain.polygon.rpc_url'),
                    'chain_id' => config('blockchain.polygon.chain_id'),
                ]),
                'bsc' => new EthereumConnector([
                    'rpc_url' => config('blockchain.bsc.rpc_url'),
                    'chain_id' => config('blockchain.bsc.chain_id'),
                ]),
                'bitcoin' => new SimpleBitcoinConnector([
                    'network' => config('blockchain.bitcoin.network'),
                    'api_url' => config('blockchain.bitcoin.api_url'),
                    'api_key' => config('blockchain.bitcoin.api_key'),
                ]),
            ];
        });
        
        // Register blockchain wallet service
        $this->app->singleton(BlockchainWalletService::class, function ($app) {
            return new BlockchainWalletService(
                $app->make(KeyManagementService::class)
            );
        });
        
        // Register workflow activities with connectors
        $this->app->bind(BlockchainDepositActivities::class, function ($app) {
            return new BlockchainDepositActivities(
                $app->make(BlockchainWalletService::class),
                $app->make('blockchain.connectors')
            );
        });
        
        $this->app->bind(BlockchainWithdrawalActivities::class, function ($app) {
            return new BlockchainWithdrawalActivities(
                $app->make(BlockchainWalletService::class),
                $app->make(KeyManagementService::class),
                $app->make('blockchain.connectors')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register blockchain configuration
        $this->publishes([
            __DIR__.'/../../config/blockchain.php' => config_path('blockchain.php'),
        ], 'blockchain-config');
    }
}