<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Compliance\Projectors\ComplianceAlertProjector;
// Repository Interfaces and Implementations
use App\Domain\Compliance\Projectors\TransactionMonitoringProjector;
use App\Domain\Compliance\Repositories\ComplianceEventRepository;
use App\Domain\Compliance\Repositories\ComplianceSnapshotRepository;
use App\Domain\Exchange\Contracts\LiquidityPoolRepositoryInterface;
use App\Domain\Exchange\Contracts\OrderRepositoryInterface;
use App\Domain\Exchange\Repositories\LiquidityPoolRepository;
// CQRS Infrastructure
use App\Domain\Exchange\Repositories\OrderRepository;
use App\Domain\Shared\CQRS\CommandBus;
// Compliance domain repositories (Spatie Event Sourcing)
use App\Domain\Shared\CQRS\QueryBus;
use App\Domain\Shared\Events\DomainEventBus;
// Compliance projectors
use App\Domain\Stablecoin\Contracts\StablecoinAggregateRepositoryInterface;
use App\Domain\Stablecoin\Repositories\StablecoinAggregateRepository;
use App\Infrastructure\CQRS\LaravelCommandBus;
use App\Infrastructure\CQRS\LaravelQueryBus;
// Domain Event Bus
use App\Infrastructure\Events\LaravelDomainEventBus;
use Illuminate\Support\ServiceProvider;
use Spatie\EventSourcing\Facades\Projectionist;

/**
 * Service provider for domain layer bindings and configuration.
 * Implements dependency inversion for repositories, services, and infrastructure.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerCQRSInfrastructure();
        $this->registerDomainEventBus();
        $this->registerSagas();
        $this->registerEventSourcingRepositories();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerEventSubscribers();
        $this->registerCommandHandlers();
        $this->registerQueryHandlers();
        $this->registerProjectors();
    }

    /**
     * Register repository bindings.
     */
    private function registerRepositories(): void
    {
        // Exchange domain repositories
        $this->app->bind(OrderRepositoryInterface::class, function ($app) {
            return new OrderRepository();
        });

        $this->app->bind(LiquidityPoolRepositoryInterface::class, function ($app) {
            return new LiquidityPoolRepository();
        });

        // Stablecoin domain repositories
        $this->app->bind(StablecoinAggregateRepositoryInterface::class, function ($app) {
            return new StablecoinAggregateRepository();
        });
    }

    /**
     * Register Event Sourcing repositories for Compliance domain.
     */
    private function registerEventSourcingRepositories(): void
    {
        // Compliance Event Sourcing repositories (Spatie)
        $this->app->singleton(ComplianceEventRepository::class, function ($app) {
            return new ComplianceEventRepository();
        });

        $this->app->singleton(ComplianceSnapshotRepository::class, function ($app) {
            return new ComplianceSnapshotRepository();
        });
    }

    /**
     * Register CQRS infrastructure.
     */
    private function registerCQRSInfrastructure(): void
    {
        // Command Bus
        $this->app->singleton(CommandBus::class, function ($app) {
            return new LaravelCommandBus($app);
        });

        // Query Bus
        $this->app->singleton(QueryBus::class, function ($app) {
            return new LaravelQueryBus($app, $app['cache.store']);
        });
    }

    /**
     * Register Domain Event Bus.
     */
    private function registerDomainEventBus(): void
    {
        $this->app->singleton(DomainEventBus::class, function ($app) {
            return new LaravelDomainEventBus($app['events'], $app);
        });
    }

    /**
     * Register saga workflows.
     */
    private function registerSagas(): void
    {
        // Register saga workflows with Laravel Workflow
        $this->app->tag([
            \App\Domain\Exchange\Sagas\OrderFulfillmentSaga::class,
            \App\Domain\Stablecoin\Sagas\StablecoinIssuanceSaga::class,
            // TODO: Create LoanDisbursementSaga
            // \App\Domain\Lending\Sagas\LoanDisbursementSaga::class,
        ], 'sagas');
    }

    /**
     * Register event subscribers.
     */
    private function registerEventSubscribers(): void
    {
        // Only register if not in demo mode or if explicitly enabled
        if (config('app.env') === 'production' || config('domain.enable_handlers', false)) {
            $eventBus = $this->app->make(DomainEventBus::class);

            // Note: Handlers will be implemented as features are developed
            // Example pattern for future implementation:
            // $eventBus->subscribe(
            //     \App\Domain\Exchange\Events\OrderPlaced::class,
            //     \App\Domain\Exchange\Handlers\OrderPlacedHandler::class
            // );
        }
    }

    /**
     * Register command handlers.
     */
    private function registerCommandHandlers(): void
    {
        // Only register if not in demo mode or if explicitly enabled
        if (config('app.env') === 'production' || config('domain.enable_handlers', false)) {
            $commandBus = $this->app->make(CommandBus::class);

            // Note: Command handlers will be registered as features are developed
            // Example pattern for future implementation:
            // $commandBus->register(
            //     \App\Domain\Exchange\Commands\PlaceOrderCommand::class,
            //     \App\Domain\Exchange\Handlers\PlaceOrderHandler::class
            // );
        }
    }

    /**
     * Register query handlers.
     */
    private function registerQueryHandlers(): void
    {
        // Only register if not in demo mode or if explicitly enabled
        if (config('app.env') === 'production' || config('domain.enable_handlers', false)) {
            $queryBus = $this->app->make(QueryBus::class);

            // Note: Query handlers will be registered as features are developed
            // Example pattern for future implementation:
            // $queryBus->register(
            //     \App\Domain\Exchange\Queries\GetOrderQuery::class,
            //     \App\Domain\Exchange\Handlers\GetOrderHandler::class
            // );
        }
    }

    /**
     * Register Spatie Event Sourcing projectors.
     */
    private function registerProjectors(): void
    {
        // Register Compliance projectors
        Projectionist::addProjector(ComplianceAlertProjector::class);
        Projectionist::addProjector(TransactionMonitoringProjector::class);

        // Other domain projectors can be added here as they are implemented
        // Example:
        // Projectionist::addProjector(TreasuryProjector::class);
        // Projectionist::addProjector(LendingProjector::class);
    }
}
