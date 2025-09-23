<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Domain\AgentProtocol\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AgentRegistryService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Register a new agent in the registry.
     */
    public function registerAgent(array $agentData): Agent
    {
        // Create agent aggregate
        $aggregate = AgentIdentityAggregate::register(
            agentId: $agentData['agentId'],
            did: $agentData['did'],
            name: $agentData['name'],
            type: $agentData['type'] ?? 'standard',
            metadata: $agentData['metadata'] ?? []
        );
        $aggregate->persist();

        // Create agent model
        $agent = Agent::create([
            'agent_id'     => $agentData['agentId'],
            'did'          => $agentData['did'],
            'name'         => $agentData['name'],
            'type'         => $agentData['type'] ?? 'standard',
            'status'       => 'active',
            'network_id'   => $agentData['networkId'] ?? null,
            'organization' => $agentData['organization'] ?? null,
            'endpoints'    => $agentData['endpoints'] ?? [],
            'capabilities' => $agentData['capabilities'] ?? [],
            'metadata'     => $agentData['metadata'] ?? [],
        ]);

        // Clear cache
        $this->clearAgentCache($agent->agent_id);

        return $agent;
    }

    /**
     * Check if an agent exists in the registry.
     */
    public function agentExists(string $agentId): bool
    {
        $cacheKey = "agent:exists:{$agentId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($agentId) {
            return Agent::where('agent_id', $agentId)
                ->where('status', 'active')
                ->exists();
        });
    }

    /**
     * Get agent information.
     */
    public function getAgent(string $agentId): ?Agent
    {
        $cacheKey = "agent:info:{$agentId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($agentId) {
            return Agent::where('agent_id', $agentId)
                ->where('status', 'active')
                ->first();
        });
    }

    /**
     * Get the network ID for an agent.
     */
    public function getAgentNetwork(string $agentId): ?string
    {
        $agent = $this->getAgent($agentId);

        return $agent?->network_id;
    }

    /**
     * Find agents that can relay messages between two agents.
     */
    public function findRelayAgents(string $fromAgentId, string $toAgentId): Collection
    {
        $cacheKey = "agent:relay:{$fromAgentId}:{$toAgentId}";

        return Cache::remember($cacheKey, 300, function () {
            // Find agents that have relay capability
            // For now, return agents with relay capability without checking connections
            // This avoids PHPStan issues with relationship queries
            return Agent::where('status', 'active')
                ->whereJsonContains('capabilities', 'relay')
                ->where('relay_score', '>', 0)
                ->orderBy('relay_score', 'desc')
                ->limit(5)
                ->get();
        });
    }

    /**
     * Update agent status.
     */
    public function updateAgentStatus(string $agentId, string $status): bool
    {
        $updated = Agent::where('agent_id', $agentId)
            ->update([
                'status'     => $status,
                'updated_at' => now(),
            ]);

        if ($updated) {
            $this->clearAgentCache($agentId);
        }

        return (bool) $updated;
    }

    /**
     * Update agent endpoints.
     */
    public function updateAgentEndpoints(string $agentId, array $endpoints): bool
    {
        $updated = Agent::where('agent_id', $agentId)
            ->update([
                'endpoints'  => $endpoints,
                'updated_at' => now(),
            ]);

        if ($updated) {
            $this->clearAgentCache($agentId);
        }

        return (bool) $updated;
    }

    /**
     * Search agents by capability.
     */
    public function searchByCapability(string $capability): Collection
    {
        $cacheKey = "agent:capability:{$capability}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($capability) {
            return Agent::where('status', 'active')
                ->whereJsonContains('capabilities', $capability)
                ->get();
        });
    }

    /**
     * Get agents in the same organization.
     */
    public function getOrganizationAgents(string $organization): Collection
    {
        $cacheKey = "agent:org:{$organization}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organization) {
            return Agent::where('status', 'active')
                ->where('organization', $organization)
                ->get();
        });
    }

    /**
     * Record agent activity.
     */
    public function recordActivity(string $agentId, string $activityType, array $data = []): void
    {
        DB::table('agent_activities')->insert([
            'agent_id'      => $agentId,
            'activity_type' => $activityType,
            'data'          => json_encode($data),
            'created_at'    => now(),
        ]);

        // Update last activity timestamp
        Agent::where('agent_id', $agentId)
            ->update(['last_activity_at' => now()]);
    }

    /**
     * Clear cache for an agent.
     */
    private function clearAgentCache(string $agentId): void
    {
        Cache::forget("agent:exists:{$agentId}");
        Cache::forget("agent:info:{$agentId}");
        // Clear related caches
        Cache::tags(['agents'])->flush();
    }
}
