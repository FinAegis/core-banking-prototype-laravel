<?php

namespace App\Providers;

use App\Support\StaffRoles;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(
            function (IncomingEntry $entry) use ($isLocal) {
                return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
            }
        );
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders(
            [
                'cookie',
                'x-csrf-token',
                'x-xsrf-token',
            ]
        );
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments:
     * platform admins (StaffRoles::ADMINS).
     */
    protected function gate(): void
    {
        // Platform admins only, checked by role and never by email address:
        // an address can be registered by anyone before it is listed. Local
        // development is open through the vendor authorization callback.
        Gate::define(
            'viewTelescope',
            fn ($user = null): bool => StaffRoles::userHasAny($user, StaffRoles::ADMINS)
        );
    }
}
