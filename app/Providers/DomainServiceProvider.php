<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Exchange\Contracts\LiquidityPoolRepositoryInterface;
// Repository Interfaces and Implementations
use App\Domain\Exchange\Contracts\OrderRepositoryInterface;
use App\Domain\Exchange\Repositories\LiquidityPoolRepository;
use App\Domain\Exchange\Repositories\OrderRepository;
use App\Domain\Shared\CQRS\CommandBus;
use App\Domain\Shared\CQRS\QueryBus;
use App\Domain\Shared\Events\DomainEventBus;
// CQRS Infrastructure
use App\Domain\Stablecoin\Contracts\StablecoinAggregateRepositoryInterface;
use App\Domain\Stablecoin\Repositories\StablecoinAggregateRepository;
use App\Infrastructure\CQRS\LaravelCommandBus;
use App\Infrastructure\CQRS\LaravelQueryBus;
// Domain Event Bus
use App\Infrastructure\Events\LaravelDomainEventBus;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerEventSubscribers();
        $this->registerCommandHandlers();
        $this->registerQueryHandlers();
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

            // Note: Handlers will be implemented as features are developed
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

            // Note: Handlers will be implemented as features are developed
            // Example pattern for future implementation:
            // $queryBus->register(
            //     \App\Domain\Exchange\Queries\GetOrderBookQuery::class,
            //     \App\Domain\Exchange\Handlers\GetOrderBookHandler::class
            // );
        }
    }
}
