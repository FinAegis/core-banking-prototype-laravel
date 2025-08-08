<?php

declare(strict_types=1);

namespace App\Domain\AI\Workflows;

use App\Domain\AI\Activities\ClassifyIntentActivity;
use App\Domain\AI\Activities\ExecuteToolActivity;
use App\Domain\AI\Activities\GenerateResponseActivity;
use App\Domain\AI\Activities\ProcessQueryActivity;
use App\Domain\AI\Activities\ValidateContextActivity;
use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\ValueObjects\AgentContext;
use App\Domain\AI\ValueObjects\ConversationContext;
use Workflow\Activity\ActivityOptions;
use Workflow\ActivityStub;
use Workflow\Workflow;

class CustomerServiceWorkflow extends Workflow
{
    private array $context = [];
    private string $conversationId;
    private ?string $userId;
    private array $executionHistory = [];

    public function execute(
        string $conversationId,
        string $query,
        ?string $userId = null,
        array $initialContext = []
    ): \Generator {
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->context = $initialContext;

        try {
            // Initialize conversation in event store
            yield $this->initializeConversation();

            // Step 1: Validate context and user permissions
            $validationResult = yield $this->validateContext($query);
            
            if (!$validationResult['valid']) {
                return $this->handleValidationFailure($validationResult);
            }

            // Step 2: Process and understand the query
            $processedQuery = yield $this->processQuery($query);

            // Step 3: Classify intent with confidence scoring
            $intent = yield $this->classifyIntent($processedQuery);

            // Step 4: Execute appropriate tool based on intent
            $toolResult = yield $this->executeToolForIntent($intent);

            // Step 5: Generate natural language response
            $response = yield $this->generateResponse($intent, $toolResult);

            // Step 6: Record interaction in event store
            yield $this->recordInteraction($intent, $toolResult, $response);

            return [
                'success' => true,
                'response' => $response,
                'metadata' => [
                    'conversation_id' => $this->conversationId,
                    'intent' => $intent['name'],
                    'confidence' => $intent['confidence'],
                    'tools_used' => $this->getUsedTools(),
                    'duration_ms' => $this->calculateDuration(),
                ],
            ];

        } catch (\Exception $e) {
            // Handle workflow failure with compensation
            yield $this->handleWorkflowFailure($e);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'conversation_id' => $this->conversationId,
            ];
        }
    }

    private function initializeConversation()
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(5)
            ->withRetryAttempts(3);

        $activity = Workflow::newActivityStub(
            \App\Domain\AI\Activities\InitializeConversationActivity::class,
            $options
        );

        return yield $activity->execute(
            $this->conversationId,
            'customer-service',
            $this->userId,
            $this->context
        );
    }

    private function validateContext(string $query)
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(3)
            ->withRetryAttempts(2);

        $activity = Workflow::newActivityStub(
            ValidateContextActivity::class,
            $options
        );

        return yield $activity->execute([
            'query' => $query,
            'user_id' => $this->userId,
            'context' => $this->context,
        ]);
    }

    private function processQuery(string $query)
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(5)
            ->withRetryAttempts(3);

        $activity = Workflow::newActivityStub(
            ProcessQueryActivity::class,
            $options
        );

        $result = yield $activity->execute([
            'query' => $query,
            'conversation_id' => $this->conversationId,
            'context' => $this->context,
        ]);

        // Update context with processed query info
        $this->context['processed_query'] = $result['processed'];
        $this->context['entities'] = $result['entities'] ?? [];
        
        return $result;
    }

    private function classifyIntent(array $processedQuery)
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(10)
            ->withRetryAttempts(3);

        $activity = Workflow::newActivityStub(
            ClassifyIntentActivity::class,
            $options
        );

        $intent = yield $activity->execute([
            'query' => $processedQuery['processed'],
            'entities' => $processedQuery['entities'],
            'conversation_id' => $this->conversationId,
        ]);

        // Record intent in context
        $this->context['intent'] = $intent['name'];
        $this->context['confidence'] = $intent['confidence'];

        // Check if we need human approval for low confidence
        if ($intent['confidence'] < 0.7) {
            yield $this->requestHumanApproval($intent);
        }

        return $intent;
    }

    private function executeToolForIntent(array $intent)
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(30)
            ->withRetryAttempts(3);

        $activity = Workflow::newActivityStub(
            ExecuteToolActivity::class,
            $options
        );

        // Map intent to appropriate tool
        $toolMapping = $this->getToolMapping($intent);

        if (!$toolMapping) {
            return [
                'success' => false,
                'message' => 'No tool available for this intent',
            ];
        }

        $result = yield $activity->execute([
            'tool_name' => $toolMapping['tool'],
            'parameters' => $toolMapping['parameters'],
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
        ]);

        // Track tool execution
        $this->executionHistory[] = [
            'tool' => $toolMapping['tool'],
            'timestamp' => now()->toIso8601String(),
            'success' => $result['success'] ?? false,
        ];

        return $result;
    }

    private function generateResponse(array $intent, array $toolResult)
    {
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(15)
            ->withRetryAttempts(3);

        $activity = Workflow::newActivityStub(
            GenerateResponseActivity::class,
            $options
        );

        return yield $activity->execute([
            'intent' => $intent,
            'tool_result' => $toolResult,
            'conversation_id' => $this->conversationId,
            'context' => $this->context,
        ]);
    }

    private function recordInteraction(array $intent, array $toolResult, array $response)
    {
        $aggregate = AIInteractionAggregate::retrieve($this->conversationId);
        
        // Record AI decision
        $aggregate->makeDecision(
            $response['text'] ?? 'Response generated',
            [
                'intent' => $intent['name'],
                'tool_result' => $toolResult['success'] ?? false,
            ],
            $intent['confidence'],
            $intent['confidence'] < 0.7
        );

        $aggregate->persist();
    }

    private function handleValidationFailure(array $validationResult)
    {
        return [
            'success' => false,
            'error' => 'Validation failed',
            'details' => $validationResult['errors'] ?? [],
            'conversation_id' => $this->conversationId,
        ];
    }

    private function handleWorkflowFailure(\Exception $e)
    {
        // Log the failure
        \Log::error('Customer Service Workflow failed', [
            'conversation_id' => $this->conversationId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Record failure in event store
        $aggregate = AIInteractionAggregate::retrieve($this->conversationId);
        $aggregate->endConversation([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
        $aggregate->persist();

        // Compensation: Clean up any partial state
        yield $this->compensate();
    }

    public function compensate()
    {
        // Implement compensation logic
        // For example, rollback any partial operations
        foreach (array_reverse($this->executionHistory) as $execution) {
            if ($execution['success']) {
                // Attempt to rollback the tool execution
                // This would depend on the specific tool
            }
        }
    }

    private function requestHumanApproval(array $intent)
    {
        // In a real implementation, this would trigger a human review process
        // For now, we'll just log it
        \Log::warning('Low confidence intent requires human approval', [
            'conversation_id' => $this->conversationId,
            'intent' => $intent['name'],
            'confidence' => $intent['confidence'],
        ]);
    }

    private function getToolMapping(array $intent): ?array
    {
        $mappings = [
            'check_balance' => [
                'tool' => 'account.balance',
                'parameters' => function($entities) {
                    return [
                        'account_uuid' => $entities['account_id'] ?? null,
                        'asset_code' => $entities['currency'] ?? null,
                    ];
                },
            ],
            'transfer_funds' => [
                'tool' => 'payment.transfer',
                'parameters' => function($entities) {
                    return [
                        'from_account' => $entities['from_account'] ?? null,
                        'to_account' => $entities['to_account'] ?? null,
                        'amount' => $entities['amount'] ?? null,
                        'currency' => $entities['currency'] ?? 'USD',
                    ];
                },
            ],
            'exchange_quote' => [
                'tool' => 'exchange.quote',
                'parameters' => function($entities) {
                    return [
                        'from_currency' => $entities['from_currency'] ?? null,
                        'to_currency' => $entities['to_currency'] ?? null,
                        'amount' => $entities['amount'] ?? null,
                    ];
                },
            ],
            'check_kyc_status' => [
                'tool' => 'compliance.kyc_status',
                'parameters' => function($entities) {
                    return [
                        'user_id' => $entities['user_id'] ?? $this->userId,
                    ];
                },
            ],
        ];

        if (!isset($mappings[$intent['name']])) {
            return null;
        }

        $mapping = $mappings[$intent['name']];
        
        return [
            'tool' => $mapping['tool'],
            'parameters' => $mapping['parameters']($intent['entities'] ?? []),
        ];
    }

    private function getUsedTools(): array
    {
        return array_map(fn($e) => $e['tool'], $this->executionHistory);
    }

    private function calculateDuration(): int
    {
        // In a real implementation, track actual execution time
        return rand(100, 2000);
    }
}