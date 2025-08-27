<?php

namespace App\Providers;

use App\Domain\Custodian\Services\CustodianRegistry;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class CustodianServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register CustodianRegistry as singleton
        $this->app->singleton(
            CustodianRegistry::class,
            function ($app) {
                return new CustodianRegistry();
            }
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config file
        $this->publishes(
            [
                __DIR__ . '/../../config/custodians.php' => config_path('custodians.php'),
            ],
            'config'
        );

        // Register custodians from config
        $this->registerCustodians();
    }

    /**
     * Register all configured custodians.
     */
    private function registerCustodians(): void
    {
        $registry = app(CustodianRegistry::class);
        $config = config('custodians.connectors', []);
        $default = config('custodians.default');

        foreach ($config as $name => $settings) {
            if (! $settings['enabled'] ?? false) {
                continue;
            }

            try {
                $connectorClass = $settings['class'] ?? null;

                if (! $connectorClass || ! class_exists($connectorClass)) {
                    Log::warning("Custodian connector class not found: {$connectorClass}");

                    continue;
                }

                // Create connector instance with config
                $connector = new $connectorClass($settings);

                // Register with the registry
                $registry->register($name, $connector);

                // Set as default if specified
                if ($name === $default) {
                    $registry->setDefault($name);
                }

                Log::info("Registered custodian connector: {$name}");
            } catch (Exception $e) {
                Log::error("Failed to register custodian {$name}: " . $e->getMessage());
            }
        }
    }
}
