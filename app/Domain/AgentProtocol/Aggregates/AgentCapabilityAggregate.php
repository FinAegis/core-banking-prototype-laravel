<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\CapabilityAdvertised;
use App\Domain\AgentProtocol\Events\CapabilityDeprecated;
use App\Domain\AgentProtocol\Events\CapabilityEnabled;
use App\Domain\AgentProtocol\Events\CapabilityRegistered;
use App\Domain\AgentProtocol\Events\CapabilityUpdated;
use App\Domain\AgentProtocol\Events\CapabilityVersionAdded;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class AgentCapabilityAggregate extends AggregateRoot
{
    private const STATUS_DRAFT = 'draft';

    private const STATUS_ACTIVE = 'active';

    private const STATUS_DEPRECATED = 'deprecated';

    private const STATUS_DISABLED = 'disabled';

    private string $capabilityId = '';

    private string $agentId = '';

    private string $name = '';

    private string $description = '';

    private string $status = self::STATUS_DRAFT;

    private array $capabilities = [];

    private array $versions = [];

    private string $currentVersion = '1.0.0';

    private array $metadata = [];

    private array $parameters = [];

    private array $endpoints = [];

    private array $requiredPermissions = [];

    private array $supportedProtocols = [];

    private ?string $category = null;

    private int $priority = 50;

    private bool $isPublic = true;

    private array $rateLimits = [];

    private array $dependencies = [];

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    public static function register(
        string $capabilityId,
        string $agentId,
        string $name,
        string $description,
        array $capabilities,
        string $version = '1.0.0',
        ?string $category = null,
        array $metadata = []
    ): self {
        if (empty($name)) {
            throw new InvalidArgumentException('Capability name is required');
        }

        if (empty($capabilities)) {
            throw new InvalidArgumentException('At least one capability must be provided');
        }

        $aggregate = static::retrieve($capabilityId);
        $aggregate->recordThat(new CapabilityRegistered(
            capabilityId: $capabilityId,
            agentId: $agentId,
            name: $name,
            description: $description,
            capabilities: $capabilities,
            version: $version,
            category: $category,
            metadata: $metadata
        ));

        return $aggregate;
    }

    public function advertise(
        array $endpoints,
        array $parameters = [],
        array $requiredPermissions = [],
        array $supportedProtocols = ['AP2', 'A2A']
    ): self {
        if ($this->status === self::STATUS_DEPRECATED) {
            throw new InvalidArgumentException('Cannot advertise deprecated capability');
        }

        if (empty($endpoints)) {
            throw new InvalidArgumentException('At least one endpoint must be provided');
        }

        $this->recordThat(new CapabilityAdvertised(
            capabilityId: $this->capabilityId,
            agentId: $this->agentId,
            endpoints: $endpoints,
            parameters: $parameters,
            requiredPermissions: $requiredPermissions,
            supportedProtocols: $supportedProtocols,
            advertisedAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function addVersion(
        string $version,
        array $changes,
        bool $isBackwardCompatible = true,
        ?string $migrationPath = null
    ): self {
        if (in_array($version, array_keys($this->versions), true)) {
            throw new InvalidArgumentException("Version {$version} already exists");
        }

        if (! $this->isValidVersion($version)) {
            throw new InvalidArgumentException("Invalid version format: {$version}");
        }

        $this->recordThat(new CapabilityVersionAdded(
            capabilityId: $this->capabilityId,
            version: $version,
            previousVersion: $this->currentVersion,
            changes: $changes,
            isBackwardCompatible: $isBackwardCompatible,
            migrationPath: $migrationPath,
            addedAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function update(
        ?string $description = null,
        ?array $capabilities = null,
        ?array $endpoints = null,
        ?array $parameters = null,
        ?array $metadata = null
    ): self {
        if ($this->status === self::STATUS_DEPRECATED) {
            throw new InvalidArgumentException('Cannot update deprecated capability');
        }

        $updates = array_filter(
            [
                'description'  => $description,
                'capabilities' => $capabilities,
                'endpoints'    => $endpoints,
                'parameters'   => $parameters,
                'metadata'     => $metadata,
            ],
            fn ($value) => $value !== null
        );

            if (empty($updates)) {
                throw new InvalidArgumentException('No updates provided');
            }

            $this->recordThat(new CapabilityUpdated(
                capabilityId: $this->capabilityId,
                updates: $updates,
                updatedBy: $this->agentId,
                updatedAt: now()->toIso8601String()
            ));

        return $this;
    }

    public function enable(string $enabledBy, string $reason = ''): self
    {
        if ($this->status === self::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Capability is already active');
        }

        if ($this->status === self::STATUS_DEPRECATED) {
            throw new InvalidArgumentException('Cannot enable deprecated capability');
        }

        $this->recordThat(new CapabilityEnabled(
            capabilityId: $this->capabilityId,
            enabledBy: $enabledBy,
            reason: $reason,
            enabledAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function deprecate(
        string $deprecatedBy,
        string $reason,
        ?string $replacementCapabilityId = null,
        ?string $sunsetDate = null
    ): self {
        if ($this->status === self::STATUS_DEPRECATED) {
            throw new InvalidArgumentException('Capability is already deprecated');
        }

        $this->recordThat(new CapabilityDeprecated(
            capabilityId: $this->capabilityId,
            deprecatedBy: $deprecatedBy,
            reason: $reason,
            replacementCapabilityId: $replacementCapabilityId,
            sunsetDate: $sunsetDate,
            deprecatedAt: now()->toIso8601String()
        ));

        return $this;
    }

    public function setRateLimits(array $rateLimits): self
    {
        foreach ($rateLimits as $limit) {
            if (! isset($limit['requests'], $limit['period'])) {
                throw new InvalidArgumentException('Rate limits must include requests and period');
            }
        }

        $this->rateLimits = $rateLimits;

        return $this;
    }

    public function setDependencies(array $dependencies): self
    {
        $this->dependencies = $dependencies;

        return $this;
    }

    public function setPriority(int $priority): self
    {
        if ($priority < 0 || $priority > 100) {
            throw new InvalidArgumentException('Priority must be between 0 and 100');
        }

        $this->priority = $priority;

        return $this;
    }

    public function setVisibility(bool $isPublic): self
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    protected function applyCapabilityRegistered(CapabilityRegistered $event): void
    {
        $this->capabilityId = $event->capabilityId;
        $this->agentId = $event->agentId;
        $this->name = $event->name;
        $this->description = $event->description;
        $this->capabilities = $event->capabilities;
        $this->currentVersion = $event->version;
        $this->versions[$event->version] = [
            'registeredAt' => now()->toIso8601String(),
            'capabilities' => $event->capabilities,
        ];
        $this->category = $event->category;
        $this->metadata = $event->metadata;
        $this->status = self::STATUS_DRAFT;
    }

    protected function applyCapabilityAdvertised(CapabilityAdvertised $event): void
    {
        $this->endpoints = $event->endpoints;
        $this->parameters = $event->parameters;
        $this->requiredPermissions = $event->requiredPermissions;
        $this->supportedProtocols = $event->supportedProtocols;
        $this->status = self::STATUS_ACTIVE;
        $this->metadata['advertisedAt'] = $event->advertisedAt;
    }

    protected function applyCapabilityVersionAdded(CapabilityVersionAdded $event): void
    {
        $this->versions[$event->version] = [
            'previousVersion'      => $event->previousVersion,
            'changes'              => $event->changes,
            'isBackwardCompatible' => $event->isBackwardCompatible,
            'migrationPath'        => $event->migrationPath,
            'addedAt'              => $event->addedAt,
        ];
        $this->currentVersion = $event->version;
    }

    protected function applyCapabilityUpdated(CapabilityUpdated $event): void
    {
        foreach ($event->updates as $key => $value) {
            match ($key) {
                'description'  => $this->description = $value,
                'capabilities' => $this->capabilities = $value,
                'endpoints'    => $this->endpoints = $value,
                'parameters'   => $this->parameters = $value,
                'metadata'     => $this->metadata = array_merge($this->metadata, $value),
                default        => null
            };
        }
        $this->metadata['lastUpdatedAt'] = $event->updatedAt;
        $this->metadata['lastUpdatedBy'] = $event->updatedBy;
    }

    protected function applyCapabilityEnabled(CapabilityEnabled $event): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->metadata['enabledBy'] = $event->enabledBy;
        $this->metadata['enabledAt'] = $event->enabledAt;
        $this->metadata['enableReason'] = $event->reason;
    }

    protected function applyCapabilityDeprecated(CapabilityDeprecated $event): void
    {
        $this->status = self::STATUS_DEPRECATED;
        $this->metadata['deprecatedBy'] = $event->deprecatedBy;
        $this->metadata['deprecatedAt'] = $event->deprecatedAt;
        $this->metadata['deprecationReason'] = $event->reason;
        $this->metadata['replacementCapabilityId'] = $event->replacementCapabilityId;
        $this->metadata['sunsetDate'] = $event->sunsetDate;
    }

    private function isValidVersion(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+(-[a-zA-Z0-9]+)?$/', $version);
    }

    public function getCapabilityId(): string
    {
        return $this->capabilityId;
    }

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public static function getAvailableStatuses(): array
    {
        return [
            'draft'      => self::STATUS_DRAFT,
            'active'     => self::STATUS_ACTIVE,
            'deprecated' => self::STATUS_DEPRECATED,
            'disabled'   => self::STATUS_DISABLED,
        ];
    }

    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    public function getVersions(): array
    {
        return $this->versions;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getRequiredPermissions(): array
    {
        return $this->requiredPermissions;
    }

    public function getSupportedProtocols(): array
    {
        return $this->supportedProtocols;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function getRateLimits(): array
    {
        return $this->rateLimits;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function canHandleProtocol(string $protocol): bool
    {
        return in_array($protocol, $this->supportedProtocols, true);
    }

    public function isCompatibleWithVersion(string $version): bool
    {
        if (! isset($this->versions[$version])) {
            return false;
        }

        $versionData = $this->versions[$version];

        return $versionData['isBackwardCompatible'] ?? false;
    }
}
