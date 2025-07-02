<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment() !== 'testing') {
            $this->app->register( WaterlineServiceProvider::class);
        }
        
        // Register voting power strategies
        $this->app->bind('asset_weighted_vote', \App\Domain\Governance\Strategies\AssetWeightedVoteStrategy::class);
        $this->app->bind('one_user_one_vote', \App\Domain\Governance\Strategies\OneUserOneVoteStrategy::class);
        $this->app->bind(\App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class, \App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Treat 'demo' environment as production
        if ($this->app->environment('demo')) {
            // Force production-like settings
            config(['app.debug' => config('demo.debug', false)]);
            config(['app.debug_blacklist' => config('demo.debug_blacklist')]);
            
            // Force HTTPS in demo environment
            if (request()->getHost() !== 'localhost' && request()->getHost() !== '127.0.0.1') {
                \URL::forceScheme('https');
            }
            
            // Apply demo-specific rate limits
            config(['app.rate_limits.api' => config('demo.rate_limits.api', 60)]);
            config(['app.rate_limits.transactions' => config('demo.rate_limits.transactions', 10)]);
        }
    }
}
