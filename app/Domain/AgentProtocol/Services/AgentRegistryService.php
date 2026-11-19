<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use App\Domain\AgentProtocol\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing the agent registry within the Agent Protocol.
 *
 * Handles agent registration, lookup, capability management, and status tracking.
 * Maintains a central registry of all agents participating in the protocol.
 *
 * Configuration from config/agent_protocol.php:
 * - registry.cache_ttl: Cache duration for registry lookups
 * - registry.info_cache_ttl: Cache duration for agent info
 * - registry.max_capabilities: Maximum capabilities per agent
 */
class AgentRegistryService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Register a new agent in the registry.
     *
     * `ownerUserId` is the id of the user who owns the agent (only that user,
     * or staff, may manage its API keys, sessions and A2A tasks). Leave it
     * out for platform agents, which only staff can manage.
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
            'agent_id'      => $agentData['agentId'],
            'did'           => $agentData['did'],
            'name'          => $agentData['name'],
            'type'          => $agentData['type'] ?? 'standard',
            'status'        => 'active',
            'network_id'    => $agentData['networkId'] ?? null,
            'organization'  => $agentData['organization'] ?? null,
            'owner_user_id' => isset($agentData['ownerUserId']) ? (int) $agentData['ownerUserId'] : null,
            'endpoints'     => $agentData['endpoints'] ?? [],
            'capabilities'  => $agentData['capabilities'] ?? [],
            'metadata'      => $agentData['metadata'] ?? [],
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

    /**
     * Discover agents with filtering options.
     */
    public function discoverAgents(
        ?string $capability = null,
        ?string $type = null,
        string $status = 'active',
        int $limit = 20
    ): array {
        $cacheKey = 'agent:discover:' . md5((string) json_encode([$capability, $type, $status, $limit]));

        return Cache::remember($cacheKey, 300, function () use ($capability, $type, $status, $limit) {
            $query = Agent::query()->where('status', $status);

            if ($capability) {
                $query->whereJsonContains('capabilities', $capability);
            }

            if ($type) {
                $query->where('type', $type);
            }

            return $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($agent) => [
                    'agent_id'     => $agent->agent_id,
                    'did'          => $agent->did,
                    'name'         => $agent->name,
                    'type'         => $agent->type,
                    'status'       => $agent->status,
                    'capabilities' => $agent->capabilities ?? [],
                    'endpoints'    => $agent->endpoints ?? [],
                    'created_at'   => $agent->created_at?->toIso8601String(),
                ])
                ->toArray();
        });
    }

    /**
     * Get agent by DID.
     */
    public function getAgentByDID(string $did): ?array
    {
        $cacheKey = "agent:did:{$did}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($did) {
            $agent = Agent::where('did', $did)
                ->where('status', 'active')
                ->first();

            if (! $agent) {
                return null;
            }

            return [
                'agent_id'         => $agent->agent_id,
                'did'              => $agent->did,
                'name'             => $agent->name,
                'type'             => $agent->type,
                'status'           => $agent->status,
                'organization'     => $agent->organization,
                'network_id'       => $agent->network_id,
                'capabilities'     => $agent->capabilities ?? [],
                'endpoints'        => $agent->endpoints ?? [],
                'metadata'         => $agent->metadata ?? [],
                'public_key'       => $agent->public_key,
                'created_at'       => $agent->created_at?->toIso8601String(),
                'last_activity_at' => $agent->last_activity_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Get agent payments.
     */
    public function getAgentPayments(
        string $did,
        ?string $status = null,
        string $type = 'all',
        int $limit = 20
    ): array {
        // In a real implementation, this would query the event store or transactions table
        // For now, return empty array - the actual data comes from aggregates
        return [];
    }

    /**
     * Get agent messages.
     */
    public function getAgentMessages(
        string $did,
        string $type = 'all',
        ?string $status = null,
        bool $unacknowledgedOnly = false,
        int $limit = 20
    ): array {
        // In a real implementation, this would query the event store or messages table
        // For now, return empty array - the actual data comes from aggregates
        return [];
    }

    /**
     * Get agent reputation history.
     */
    public function getAgentReputationHistory(string $agentId, int $limit = 20): array
    {
        // In a real implementation, this would query the event store for reputation events
        // For now, return empty array - the actual data comes from aggregates
        return [];
    }

    /**
     * Get reputation leaderboard.
     */
    public function getReputationLeaderboard(int $limit = 10, ?string $capability = null): array
    {
        $cacheKey = 'reputation:leaderboard:' . md5((string) json_encode([$limit, $capability]));

        return Cache::remember($cacheKey, 300, function () use ($limit, $capability) {
            $query = Agent::query()
                ->where('status', 'active')
                ->whereNotNull('reputation_score');

            if ($capability) {
                $query->whereJsonContains('capabilities', $capability);
            }

            return $query->orderBy('reputation_score', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($agent, $index) => [
                    'rank'             => $index + 1,
                    'agent_id'         => $agent->agent_id,
                    'did'              => $agent->did,
                    'name'             => $agent->name,
                    'reputation_score' => $agent->reputation_score ?? 50.0,
                    'trust_level'      => $this->calculateTrustLevel((float) ($agent->reputation_score ?? 50.0)),
                    'capabilities'     => $agent->capabilities ?? [],
                ])
                ->toArray();
        });
    }

    /**
     * Calculate trust level from score.
     */
    private function calculateTrustLevel(float $score): string
    {
        return match (true) {
            $score >= 80 => 'trusted',
            $score >= 60 => 'high',
            $score >= 40 => 'neutral',
            $score >= 20 => 'low',
            default      => 'untrusted',
        };
    }
}
