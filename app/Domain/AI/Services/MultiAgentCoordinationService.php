<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Workflows\ComplianceWorkflow;
use App\Domain\AI\Workflows\CustomerServiceWorkflow;
use App\Domain\AI\Workflows\RiskAssessmentSaga;
use App\Domain\AI\Workflows\TradingAgentWorkflow;
use App\Models\User;

/**
 * Multi-Agent Coordination Service.
 *
 * Orchestrates communication and coordination between multiple AI agents,
 * manages task delegation, consensus mechanisms, and conflict resolution.
 *
 * Features:
 * - Agent communication protocol
 * - Task delegation and assignment
 * - Consensus building for multi-agent decisions
 * - Conflict resolution mechanisms
 * - Agent capability discovery
 * - Load balancing across agents
 */
class MultiAgentCoordinationService
{
    /**
     * @var array<string, array{class: class-string, capabilities: array<string>, priority: int}>
     */
    private array $registeredAgents = [];

    /**
     * @var array<string, array{agent: string, status: string, started_at: string}>
     */
    private array $activeTasks = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $agentCommunications = [];

    /**
     * @var array<string, float>
     */
    private array $agentLoadMetrics = [];

    public function __construct()
    {
        $this->registerDefaultAgents();
    }

    /**
     * Register default AI agents.
     */
    private function registerDefaultAgents(): void
    {
        $this->registeredAgents = [
            'customer_service' => [
                'class'        => CustomerServiceWorkflow::class,
                'capabilities' => ['query_handling', 'account_operations', 'basic_support'],
                'priority'     => 1,
            ],
            'compliance' => [
                'class'        => ComplianceWorkflow::class,
                'capabilities' => ['kyc_verification', 'aml_screening', 'regulatory_reporting'],
                'priority'     => 2,
            ],
            'risk_assessment' => [
                'class'        => RiskAssessmentSaga::class,
                'capabilities' => ['credit_scoring', 'fraud_detection', 'portfolio_risk'],
                'priority'     => 2,
            ],
            'trading' => [
                'class'        => TradingAgentWorkflow::class,
                'capabilities' => ['market_analysis', 'trading_strategy', 'portfolio_optimization'],
                'priority'     => 1,
            ],
        ];

        // Initialize load metrics
        foreach (array_keys($this->registeredAgents) as $agent) {
            $this->agentLoadMetrics[$agent] = 0.0;
        }
    }

    /**
     * Coordinate task execution across multiple agents.
     *
     * @param string $taskId Unique task identifier
     * @param string $taskType Type of task to execute
     * @param array<string, mixed> $parameters Task parameters
     * @param User $user User context
     *
     * @return array<string, mixed> Coordination result
     */
    public function coordinateTask(
        string $taskId,
        string $taskType,
        array $parameters,
        User $user
    ): array {
        // Discover capable agents
        $capableAgents = $this->discoverCapableAgents($taskType);

        if (empty($capableAgents)) {
            return $this->handleNoCapableAgents($taskId, $taskType);
        }

        // Check if task requires multiple agents
        if ($this->requiresMultipleAgents($taskType, $parameters)) {
            return $this->coordinateMultiAgentTask(
                $taskId,
                $taskType,
                $parameters,
                $user,
                $capableAgents
            );
        }

        // Single agent task - delegate to best available
        $selectedAgent = $this->selectBestAgent($capableAgents, $taskType);

        return $this->delegateToAgent($taskId, $selectedAgent, $taskType, $parameters, $user);
    }

    /**
     * Coordinate multi-agent task execution.
     */
    private function coordinateMultiAgentTask(
        string $taskId,
        string $taskType,
        array $parameters,
        User $user,
        array $capableAgents
    ): array {
        $subTasks = $this->decomposeTask($taskType, $parameters);
        $results = [];
        $communications = [];

        foreach ($subTasks as $subTask) {
            $agent = $this->selectAgentForSubTask($capableAgents, $subTask);

            // Execute sub-task
            $result = $this->delegateToAgent(
                "{$taskId}_{$subTask['id']}",
                $agent,
                $subTask['type'],
                $subTask['parameters'],
                $user
            );

            $results[$subTask['id']] = $result;

            // Enable inter-agent communication
            if ($subTask['requires_communication']) {
                $communications[] = $this->facilitateCommunication(
                    $agent,
                    $subTask['communicate_with'],
                    $result
                );
            }
        }

        // Build consensus if needed
        if ($this->requiresConsensus($taskType)) {
            return $this->buildConsensus($taskId, $results, $communications);
        }

        return $this->aggregateResults($taskId, $results, $communications);
    }

    /**
     * Delegate task to specific agent.
     */
    private function delegateToAgent(
        string $taskId,
        string $agentName,
        string $taskType,
        array $parameters,
        User $user
    ): array {
        // Record task assignment
        $this->activeTasks[$taskId] = [
            'agent'      => $agentName,
            'status'     => 'executing',
            'started_at' => now()->toIso8601String(),
        ];

        // Update agent load
        $this->updateAgentLoad($agentName, 0.2);

        try {
            // Execute through appropriate workflow
            $result = $this->executeAgentWorkflow($agentName, $taskType, $parameters, $user);

            // Update task status
            $this->activeTasks[$taskId]['status'] = 'completed';
            $this->activeTasks[$taskId]['completed_at'] = now()->toIso8601String();

            // Decrease agent load
            $this->updateAgentLoad($agentName, -0.2);

            return [
                'success'    => true,
                'agent'      => $agentName,
                'result'     => $result,
                'task_id'    => $taskId,
                'confidence' => $result['confidence'] ?? 0.8,
            ];
        } catch (\Exception $e) {
            // Handle failure
            $this->activeTasks[$taskId]['status'] = 'failed';
            $this->activeTasks[$taskId]['error'] = $e->getMessage();
            $this->updateAgentLoad($agentName, -0.2);

            return [
                'success' => false,
                'agent'   => $agentName,
                'error'   => $e->getMessage(),
                'task_id' => $taskId,
            ];
        }
    }

    /**
     * Execute agent workflow.
     */
    private function executeAgentWorkflow(
        string $agentName,
        string $taskType,
        array $parameters,
        User $user
    ): array {
        // Simplified execution for demo
        switch ($agentName) {
            case 'customer_service':
                return [
                    'response'   => 'Customer query handled successfully',
                    'confidence' => 0.85,
                    'actions'    => ['balance_checked', 'information_provided'],
                ];

            case 'compliance':
                return [
                    'verification_status' => 'approved',
                    'risk_score'          => 25,
                    'confidence'          => 0.90,
                    'checks_performed'    => ['kyc', 'aml', 'sanctions'],
                ];

            case 'risk_assessment':
                return [
                    'risk_level' => 'moderate',
                    'risk_score' => 45,
                    'confidence' => 0.78,
                    'factors'    => ['credit', 'market', 'operational'],
                ];

            case 'trading':
                return [
                    'strategy'       => 'momentum',
                    'recommendation' => 'buy',
                    'confidence'     => 0.72,
                    'assets'         => ['BTC', 'ETH'],
                ];

            default:
                throw new \Exception("Unknown agent: {$agentName}");
        }
    }

    /**
     * Discover agents capable of handling task type.
     */
    private function discoverCapableAgents(string $taskType): array
    {
        $capable = [];

        foreach ($this->registeredAgents as $name => $agent) {
            if ($this->agentCanHandle($agent, $taskType)) {
                $capable[] = $name;
            }
        }

        return $capable;
    }

    /**
     * Check if agent can handle task type.
     */
    private function agentCanHandle(array $agent, string $taskType): bool
    {
        // Map task types to capabilities
        $taskCapabilityMap = [
            'customer_query'    => 'query_handling',
            'compliance_check'  => 'kyc_verification',
            'risk_analysis'     => 'credit_scoring',
            'trading_decision'  => 'market_analysis',
            'account_operation' => 'account_operations',
            'fraud_check'       => 'fraud_detection',
        ];

        $requiredCapability = $taskCapabilityMap[$taskType] ?? $taskType;

        return in_array($requiredCapability, $agent['capabilities']);
    }

    /**
     * Check if task requires multiple agents.
     */
    private function requiresMultipleAgents(string $taskType, array $parameters): bool
    {
        // Complex tasks requiring coordination
        $multiAgentTasks = [
            'comprehensive_risk_assessment',
            'complex_trading_strategy',
            'full_compliance_review',
            'cross_domain_analysis',
        ];

        return in_array($taskType, $multiAgentTasks)
            || ($parameters['require_multi_agent'] ?? false);
    }

    /**
     * Select best agent based on load and priority.
     */
    private function selectBestAgent(array $capableAgents, string $taskType): string
    {
        $scores = [];

        foreach ($capableAgents as $agent) {
            $priority = $this->registeredAgents[$agent]['priority'];
            $load = $this->agentLoadMetrics[$agent];

            // Higher score is better (high priority, low load)
            $scores[$agent] = ($priority * 2) - $load;
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    /**
     * Decompose complex task into sub-tasks.
     */
    private function decomposeTask(string $taskType, array $parameters): array
    {
        switch ($taskType) {
            case 'comprehensive_risk_assessment':
                return [
                    [
                        'id'                     => 'credit_risk',
                        'type'                   => 'credit_scoring',
                        'parameters'             => $parameters,
                        'requires_communication' => true,
                        'communicate_with'       => ['fraud_risk'],
                    ],
                    [
                        'id'                     => 'fraud_risk',
                        'type'                   => 'fraud_check',
                        'parameters'             => $parameters,
                        'requires_communication' => true,
                        'communicate_with'       => ['credit_risk'],
                    ],
                    [
                        'id'                     => 'market_risk',
                        'type'                   => 'market_analysis',
                        'parameters'             => $parameters,
                        'requires_communication' => false,
                        'communicate_with'       => [],
                    ],
                ];

            case 'complex_trading_strategy':
                return [
                    [
                        'id'                     => 'market_analysis',
                        'type'                   => 'market_analysis',
                        'parameters'             => $parameters,
                        'requires_communication' => true,
                        'communicate_with'       => ['risk_assessment'],
                    ],
                    [
                        'id'                     => 'risk_assessment',
                        'type'                   => 'risk_analysis',
                        'parameters'             => $parameters,
                        'requires_communication' => true,
                        'communicate_with'       => ['market_analysis'],
                    ],
                ];

            default:
                return [
                    [
                        'id'                     => 'main_task',
                        'type'                   => $taskType,
                        'parameters'             => $parameters,
                        'requires_communication' => false,
                        'communicate_with'       => [],
                    ],
                ];
        }
    }

    /**
     * Select agent for specific sub-task.
     */
    private function selectAgentForSubTask(array $capableAgents, array $subTask): string
    {
        foreach ($capableAgents as $agent) {
            if ($this->agentCanHandle($this->registeredAgents[$agent], $subTask['type'])) {
                return $agent;
            }
        }

        // Fallback to first capable agent
        return $capableAgents[0];
    }

    /**
     * Facilitate communication between agents.
     */
    private function facilitateCommunication(
        string $fromAgent,
        array $toAgents,
        array $message
    ): array {
        $communicationId = uniqid('comm_');

        $communication = [
            'id'        => $communicationId,
            'from'      => $fromAgent,
            'to'        => $toAgents,
            'message'   => $message,
            'timestamp' => now()->toIso8601String(),
            'protocol'  => 'inter_agent_v1',
        ];

        $this->agentCommunications[$communicationId] = $communication;

        // Simulate message passing and response
        $responses = [];
        foreach ($toAgents as $agent) {
            $responses[$agent] = [
                'acknowledged' => true,
                'feedback'     => $this->generateAgentFeedback($agent, $message),
            ];
        }

        return [
            'communication_id' => $communicationId,
            'responses'        => $responses,
        ];
    }

    /**
     * Generate agent feedback for communication.
     */
    private function generateAgentFeedback(string $agent, array $message): array
    {
        // Simplified feedback generation
        return [
            'agreement_level' => rand(60, 95) / 100,
            'suggestions'     => [],
            'concerns'        => [],
        ];
    }

    /**
     * Check if task requires consensus.
     */
    private function requiresConsensus(string $taskType): bool
    {
        $consensusTasks = [
            'comprehensive_risk_assessment',
            'complex_trading_strategy',
            'multi_agent_decision',
        ];

        return in_array($taskType, $consensusTasks);
    }

    /**
     * Build consensus from multiple agent results.
     */
    private function buildConsensus(
        string $taskId,
        array $results,
        array $communications
    ): array {
        $consensusBuilder = new ConsensusBuilder();

        // Aggregate confidence scores
        $confidenceScores = [];
        foreach ($results as $result) {
            if (isset($result['result']['confidence'])) {
                $confidenceScores[] = $result['result']['confidence'];
            }
        }

        $averageConfidence = ! empty($confidenceScores)
            ? array_sum($confidenceScores) / count($confidenceScores)
            : 0.5;

        // Check for conflicts
        $conflicts = $this->detectConflicts($results);

        if (! empty($conflicts)) {
            $resolution = $this->resolveConflicts($conflicts, $results);

            return [
                'consensus_reached'   => false,
                'conflict_resolution' => $resolution,
                'confidence'          => $averageConfidence * 0.8, // Reduce confidence due to conflicts
                'results'             => $results,
                'task_id'             => $taskId,
            ];
        }

        // Build final consensus
        return [
            'consensus_reached' => true,
            'confidence'        => $averageConfidence,
            'aggregated_result' => $this->mergeResults($results),
            'agent_agreement'   => $this->calculateAgreement($results),
            'task_id'           => $taskId,
        ];
    }

    /**
     * Detect conflicts in agent results.
     */
    private function detectConflicts(array $results): array
    {
        $conflicts = [];

        // Check for conflicting recommendations
        $recommendations = [];
        foreach ($results as $id => $result) {
            if (isset($result['result']['recommendation'])) {
                $recommendations[$id] = $result['result']['recommendation'];
            }
        }

        if (count(array_unique($recommendations)) > 1) {
            $conflicts[] = [
                'type'   => 'recommendation_conflict',
                'agents' => array_keys($recommendations),
                'values' => $recommendations,
            ];
        }

        return $conflicts;
    }

    /**
     * Resolve conflicts between agents.
     */
    private function resolveConflicts(array $conflicts, array $results): array
    {
        $resolutions = [];

        foreach ($conflicts as $conflict) {
            switch ($conflict['type']) {
                case 'recommendation_conflict':
                    // Use weighted voting based on confidence
                    $resolutions[] = $this->resolveByWeightedVoting($conflict, $results);
                    break;

                default:
                    // Default to highest confidence
                    $resolutions[] = $this->resolveByHighestConfidence($conflict, $results);
            }
        }

        return $resolutions;
    }

    /**
     * Resolve conflict using weighted voting.
     */
    private function resolveByWeightedVoting(array $conflict, array $results): array
    {
        $votes = [];

        foreach ($conflict['agents'] as $agent) {
            $confidence = $results[$agent]['result']['confidence'] ?? 0.5;
            $recommendation = $conflict['values'][$agent];

            if (! isset($votes[$recommendation])) {
                $votes[$recommendation] = 0;
            }

            $votes[$recommendation] += $confidence;
        }

        arsort($votes);

        return [
            'resolution_method' => 'weighted_voting',
            'winning_option'    => array_key_first($votes),
            'vote_scores'       => $votes,
        ];
    }

    /**
     * Resolve by selecting highest confidence result.
     */
    private function resolveByHighestConfidence(array $conflict, array $results): array
    {
        $highestConfidence = 0;
        $selectedAgent = null;

        foreach ($conflict['agents'] as $agent) {
            $confidence = $results[$agent]['result']['confidence'] ?? 0;
            if ($confidence > $highestConfidence) {
                $highestConfidence = $confidence;
                $selectedAgent = $agent;
            }
        }

        return [
            'resolution_method' => 'highest_confidence',
            'selected_agent'    => $selectedAgent,
            'confidence'        => $highestConfidence,
        ];
    }

    /**
     * Aggregate results without consensus requirement.
     */
    private function aggregateResults(
        string $taskId,
        array $results,
        array $communications
    ): array {
        return [
            'task_id'        => $taskId,
            'results'        => $results,
            'communications' => $communications,
            'aggregation'    => $this->mergeResults($results),
            'success'        => $this->determineOverallSuccess($results),
        ];
    }

    /**
     * Merge multiple agent results.
     */
    private function mergeResults(array $results): array
    {
        $merged = [];

        foreach ($results as $result) {
            if (isset($result['result']) && is_array($result['result'])) {
                $merged = array_merge_recursive($merged, $result['result']);
            }
        }

        return $merged;
    }

    /**
     * Calculate agreement level between agents.
     */
    private function calculateAgreement(array $results): float
    {
        if (count($results) < 2) {
            return 1.0;
        }

        // Simplified agreement calculation based on confidence similarity
        $confidences = array_map(
            fn ($r) => $r['result']['confidence'] ?? 0.5,
            $results
        );

        $variance = $this->calculateVariance($confidences);

        // Lower variance means higher agreement
        return max(0, 1 - ($variance * 2));
    }

    /**
     * Calculate variance of values.
     */
    private function calculateVariance(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn ($v) => pow($v - $mean, 2), $values);

        return array_sum($squaredDiffs) / count($values);
    }

    /**
     * Determine overall success from results.
     */
    private function determineOverallSuccess(array $results): bool
    {
        $successCount = 0;

        foreach ($results as $result) {
            if ($result['success'] ?? false) {
                $successCount++;
            }
        }

        // Require majority success
        return $successCount > (count($results) / 2);
    }

    /**
     * Handle case when no capable agents found.
     */
    private function handleNoCapableAgents(string $taskId, string $taskType): array
    {
        return [
            'success' => false,
            'error'   => "No agents capable of handling task type: {$taskType}",
            'task_id' => $taskId,
        ];
    }

    /**
     * Update agent load metrics.
     */
    private function updateAgentLoad(string $agent, float $delta): void
    {
        $this->agentLoadMetrics[$agent] = max(
            0,
            min(1, $this->agentLoadMetrics[$agent] + $delta)
        );
    }

    /**
     * Get current coordination status.
     */
    public function getCoordinationStatus(): array
    {
        return [
            'registered_agents' => array_keys($this->registeredAgents),
            'active_tasks'      => $this->activeTasks,
            'agent_loads'       => $this->agentLoadMetrics,
            'communications'    => count($this->agentCommunications),
        ];
    }

    /**
     * Register new agent.
     *
     * @param class-string $workflowClass
     */
    public function registerAgent(
        string $name,
        string $workflowClass,
        array $capabilities,
        int $priority = 1
    ): void {
        $this->registeredAgents[$name] = [
            'class'        => $workflowClass,
            'capabilities' => $capabilities,
            'priority'     => $priority,
        ];

        $this->agentLoadMetrics[$name] = 0.0;
    }
}
