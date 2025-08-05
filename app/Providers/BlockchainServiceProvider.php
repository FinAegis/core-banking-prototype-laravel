<?php

namespace App\Providers;

use App\Domain\Wallet\Factories\BlockchainConnectorFactory;
use App\Domain\Wallet\Services\BlockchainWalletService;
use App\Domain\Wallet\Services\KeyManagementService;
use App\Domain\Wallet\Services\SecureKeyStorageService;
use App\Workflows\BlockchainDepositActivities;
use App\Workflows\BlockchainWithdrawalActivities;
use Illuminate\Support\ServiceProvider;

class BlockchainServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register services.
     */
    public function register(): void
    {
        // Register key management service as singleton
        $this->app->singleton(
            KeyManagementService::class,
            function ($app) {
                return new KeyManagementService();
            }
        );

        // Register blockchain connectors
        $this->app->bind(
            'blockchain.connectors',
            function ($app) {
                // Use factory to create connectors (handles demo mode)
                return [
                    'ethereum' => BlockchainConnectorFactory::create('ethereum'),
                    'polygon'  => BlockchainConnectorFactory::create('polygon'),
                    'bitcoin'  => BlockchainConnectorFactory::create('bitcoin'),
                ];
            }
        );

        // Register secure key storage service as singleton
        $this->app->singleton(SecureKeyStorageService::class, function ($app) {
            return new SecureKeyStorageService(
                $app->make('encrypter'),
                $app->make(KeyManagementService::class)
            );
        });

        // Register blockchain wallet service
        $this->app->singleton(
            BlockchainWalletService::class,
            function ($app) {
                return new BlockchainWalletService(
                    $app->make(KeyManagementService::class),
                    $app->make(SecureKeyStorageService::class)
                );
            }
        );

        // Register workflow activities with connectors
        $this->app->bind(
            BlockchainDepositActivities::class,
            function ($app) {
                return new BlockchainDepositActivities(
                    $app->make(BlockchainWalletService::class),
                    $app->make('blockchain.connectors')
                );
            }
        );

        $this->app->bind(
            BlockchainWithdrawalActivities::class,
            function ($app) {
                return new BlockchainWithdrawalActivities(
                    $app->make(BlockchainWalletService::class),
                    $app->make(KeyManagementService::class),
                    $app->make('blockchain.connectors')
                );
            }
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register blockchain configuration
        $this->publishes(
            [
                __DIR__ . '/../../config/blockchain.php' => config_path('blockchain.php'),
            ],
            'blockchain-config'
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            BlockchainWalletService::class,
            SecureKeyStorageService::class,
        ];
    }
}
