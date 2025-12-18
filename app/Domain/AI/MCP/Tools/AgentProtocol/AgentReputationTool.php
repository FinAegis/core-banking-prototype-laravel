<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\AgentProtocol;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\Services\AIAgentProtocolBridgeService;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tool for AI Agent Reputation Management.
 *
 * Enables AI agents to query reputation scores, check trust levels,
 * and evaluate trust relationships between agents for informed decisions.
 */
class AgentReputationTool implements MCPToolInterface
{
    public function __construct(
        private readonly AIAgentProtocolBridgeService $bridgeService
    ) {
    }

    public function getName(): string
    {
        return 'agent_protocol.reputation';
    }

    public function getCategory(): string
    {
        return 'agent_protocol';
    }

    public function getDescription(): string
    {
        return 'Query and evaluate AI agent reputation scores and trust relationships. Supports reputation lookup, threshold checking, and bilateral trust calculations.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'description' => 'The reputation action to perform',
                    'enum'        => ['get_reputation', 'check_threshold', 'calculate_trust', 'discover_agents'],
                ],
                'agent_name' => [
                    'type'        => 'string',
                    'description' => 'The name/identifier of the AI agent to query',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
                'other_agent_name' => [
                    'type'        => 'string',
                    'description' => 'The name/identifier of another agent (for calculate_trust)',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
                'threshold_type' => [
                    'type'        => 'string',
                    'description' => 'The reputation threshold type to check',
                    'enum'        => ['basic', 'standard', 'premium', 'enterprise'],
                    'default'     => 'standard',
                ],
                'capabilities' => [
                    'type'        => 'array',
                    'description' => 'Required capabilities for discover_agents action',
                    'items'       => ['type' => 'string'],
                    'examples'    => [['automated_payments', 'escrow_transactions']],
                ],
                'min_reputation' => [
                    'type'        => 'number',
                    'description' => 'Minimum reputation score for discover_agents',
                    'minimum'     => 0,
                    'maximum'     => 100,
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action'            => ['type' => 'string', 'description' => 'Action performed'],
                'agent_name'        => ['type' => 'string', 'description' => 'Agent name queried'],
                'score'             => ['type' => 'number', 'description' => 'Reputation score (0-100)'],
                'level'             => ['type' => 'string', 'description' => 'Reputation level'],
                'transaction_count' => ['type' => 'integer', 'description' => 'Total transactions'],
                'meets_threshold'   => ['type' => 'boolean', 'description' => 'Whether threshold is met'],
                'trust_score'       => ['type' => 'number', 'description' => 'Bilateral trust score (0-1)'],
                'recommendation'    => ['type' => 'string', 'description' => 'Trust recommendation'],
                'agents'            => ['type' => 'array', 'description' => 'Discovered agents'],
                'queried_at'        => ['type' => 'string', 'description' => 'Query timestamp'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $action = $parameters['action'];

            Log::info('MCP Tool: Processing reputation action', [
                'action'          => $action,
                'conversation_id' => $conversationId,
            ]);

            return match ($action) {
                'get_reputation'  => $this->getReputation($parameters),
                'check_threshold' => $this->checkThreshold($parameters),
                'calculate_trust' => $this->calculateTrust($parameters),
                'discover_agents' => $this->discoverAgents($parameters),
                default           => ToolExecutionResult::failure("Unknown action: {$action}"),
            };
        } catch (Exception $e) {
            Log::error('MCP Tool error: agent_protocol.reputation', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
                'trace'      => $e->getTraceAsString(),
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function getReputation(array $parameters): ToolExecutionResult
    {
        $agentName = $parameters['agent_name'] ?? null;

        if (! $agentName) {
            return ToolExecutionResult::failure('agent_name is required for get_reputation action');
        }

        Log::info('MCP Tool: Getting agent reputation', [
            'agent_name' => $agentName,
        ]);

        $result = $this->bridgeService->getAIAgentReputation($agentName);

        return ToolExecutionResult::success([
            'action'            => 'get_reputation',
            'agent_name'        => $agentName,
            'score'             => $result['score'],
            'level'             => $result['level'],
            'transaction_count' => $result['transaction_count'],
            'queried_at'        => now()->toIso8601String(),
        ]);
    }

    private function checkThreshold(array $parameters): ToolExecutionResult
    {
        $agentName = $parameters['agent_name'] ?? null;
        $thresholdType = $parameters['threshold_type'] ?? 'standard';

        if (! $agentName) {
            return ToolExecutionResult::failure('agent_name is required for check_threshold action');
        }

        Log::info('MCP Tool: Checking reputation threshold', [
            'agent_name'     => $agentName,
            'threshold_type' => $thresholdType,
        ]);

        $meetsThreshold = $this->bridgeService->meetsReputationThreshold($agentName, $thresholdType);
        $reputation = $this->bridgeService->getAIAgentReputation($agentName);

        return ToolExecutionResult::success([
            'action'          => 'check_threshold',
            'agent_name'      => $agentName,
            'threshold_type'  => $thresholdType,
            'meets_threshold' => $meetsThreshold,
            'current_score'   => $reputation['score'],
            'current_level'   => $reputation['level'],
            'queried_at'      => now()->toIso8601String(),
        ]);
    }

    private function calculateTrust(array $parameters): ToolExecutionResult
    {
        $agentName = $parameters['agent_name'] ?? null;
        $otherAgentName = $parameters['other_agent_name'] ?? null;

        if (! $agentName || ! $otherAgentName) {
            return ToolExecutionResult::failure('agent_name and other_agent_name are required for calculate_trust action');
        }

        Log::info('MCP Tool: Calculating trust between agents', [
            'agent1' => $agentName,
            'agent2' => $otherAgentName,
        ]);

        // Register agents if not already registered
        $this->bridgeService->registerAIAgent($agentName);
        $this->bridgeService->registerAIAgent($otherAgentName);

        $result = $this->bridgeService->calculateTrustBetweenAIAgents($agentName, $otherAgentName);

        return ToolExecutionResult::success([
            'action'         => 'calculate_trust',
            'agent_name'     => $agentName,
            'other_agent'    => $otherAgentName,
            'trust_score'    => $result['trust_score'],
            'recommendation' => $result['recommendation'],
            'queried_at'     => now()->toIso8601String(),
        ]);
    }

    private function discoverAgents(array $parameters): ToolExecutionResult
    {
        $capabilities = $parameters['capabilities'] ?? [];
        $minReputation = $parameters['min_reputation'] ?? 0;

        Log::info('MCP Tool: Discovering agents', [
            'capabilities'   => $capabilities,
            'min_reputation' => $minReputation,
        ]);

        $agents = $this->bridgeService->discoverAIAgents($capabilities);

        // Filter by minimum reputation if specified
        if ($minReputation > 0) {
            $agents = $agents->filter(function ($agent) use ($minReputation) {
                $reputation = $this->bridgeService->getAIAgentReputation($agent['name'] ?? $agent['did']);

                return $reputation['score'] >= $minReputation;
            });
        }

        return ToolExecutionResult::success([
            'action'       => 'discover_agents',
            'capabilities' => $capabilities,
            'agents'       => $agents->values()->toArray(),
            'count'        => $agents->count(),
            'queried_at'   => now()->toIso8601String(),
        ]);
    }

    public function getCapabilities(): array
    {
        return [
            'read',
            'reputation-query',
            'trust-calculation',
            'agent-discovery',
            'threshold-check',
        ];
    }

    public function isCacheable(): bool
    {
        return true; // Reputation data can be cached briefly
    }

    public function getCacheTtl(): int
    {
        return 300; // 5 minutes cache
    }

    public function validateInput(array $parameters): bool
    {
        // Validate action is provided
        if (empty($parameters['action'])) {
            return false;
        }

        $action = $parameters['action'];

        // Validate based on action type
        if (in_array($action, ['get_reputation', 'check_threshold']) && empty($parameters['agent_name'])) {
            return false;
        }

        if ($action === 'calculate_trust') {
            if (empty($parameters['agent_name']) || empty($parameters['other_agent_name'])) {
                return false;
            }
            if ($parameters['agent_name'] === $parameters['other_agent_name']) {
                return false; // Cannot calculate trust with self
            }
        }

        // Validate threshold type if provided
        if (isset($parameters['threshold_type'])) {
            $validThresholds = ['basic', 'standard', 'premium', 'enterprise'];
            if (! in_array($parameters['threshold_type'], $validThresholds)) {
                return false;
            }
        }

        // Validate min_reputation range if provided
        if (isset($parameters['min_reputation'])) {
            if ($parameters['min_reputation'] < 0 || $parameters['min_reputation'] > 100) {
                return false;
            }
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // Reputation queries are generally read-only and can be accessed freely
        return true;
    }
}
