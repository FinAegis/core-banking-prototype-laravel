<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Models\Agent;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AgentDiscoveryService
{
    private Client $httpClient;

    private AgentRegistryService $registryService;

    private const DISCOVERY_CACHE_TTL = 600; // 10 minutes

    private const WELL_KNOWN_PATH = '/.well-known/ap2-configuration';

    public function __construct(AgentRegistryService $registryService)
    {
        $this->registryService = $registryService;
        $this->httpClient = new Client([
            'timeout'         => 10,
            'connect_timeout' => 5,
            'http_errors'     => false,
        ]);
    }

    /**
     * Discover an agent by ID.
     */
    public function discoverAgent(string $agentId): ?array
    {
        $cacheKey = "agent:discovery:{$agentId}";

        return Cache::remember($cacheKey, self::DISCOVERY_CACHE_TTL, function () use ($agentId) {
            // First check local registry
            $agent = $this->registryService->getAgent($agentId);

            if ($agent !== null) {
                return $this->formatAgentInfo($agent);
            }

            // Try remote discovery
            return $this->discoverRemoteAgent($agentId);
        });
    }

    /**
     * Discover agents by capability.
     */
    public function discoverByCapability(string $capability, ?string $networkId = null): array
    {
        $cacheKey = "agent:discovery:capability:{$capability}:{$networkId}";

        return Cache::remember($cacheKey, self::DISCOVERY_CACHE_TTL, function () use ($capability, $networkId) {
            $agents = [];

            // Search local registry
            $localAgents = $this->registryService->searchByCapability($capability);
            foreach ($localAgents as $agent) {
                if ($networkId === null || $agent->network_id === $networkId) {
                    $agents[] = $this->formatAgentInfo($agent);
                }
            }

            // Search remote registries if needed
            if (count($agents) < 10) {
                $remoteAgents = $this->discoverRemoteCapability($capability, $networkId);
                $agents = array_merge($agents, $remoteAgents);
            }

            return $agents;
        });
    }

    /**
     * Discover AP2 configuration for an endpoint.
     */
    public function discoverAP2Configuration(string $endpoint): ?array
    {
        $cacheKey = 'ap2:config:' . md5($endpoint);

        return Cache::remember($cacheKey, self::DISCOVERY_CACHE_TTL, function () use ($endpoint) {
            try {
                $url = rtrim($endpoint, '/') . self::WELL_KNOWN_PATH;
                $response = $this->httpClient->get($url);

                if ($response->getStatusCode() === 200) {
                    $body = $response->getBody()->getContents();
                    $config = json_decode($body, true);

                    if ($this->validateAP2Configuration($config)) {
                        return $config;
                    }
                }
            } catch (Exception $e) {
                Log::warning('Failed to discover AP2 configuration', [
                    'endpoint' => $endpoint,
                    'error'    => $e->getMessage(),
                ]);
            }

            return null;
        });
    }

    /**
     * Refresh discovery cache for an agent.
     */
    public function refreshDiscovery(string $agentId): ?array
    {
        $cacheKey = "agent:discovery:{$agentId}";
        Cache::forget($cacheKey);

        return $this->discoverAgent($agentId);
    }

    /**
     * Discover remote agent.
     */
    private function discoverRemoteAgent(string $agentId): ?array
    {
        // Check known discovery servers
        $discoveryServers = config('agent_protocol.discovery_servers', []);

        foreach ($discoveryServers as $server) {
            try {
                $url = "{$server}/api/agents/{$agentId}";
                $response = $this->httpClient->get($url, [
                    'headers' => [
                        'Accept'     => 'application/json',
                        'X-Protocol' => 'A2A',
                    ],
                ]);

                if ($response->getStatusCode() === 200) {
                    $body = $response->getBody()->getContents();
                    $agentInfo = json_decode($body, true);

                    if ($agentInfo !== null) {
                        return $agentInfo;
                    }
                }
            } catch (Exception $e) {
                Log::debug('Remote discovery failed for server', [
                    'server'  => $server,
                    'agentId' => $agentId,
                    'error'   => $e->getMessage(),
                ]);
                continue;
            }
        }

        return null;
    }

    /**
     * Discover remote agents by capability.
     */
    private function discoverRemoteCapability(string $capability, ?string $networkId): array
    {
        $agents = [];
        $discoveryServers = config('agent_protocol.discovery_servers', []);

        foreach ($discoveryServers as $server) {
            try {
                $params = ['capability' => $capability];
                if ($networkId !== null) {
                    $params['network'] = $networkId;
                }

                $response = $this->httpClient->get("{$server}/api/agents/search", [
                    'query'   => $params,
                    'headers' => [
                        'Accept'     => 'application/json',
                        'X-Protocol' => 'A2A',
                    ],
                ]);

                if ($response->getStatusCode() === 200) {
                    $body = $response->getBody()->getContents();
                    $remoteAgents = json_decode($body, true);

                    if (is_array($remoteAgents)) {
                        $agents = array_merge($agents, $remoteAgents);
                    }
                }
            } catch (Exception $e) {
                Log::debug('Remote capability discovery failed', [
                    'server'     => $server,
                    'capability' => $capability,
                    'error'      => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $agents;
    }

    /**
     * Format agent information.
     */
    private function formatAgentInfo(Agent $agent): array
    {
        return [
            'agentId'         => $agent->agent_id,
            'did'             => $agent->did,
            'name'            => $agent->name,
            'type'            => $agent->type,
            'status'          => $agent->status,
            'networkId'       => $agent->network_id,
            'organization'    => $agent->organization,
            'endpoints'       => $agent->endpoints,
            'capabilities'    => $agent->capabilities,
            'protocol'        => 'A2A',
            'protocolVersion' => '1.0.0',
            'authType'        => $agent->metadata['authType'] ?? 'bearer',
            'authRequired'    => $agent->metadata['authRequired'] ?? true,
            'rateLimit'       => $agent->metadata['rateLimit'] ?? 60,
            'discoveredAt'    => now()->toIso8601String(),
        ];
    }

    /**
     * Validate AP2 configuration.
     */
    private function validateAP2Configuration(array $config): bool
    {
        $requiredFields = [
            'version',
            'capabilities',
            'endpoints',
            'authentication',
        ];

        foreach ($requiredFields as $field) {
            if (! isset($config[$field])) {
                return false;
            }
        }

        // Validate version format
        if (! preg_match('/^\d+\.\d+\.\d+$/', $config['version'])) {
            return false;
        }

        // Validate endpoints structure
        if (! is_array($config['endpoints']) || empty($config['endpoints'])) {
            return false;
        }

        return true;
    }
}
