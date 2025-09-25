<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Repositories;

use App\Domain\AgentProtocol\Models\Agent;
use Illuminate\Support\Collection;

class EloquentAgentRepository implements AgentRepositoryInterface
{
    public function findByAgentId(string $agentId): ?Agent
    {
        return Agent::where('agent_id', $agentId)->first();
    }

    public function findByDid(string $did): ?Agent
    {
        return Agent::where('did', $did)->first();
    }

    public function findActive(): Collection
    {
        return Agent::active()->get();
    }

    public function findByNetwork(string $networkId): Collection
    {
        return Agent::inNetwork($networkId)->get();
    }

    public function findByOrganization(string $organization): Collection
    {
        return Agent::inOrganization($organization)->get();
    }

    public function findWithCapability(string $capability): Collection
    {
        return Agent::withCapability($capability)->get();
    }

    public function create(array $data): Agent
    {
        return Agent::create($data);
    }

    public function update(string $agentId, array $data): bool
    {
        $agent = $this->findByAgentId($agentId);
        if (!$agent) {
            return false;
        }
        return $agent->update($data);
    }

    public function delete(string $agentId): bool
    {
        $agent = $this->findByAgentId($agentId);
        if (!$agent) {
            return false;
        }
        return $agent->delete();
    }
}
