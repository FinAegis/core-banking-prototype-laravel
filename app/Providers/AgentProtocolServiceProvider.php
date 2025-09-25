<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\AgentProtocol\Repositories\AgentRepositoryInterface;
use App\Domain\AgentProtocol\Repositories\EloquentAgentRepository;
use App\Domain\AgentProtocol\Repositories\AgentEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentSnapshotRepository;
use App\Domain\AgentProtocol\Services\AgentDiscoveryService;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\AgentWalletService;
use App\Domain\AgentProtocol\Services\EscrowService;
use App\Domain\AgentProtocol\Services\MessagingService;
use App\Domain\AgentProtocol\Services\PaymentService;
use App\Domain\AgentProtocol\Services\ReputationService;
use App\Domain\AgentProtocol\Services\TrustService;
use App\Domain\AgentProtocol\Services\DigitalSignatureService;
use App\Domain\AgentProtocol\Services\EncryptionService;
use App\Domain\AgentProtocol\Services\Integration\AIIntegrationService;
use App\Domain\AgentProtocol\Services\Integration\ComplianceIntegrationService;
use App\Domain\AgentProtocol\Services\Integration\CoordinationIntegrationService;
use App\Domain\AgentProtocol\Services\Integration\WalletIntegrationService;
use Illuminate\Support\ServiceProvider;
use Spatie\EventSourcing\Facades\Projectionist;

class AgentProtocolServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register repositories
        $this->registerRepositories();

        // Register domain services
        $this->registerDomainServices();

        // Register integration services
        $this->registerIntegrationServices();

        // Register event sourcing repositories
        $this->registerEventSourcingRepositories();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register projectors
        $this->registerProjectors();

        // Register event listeners
        $this->registerEventListeners();

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/agent_protocol');
    }

    /**
     * Register repository bindings.
     */
    private function registerRepositories(): void
    {
        // Bind repository interfaces to implementations
        $this->app->bind(AgentRepositoryInterface::class, EloquentAgentRepository::class);
    }

    /**
     * Register domain services.
     */
    private function registerDomainServices(): void
    {
        // Core services
        $this->app->singleton(AgentRegistryService::class);
        $this->app->singleton(AgentDiscoveryService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(EscrowService::class);
        $this->app->singleton(MessagingService::class);
        $this->app->singleton(AgentWalletService::class);

        // Trust & Security services
        $this->app->singleton(ReputationService::class);
        $this->app->singleton(TrustService::class);
        $this->app->singleton(DigitalSignatureService::class);
        $this->app->singleton(EncryptionService::class);
    }

    /**
     * Register integration services.
     */
    private function registerIntegrationServices(): void
    {
        $this->app->singleton(AIIntegrationService::class);
        $this->app->singleton(ComplianceIntegrationService::class);
        $this->app->singleton(CoordinationIntegrationService::class);
        $this->app->singleton(WalletIntegrationService::class);
    }

    /**
     * Register event sourcing repositories.
     */
    private function registerEventSourcingRepositories(): void
    {
        $this->app->singleton(AgentEventRepository::class);
        $this->app->singleton(AgentSnapshotRepository::class);
    }

    /**
     * Register projectors.
     */
    private function registerProjectors(): void
    {
        // Register all Agent Protocol projectors
        Projectionist::addProjector(\App\Domain\AgentProtocol\Projectors\AgentProjector::class);
        Projectionist::addProjector(\App\Domain\AgentProtocol\Projectors\PaymentProjector::class);
        Projectionist::addProjector(\App\Domain\AgentProtocol\Projectors\EscrowProjector::class);
        Projectionist::addProjector(\App\Domain\AgentProtocol\Projectors\MessageProjector::class);
        Projectionist::addProjector(\App\Domain\AgentProtocol\Projectors\ReputationProjector::class);
        Projectionist::addProjector(\App\Domain\AgentProtocol\Projectors\ComplianceProjector::class);
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        // Event listeners for domain events will be registered here
    }
}
