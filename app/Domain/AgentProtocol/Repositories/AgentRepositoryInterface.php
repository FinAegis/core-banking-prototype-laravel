<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use App\Domain\AgentProtocol\Models\Agent;
use Illuminate\Support\Collection;

interface AgentRepositoryInterface
{
    public function findByAgentId(string $agentId): ?Agent;

    public function findByDid(string $did): ?Agent;

    public function findActive(): Collection;

    public function findByNetwork(string $networkId): Collection;

    public function findByOrganization(string $organization): Collection;

    public function findWithCapability(string $capability): Collection;

    public function create(array $data): Agent;

    public function update(string $agentId, array $data): bool;

    public function delete(string $agentId): bool;
}
