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
        //
    }
}
