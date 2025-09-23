<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Services\AgentDiscoveryService;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Workflow\Activity;

class RouteMessageActivity extends Activity
{
    private AgentRegistryService $registryService;

    private AgentDiscoveryService $discoveryService;

    public function __construct()
    {
        $this->registryService = app(AgentRegistryService::class);
        $this->discoveryService = app(AgentDiscoveryService::class);
    }

    public function execute(
        string $fromAgentId,
        string $toAgentId,
        string $messageType,
        array $payload
    ): array {
        // Check cache for routing information
        $cacheKey = "agent:routing:{$toAgentId}";
        $cachedRoute = Cache::get($cacheKey);

        if ($cachedRoute !== null) {
            return $this->enrichRoutingResult($cachedRoute, $fromAgentId, $toAgentId);
        }

        // Discover agent endpoint
        $agentInfo = $this->discoveryService->discoverAgent($toAgentId);

        if ($agentInfo === null) {
            throw new RuntimeException("Unable to discover agent: {$toAgentId}");
        }

        // Determine optimal routing path
        $routingPath = $this->determineRoutingPath($fromAgentId, $toAgentId, $agentInfo);

        // Select appropriate endpoint based on message type
        $endpoint = $this->selectEndpoint($agentInfo, $messageType, $payload);

        // Build routing result
        $result = [
            'endpoint'        => $endpoint,
            'path'            => $routingPath,
            'protocol'        => $agentInfo['protocol'] ?? 'A2A',
            'protocolVersion' => $agentInfo['protocolVersion'] ?? '1.0.0',
            'capabilities'    => $agentInfo['capabilities'] ?? [],
            'authentication'  => $this->getAuthenticationRequirements($agentInfo),
            'rateLimit'       => $this->getRateLimitInfo($agentInfo),
            'metadata'        => [
                'discoveredAt' => now()->toIso8601String(),
                'ttl'          => 300, // 5 minutes cache TTL
            ],
        ];

        // Cache the routing information
        Cache::put($cacheKey, $result, 300);

        return $this->enrichRoutingResult($result, $fromAgentId, $toAgentId);
    }

    private function determineRoutingPath(
        string $fromAgentId,
        string $toAgentId,
        array $agentInfo
    ): array {
        $path = [];

        // Direct routing if agents are in the same network
        if ($this->areAgentsInSameNetwork($fromAgentId, $toAgentId)) {
            $path[] = [
                'type'     => 'direct',
                'from'     => $fromAgentId,
                'to'       => $toAgentId,
                'distance' => 1,
            ];

            return $path;
        }

        // Check if relay is needed
        if ($agentInfo['requiresRelay'] ?? false) {
            $relayAgent = $this->findRelayAgent($fromAgentId, $toAgentId);
            if ($relayAgent !== null) {
                $path[] = [
                    'type'     => 'relay',
                    'from'     => $fromAgentId,
                    'to'       => $relayAgent,
                    'distance' => 1,
                ];
                $path[] = [
                    'type'     => 'relay',
                    'from'     => $relayAgent,
                    'to'       => $toAgentId,
                    'distance' => 2,
                ];
            }
        } else {
            // Standard routing
            $path[] = [
                'type'     => 'standard',
                'from'     => $fromAgentId,
                'to'       => $toAgentId,
                'distance' => 1,
                'protocol' => $agentInfo['protocol'] ?? 'A2A',
            ];
        }

        return $path;
    }

    private function selectEndpoint(array $agentInfo, string $messageType, array $payload): string
    {
        $endpoints = $agentInfo['endpoints'] ?? [];

        // Priority order for endpoint selection
        $endpointPriorities = [
            'request'      => ['api', 'webhook', 'websocket'],
            'response'     => ['webhook', 'api', 'websocket'],
            'event'        => ['webhook', 'websocket', 'api'],
            'notification' => ['webhook', 'websocket', 'api'],
            'direct'       => ['api', 'webhook', 'websocket'],
            'broadcast'    => ['websocket', 'webhook', 'api'],
        ];

        $priorities = $endpointPriorities[$messageType] ?? ['api', 'webhook', 'websocket'];

        foreach ($priorities as $type) {
            if (isset($endpoints[$type]) && ! empty($endpoints[$type])) {
                return $endpoints[$type];
            }
        }

        // Fallback to primary endpoint
        if (isset($endpoints['primary'])) {
            return $endpoints['primary'];
        }

        throw new RuntimeException('No suitable endpoint found for agent: ' . $agentInfo['agentId']);
    }

    private function getAuthenticationRequirements(array $agentInfo): array
    {
        return [
            'type'          => $agentInfo['authType'] ?? 'bearer',
            'required'      => $agentInfo['authRequired'] ?? true,
            'mechanisms'    => $agentInfo['authMechanisms'] ?? ['oauth2', 'api-key'],
            'tokenEndpoint' => $agentInfo['tokenEndpoint'] ?? null,
        ];
    }

    private function getRateLimitInfo(array $agentInfo): array
    {
        return [
            'enabled'           => $agentInfo['rateLimitEnabled'] ?? true,
            'requestsPerMinute' => $agentInfo['rateLimit'] ?? 60,
            'burstLimit'        => $agentInfo['burstLimit'] ?? 10,
            'retryAfter'        => $agentInfo['retryAfter'] ?? 60,
        ];
    }

    private function areAgentsInSameNetwork(string $fromAgentId, string $toAgentId): bool
    {
        // Check if agents belong to the same network/organization
        $fromNetwork = $this->registryService->getAgentNetwork($fromAgentId);
        $toNetwork = $this->registryService->getAgentNetwork($toAgentId);

        return $fromNetwork !== null && $fromNetwork === $toNetwork;
    }

    private function findRelayAgent(string $fromAgentId, string $toAgentId): ?string
    {
        // Find an intermediate agent that can relay messages
        $relayAgents = $this->registryService->findRelayAgents($fromAgentId, $toAgentId);

        if ($relayAgents->isEmpty()) {
            return null;
        }

        // Select relay with highest relay score (first in the collection)
        $firstRelay = $relayAgents->first();

        return $firstRelay ? $firstRelay->agent_id : null;
    }

    private function enrichRoutingResult(
        array $result,
        string $fromAgentId,
        string $toAgentId
    ): array {
        $result['fromAgentId'] = $fromAgentId;
        $result['toAgentId'] = $toAgentId;
        $result['routedAt'] = now()->toIso8601String();

        return $result;
    }
}
