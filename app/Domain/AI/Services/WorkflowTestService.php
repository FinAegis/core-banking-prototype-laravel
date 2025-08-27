<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\AIDecisionMadeEvent;
use App\Domain\AI\Events\CompensationExecutedEvent;
use App\Domain\AI\Events\HumanInterventionRequestedEvent;
use Exception;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Service to test workflow business logic without workflow engine overhead
 * Maintains DDD, Event Sourcing, and Saga patterns for fast testing.
 */
class WorkflowTestService
{
    /**
     * Test compliance workflow logic using event sourcing
     * This simulates the workflow execution without the engine overhead.
     */
    public function simulateComplianceWorkflow(
        string $conversationId,
        string $userId,
        string $complianceType,
        array $parameters = []
    ): array {
        // Use event sourcing aggregate
        $aggregate = AIInteractionAggregate::retrieve($conversationId);

        // Start conversation event
        $aggregate->startConversation(
            $conversationId,
            'compliance-agent',
            $userId,
            ['compliance_type' => $complianceType]
        );

        // Simulate workflow steps without generator overhead
        $result = match ($complianceType) {
            'kyc'                    => $this->simulateKycCheck($parameters),
            'aml'                    => $this->simulateAmlCheck($parameters),
            'transaction_monitoring' => $this->simulateTransactionMonitoring($parameters),
            default                  => throw new InvalidArgumentException("Unknown compliance type: {$complianceType}")
        };

        // Calculate confidence for decision
        $confidence = $this->calculateConfidence($complianceType, $result);

        // Record decision in aggregate (event sourcing)
        $aggregate->makeDecision(
            "Compliance check completed: {$complianceType}",
            [
                'type'   => $complianceType,
                'result' => $result,
            ],
            $confidence,
            $confidence < 0.7
        );

        // Persist events
        $aggregate->persist();

        return [
            'success'         => true,
            'compliance_type' => $complianceType,
            'result'          => $result,
            'metadata'        => [
                'conversation_id' => $conversationId,
                'user_id'         => $userId,
                'confidence'      => $confidence,
            ],
        ];
    }

    /**
     * Test saga compensation logic without workflow overhead.
     */
    public function simulateSagaCompensation(
        string $conversationId,
        array $completedSteps,
        Exception $failure
    ): bool {
        // Simulate compensation in reverse order (saga pattern)
        foreach (array_reverse($completedSteps) as $step) {
            Event::dispatch(new CompensationExecutedEvent(
                $conversationId,
                $step['type'], // workflowId
                'Compensating due to: ' . $failure->getMessage(), // reason (string)
                [$step['data']], // compensatedActions (array)
                true, // success
                null // errorMessage
            ));
        }

        return true;
    }

    /**
     * Test human-in-the-loop logic without waiting.
     */
    public function simulateHumanApproval(
        string $conversationId,
        string $operation,
        array $data,
        bool $approved = true
    ): array {
        $aggregate = AIInteractionAggregate::retrieve($conversationId);

        // Initialize the aggregate if it's new
        // Check if aggregate has been initialized with a conversation
        // Using reflection to check if property is initialized since ?? doesn't throw
        $reflectionClass = new ReflectionClass($aggregate);
        $conversationIdInitialized = false;

        if ($reflectionClass->hasProperty('conversationId')) {
            $property = $reflectionClass->getProperty('conversationId');
            $property->setAccessible(true);
            $conversationIdInitialized = $property->isInitialized($aggregate);
        }

        if (! $conversationIdInitialized) {
            $aggregate->startConversation(
                $conversationId,
                'approval-agent',
                'test_user',
                ['operation' => $operation]
            );
        }

        // Request human intervention (event)
        Event::dispatch(new HumanInterventionRequestedEvent(
            $conversationId,
            'Approval required for: ' . $operation,
            $data,
            0.5, // Low confidence requiring approval
            null
        ));

        // Simulate immediate approval/rejection without timeout
        $approverId = $approved ? 'test_approver' : 'test_rejector';
        $comments = $approved ? 'Approved in test' : 'Rejected in test';

        // Record decision
        $aggregate->makeDecision(
            $approved ? 'Approved' : 'Rejected',
            ['approver' => $approverId, 'comments' => $comments],
            1.0, // High confidence after human review
            false
        );

        $aggregate->persist();

        return [
            'approved'    => $approved,
            'approver_id' => $approverId,
            'comments'    => $comments,
            'timed_out'   => false,
        ];
    }

    /**
     * Test multi-agent coordination using DDD.
     */
    public function simulateMultiAgentCoordination(
        string $taskId,
        string $taskType,
        array $agents
    ): array {
        $subtasks = [];
        $results = [];

        // Create subtasks for each agent (DDD bounded contexts)
        foreach ($agents as $agent) {
            $subtaskId = "{$taskId}_{$agent}";
            $conversationId = "conv_{$subtaskId}";

            // Each agent has its own aggregate (bounded context)
            $aggregate = AIInteractionAggregate::retrieve($conversationId);
            $aggregate->startConversation($conversationId, $agent, null, ['task' => $taskType]);

            // Simulate agent decision
            $aggregate->makeDecision(
                "Task handled by {$agent}",
                ['agent' => $agent, 'task' => $taskType],
                0.85,
                false
            );

            $aggregate->persist();

            $subtasks[] = $subtaskId;
            $results[$agent] = ['status' => 'completed', 'confidence' => 0.85];
        }

        // Coordination event
        Event::dispatch(new AIDecisionMadeEvent(
            $taskId,
            'coordinator',
            'Multi-agent coordination completed',
            ['subtasks' => $subtasks, 'results' => $results],
            0.9,
            false,
            null
        ));

        return [
            'lead_agent' => $agents[0] ?? 'coordinator',
            'subtasks'   => $subtasks,
            'results'    => $results,
        ];
    }

    private function simulateKycCheck(array $parameters): array
    {
        $documents = $parameters['documents'] ?? [];
        $score = 50;

        if (isset($documents['id_document'])) {
            $score += 25;
        }
        if (isset($documents['proof_of_address'])) {
            $score += 25;
        }

        return [
            'verified'        => $score >= 70,
            'score'           => $score,
            'level'           => $parameters['level'] ?? 'standard',
            'issues'          => $score < 70 ? ['Additional documentation required'] : [],
            'requires_report' => false,
            'alerts'          => [],
        ];
    }

    private function simulateAmlCheck(array $parameters): array
    {
        $amount = $parameters['amount'] ?? 0;
        $riskScore = $amount > 10000 ? 25 : 10;

        return [
            'cleared'         => true,
            'risk_score'      => $riskScore,
            'flags'           => [],
            'sanctions_match' => false,
            'requires_report' => false,
            'alerts'          => [],
        ];
    }

    private function simulateTransactionMonitoring(array $parameters): array
    {
        return [
            'monitored'        => true,
            'period'           => $parameters['period'] ?? 'last_30_days',
            'patterns'         => ['suspicious' => [], 'alerts' => []],
            'unusual_activity' => ['detected' => false, 'alerts' => []],
            'requires_report'  => false,
            'alerts'           => [],
        ];
    }

    private function calculateConfidence(string $complianceType, array $result): float
    {
        return match ($complianceType) {
            'kyc'                    => ($result['score'] ?? 0) / 100,
            'aml'                    => 1 - (($result['risk_score'] ?? 0) / 100),
            'transaction_monitoring' => empty($result['alerts']) ? 0.9 : 0.3,
            default                  => 0.5,
        };
    }
}
