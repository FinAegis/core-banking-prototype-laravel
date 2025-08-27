<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;
use URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment() !== 'testing') {
            $this->app->register(WaterlineServiceProvider::class);
        }

        // Register voting power strategies
        $this->app->bind('asset_weighted_vote', \App\Domain\Governance\Strategies\AssetWeightedVoteStrategy::class);
        $this->app->bind('one_user_one_vote', \App\Domain\Governance\Strategies\OneUserOneVoteStrategy::class);
        $this->app->bind(\App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class, \App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class);

        // Register blockchain service provider
        $this->app->register(BlockchainServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure factory namespace resolution for domain models
        /**
         * @param class-string<\Illuminate\Database\Eloquent\Model> $modelName
         * @return class-string<Factory>
         */
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            // For domain models, preserve the full path structure
            if (str_starts_with($modelName, 'App\\Domain\\')) {
                // Replace App\ with Database\Factories\ and append Factory
                $factoryName = str_replace('App\\', 'Database\\Factories\\', $modelName) . 'Factory';

                /** @var class-string<Factory> */
                return $factoryName;
            }

            // For non-domain models, use the default pattern
            $modelBaseName = class_basename($modelName);

            /** @var class-string<Factory> */
            return 'Database\\Factories\\' . $modelBaseName . 'Factory';
        });

        // Treat 'demo' environment as production
        if ($this->app->environment('demo')) {
            // Force production-like settings
            config(['app.debug' => config('demo.debug', false)]);
            config(['app.debug_blacklist' => config('demo.debug_blacklist')]);

            // Force HTTPS in demo environment (but not for local development)
            $localHosts = explode(',', config('app.local_hostnames', 'localhost,127.0.0.1'));
            if (! in_array(request()->getHost(), $localHosts)) {
                URL::forceScheme('https');
            }

            // Apply demo-specific rate limits
            config(['app.rate_limits.api' => config('demo.rate_limits.api', 60)]);
            config(['app.rate_limits.transactions' => config('demo.rate_limits.transactions', 10)]);
        }
    }
}
