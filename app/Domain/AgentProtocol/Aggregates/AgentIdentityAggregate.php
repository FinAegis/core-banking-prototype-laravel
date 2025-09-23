<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\AgentRegistered;
use App\Domain\AgentProtocol\Events\AgentWalletCreated;
use App\Domain\AgentProtocol\Events\CapabilityAdvertised;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class AgentIdentityAggregate extends AggregateRoot
{
    protected string $agentId = '';

    protected string $did = '';

    protected string $name = '';

    protected string $type = '';

    protected array $capabilities = [];

    protected array $wallets = [];

    protected array $metadata = [];

    protected string $status = 'inactive';

    protected float $reputationScore = 0.0;

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    public static function register(
        string $agentId,
        string $did,
        string $name,
        string $type = 'autonomous',
        array $metadata = []
    ): self {
        $aggregate = static::retrieve($agentId);

        $aggregate->recordThat(new AgentRegistered(
            agentId: $agentId,
            did: $did,
            name: $name,
            type: $type,
            metadata: $metadata
        ));

        return $aggregate;
    }

    public function advertiseCapability(
        string $capabilityId,
        array $endpoints = [],
        array $parameters = [],
        array $requiredPermissions = [],
        array $supportedProtocols = ['AP2', 'A2A']
    ): self {
        $this->recordThat(new CapabilityAdvertised(
            capabilityId: $capabilityId,
            agentId: $this->agentId,
            endpoints: $endpoints,
            parameters: $parameters,
            requiredPermissions: $requiredPermissions,
            supportedProtocols: $supportedProtocols,
            advertisedAt: now()->toDateTimeString()
        ));

        return $this;
    }

    public function createWallet(
        string $walletId,
        string $currency = 'USD',
        float $initialBalance = 0.0,
        array $metadata = []
    ): self {
        $this->recordThat(new AgentWalletCreated(
            walletId: $walletId,
            agentId: $this->agentId,
            currency: $currency,
            initialBalance: $initialBalance,
            metadata: $metadata
        ));

        return $this;
    }

    protected function applyAgentRegistered(AgentRegistered $event): void
    {
        $this->agentId = $event->agentId;
        $this->did = $event->did;
        $this->name = $event->name;
        $this->type = $event->type;
        $this->metadata = $event->metadata;
        $this->status = 'active';
        $this->reputationScore = 50.0; // Start with neutral reputation
    }

    protected function applyCapabilityAdvertised(CapabilityAdvertised $event): void
    {
        $this->capabilities[$event->capabilityId] = [
            'endpoints'            => $event->endpoints,
            'parameters'           => $event->parameters,
            'required_permissions' => $event->requiredPermissions,
            'supported_protocols'  => $event->supportedProtocols,
            'advertised_at'        => $event->advertisedAt,
        ];
    }

    protected function applyAgentWalletCreated(AgentWalletCreated $event): void
    {
        $this->wallets[$event->walletId] = [
            'currency'   => $event->currency,
            'balance'    => $event->initialBalance,
            'metadata'   => $event->metadata,
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function getWallets(): array
    {
        return $this->wallets;
    }

    public function getReputationScore(): float
    {
        return $this->reputationScore;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasCapability(string $capability): bool
    {
        return isset($this->capabilities[$capability]);
    }

    public function hasWallet(string $walletId): bool
    {
        return isset($this->wallets[$walletId]);
    }
}
