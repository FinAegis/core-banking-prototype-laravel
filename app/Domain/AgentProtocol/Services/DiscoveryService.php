<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\AgentIdentityAggregate;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DiscoveryService
{
    private const DISCOVERY_CACHE_TTL = 300; // 5 minutes

    private const AP2_VERSION = '1.0.0';

    public function __construct(
        private readonly DIDService $didService
    ) {
    }

    public function getAP2Configuration(): array
    {
        return Cache::remember('ap2:configuration', self::DISCOVERY_CACHE_TTL, function () {
            return [
                'version'                => self::AP2_VERSION,
                'issuer'                 => config('app.url'),
                'discovery_endpoint'     => config('app.url') . '/api/agents/discover',
                'registration_endpoint'  => config('app.url') . '/api/agents/register',
                'payment_endpoint'       => config('app.url') . '/api/agents/{did}/payments',
                'escrow_endpoint'        => config('app.url') . '/api/agents/escrow',
                'capabilities_endpoint'  => config('app.url') . '/api/agents/{did}/capabilities',
                'reputation_endpoint'    => config('app.url') . '/api/agents/{did}/reputation',
                'supported_capabilities' => [
                    'payment.transfer',
                    'payment.escrow',
                    'messaging.a2a',
                    'discovery.search',
                    'reputation.scoring',
                    'wallet.management',
                ],
                'supported_payment_methods' => [
                    'direct_transfer',
                    'escrow',
                    'split_payment',
                    'subscription',
                ],
                'supported_currencies' => [
                    'USD',
                    'EUR',
                    'GBP',
                    'USDC',
                    'USDT',
                ],
                'authentication_methods' => [
                    'oauth2',
                    'api_key',
                    'did_auth',
                ],
                'rate_limits' => [
                    'discovery'    => '100/hour',
                    'registration' => '10/hour',
                    'payment'      => '1000/hour',
                    'messaging'    => '5000/hour',
                ],
                'compliance' => [
                    'kyc_required'       => true,
                    'aml_screening'      => true,
                    'transaction_limits' => [
                        'daily'           => 100000,
                        'per_transaction' => 10000,
                    ],
                ],
                'metadata' => [
                    'environment'  => config('app.env'),
                    'region'       => 'global',
                    'last_updated' => now()->toIso8601String(),
                ],
            ];
        });
    }

    public function discoverAgents(array $filters = []): Collection
    {
        $jsonString = json_encode($filters);
        if ($jsonString === false) {
            $jsonString = '';
        }
        $cacheKey = 'agents:discover:' . md5($jsonString);

        return Cache::remember($cacheKey, self::DISCOVERY_CACHE_TTL, function () use ($filters) {
            // In production, this would query a registry or database
            // For now, we'll simulate with some example data

            $agents = collect();

            // Simulate agent discovery
            // In reality, we'd query the event store or a projection

            if (isset($filters['capability'])) {
                // Filter by capability
                $agents = $agents->filter(function ($agent) use ($filters) {
                    $aggregate = AgentIdentityAggregate::retrieve($agent['agent_id']);

                    return $aggregate->hasCapability($filters['capability']);
                });
            }

            if (isset($filters['type'])) {
                // Filter by agent type
                $agents = $agents->where('type', $filters['type']);
            }

            if (isset($filters['reputation_min'])) {
                // Filter by minimum reputation score
                $agents = $agents->where('reputation_score', '>=', $filters['reputation_min']);
            }

            return $agents->map(function ($agent) {
                return $this->formatAgentForDiscovery($agent);
            });
        });
    }

    public function searchAgentByDID(string $did): ?array
    {
        if (! $this->didService->validateDID($did)) {
            return null;
        }

        $cacheKey = 'agent:did:' . $did;

        return Cache::remember($cacheKey, self::DISCOVERY_CACHE_TTL, function () use ($did) {
            $agentId = $this->didService->extractAgentIdFromDID($did);
            if (! $agentId) {
                return null;
            }

            try {
                $aggregate = AgentIdentityAggregate::retrieve($agentId);
                if (! $aggregate->isActive()) {
                    return null;
                }

                return $this->formatAgentDetails($aggregate);
            } catch (Exception $e) {
                return null;
            }
        });
    }

    public function registerCapability(
        string $agentId,
        string $capability,
        string $version = '1.0.0',
        array $parameters = []
    ): bool {
        try {
            $aggregate = AgentIdentityAggregate::retrieve($agentId);
            $aggregate->advertiseCapability($capability, $version, $parameters);
            $aggregate->persist();

            // Invalidate discovery cache
            $this->invalidateDiscoveryCache();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getAgentCapabilities(string $agentId): array
    {
        $cacheKey = 'agent:capabilities:' . $agentId;

        return Cache::remember($cacheKey, self::DISCOVERY_CACHE_TTL, function () use ($agentId) {
            try {
                $aggregate = AgentIdentityAggregate::retrieve($agentId);

                return $aggregate->getCapabilities();
            } catch (Exception $e) {
                return [];
            }
        });
    }

    public function matchCapabilities(string $requiredCapability, array $parameters = []): Collection
    {
        return $this->discoverAgents(['capability' => $requiredCapability])
            ->filter(function ($agent) use ($parameters, $requiredCapability) {
                if (empty($parameters)) {
                    return true;
                }

                $agentCapabilities = $agent['capabilities'] ?? [];
                $capability = $agentCapabilities[$requiredCapability] ?? null;

                if (! $capability) {
                    return false;
                }

                // Match parameters
                foreach ($parameters as $key => $value) {
                    if (
                        ! isset($capability['parameters'][$key]) ||
                        $capability['parameters'][$key] !== $value
                    ) {
                        return false;
                    }
                }

                return true;
            });
    }

    private function formatAgentForDiscovery(array $agent): array
    {
        return [
            'did'              => $agent['did'] ?? '',
            'name'             => $agent['name'] ?? '',
            'type'             => $agent['type'] ?? 'autonomous',
            'capabilities'     => $agent['capabilities'] ?? [],
            'reputation_score' => $agent['reputation_score'] ?? 0.0,
            'status'           => $agent['status'] ?? 'active',
            'service_endpoint' => config('app.url') . '/api/agents/' . ($agent['did'] ?? ''),
            'metadata'         => [
                'discovered_at' => now()->toIso8601String(),
                'ttl'           => self::DISCOVERY_CACHE_TTL,
            ],
        ];
    }

    private function formatAgentDetails(AgentIdentityAggregate $aggregate): array
    {
        return [
            'did'               => $aggregate->getDid(),
            'name'              => $aggregate->getName(),
            'type'              => $aggregate->getType(),
            'capabilities'      => $aggregate->getCapabilities(),
            'wallets'           => array_keys($aggregate->getWallets()),
            'reputation_score'  => $aggregate->getReputationScore(),
            'status'            => $aggregate->isActive() ? 'active' : 'inactive',
            'did_document'      => $this->didService->resolveDID($aggregate->getDid()),
            'service_endpoints' => [
                'payments'     => config('app.url') . '/api/agents/' . $aggregate->getDid() . '/payments',
                'messages'     => config('app.url') . '/api/agents/' . $aggregate->getDid() . '/messages',
                'capabilities' => config('app.url') . '/api/agents/' . $aggregate->getDid() . '/capabilities',
                'reputation'   => config('app.url') . '/api/agents/' . $aggregate->getDid() . '/reputation',
            ],
            'metadata' => [
                'retrieved_at' => now()->toIso8601String(),
                'ttl'          => self::DISCOVERY_CACHE_TTL,
            ],
        ];
    }

    private function invalidateDiscoveryCache(): void
    {
        // Clear discovery-related caches
        Cache::forget('ap2:configuration');
        Cache::flush(); // In production, use tags for selective clearing
    }

    public function getServiceEndpoint(string $did, string $service): ?string
    {
        $agent = $this->searchAgentByDID($did);
        if (! $agent) {
            return null;
        }

        return $agent['service_endpoints'][$service] ?? null;
    }
}
