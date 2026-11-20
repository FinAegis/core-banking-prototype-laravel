<?php

namespace App\Providers;

use App\Support\StaffRoles;
use Illuminate\Support\Facades\Gate;
use Waterline\WaterlineApplicationServiceProvider;

class WaterlineServiceProvider extends WaterlineApplicationServiceProvider
{
    /**
     * Register the Waterline gate.
     *
     * This gate determines who can access Waterline in non-local environments:
     * platform admins (StaffRoles::ADMINS).
     *
     * @return void
     */
    protected function gate()
    {
        // Platform admins only, checked by role and never by email address:
        // an address can be registered by anyone before it is listed. Local
        // development is open through the vendor authorization callback.
        Gate::define(
            'viewWaterline',
            fn ($user = null): bool => StaffRoles::userHasAny($user, StaffRoles::ADMINS)
        );
    }
}
