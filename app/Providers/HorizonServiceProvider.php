<?php

namespace App\Providers;

use App\Support\StaffRoles;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments:
     * platform admins (StaffRoles::ADMINS).
     */
    protected function gate(): void
    {
        // Platform admins only, checked by role and never by email address:
        // an address can be registered by anyone before it is listed. Local
        // development is open through the vendor authorization callback.
        Gate::define(
            'viewHorizon',
            fn ($user = null): bool => StaffRoles::userHasAny($user, StaffRoles::ADMINS)
        );
    }
}
