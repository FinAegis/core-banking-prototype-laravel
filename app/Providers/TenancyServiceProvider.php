<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\InitializeTenancyByTeam;
use App\Resolvers\TeamTenantResolver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    /**
     * Get the event listeners for tenancy events.
     *
     * @return array<class-string, array<int, class-string|JobPipeline>>
     */
    public function events(): array
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class  => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    // Jobs\SeedDatabase::class,

                    // Your own jobs to prepare the tenant.
                    // Provision API keys, create S3 buckets, anything you want!

                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // `false` by default, but you probably want to make this `true` for production.
            ],
            Events\SavingTenant::class   => [],
            Events\TenantSaved::class    => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class  => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class  => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // `false` by default, but you probably want to make this `true` for production.
            ],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class  => [],
            Events\SavingDomain::class   => [],
            Events\DomainSaved::class    => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class  => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class  => [],

            // Database events
            Events\DatabaseCreated::class    => [],
            Events\DatabaseMigrated::class   => [],
            Events\DatabaseSeeded::class     => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class    => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class  => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class  => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class      => [],
            Events\TenancyBootstrapped::class       => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class  => [],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register(): void
    {
        // Register the TeamTenantResolver as a singleton
        // The CachedTenantResolver base class requires a Cache Factory
        $this->app->singleton(TeamTenantResolver::class);
    }

    public function boot(): void
    {
        $this->configureTenantConnection();
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
    }

    /**
     * Configure the tenant database connection to mirror the default connection.
     *
     * When tenancy is not initialized (e.g., during testing), the 'tenant' connection
     * should point to the same database as the default connection.
     *
     * Note: For in-memory SQLite testing, the UsesTenantConnection trait has
     * been modified to return null (use default connection) in testing mode.
     * This is simpler and more reliable than trying to share PDO connections.
     *
     * Once stancl/tenancy initializes a tenant, it will override the 'tenant' connection
     * to point to the tenant's specific database.
     */
    protected function configureTenantConnection(): void
    {
        // Get the default connection name and its configuration
        $defaultConnection = Config::get('database.default');
        $defaultConfig = Config::get("database.connections.{$defaultConnection}");

        if (! $defaultConfig) {
            return;
        }

        // Copy the default config to tenant connection
        // This ensures 'tenant' is a valid connection name even when
        // the UsesTenantConnection trait falls back to the default connection
        Config::set('database.connections.tenant', $defaultConfig);
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes(): void
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,

            // Custom team-based tenant identification
            InitializeTenancyByTeam::class,
        ];

        /** @var \Illuminate\Contracts\Http\Kernel $kernel */
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
