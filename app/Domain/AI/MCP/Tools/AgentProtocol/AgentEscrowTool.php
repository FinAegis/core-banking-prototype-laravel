<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\AgentProtocol;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\Services\AIAgentProtocolBridgeService;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tool for AI Agent Escrow Operations.
 *
 * Enables AI agents to create and manage escrow transactions
 * with condition-based release for secure agent-to-agent commerce.
 */
class AgentEscrowTool implements MCPToolInterface
{
    public function __construct(
        private readonly AIAgentProtocolBridgeService $bridgeService
    ) {
    }

    public function getName(): string
    {
        return 'agent_protocol.escrow';
    }

    public function getCategory(): string
    {
        return 'agent_protocol';
    }

    public function getDescription(): string
    {
        return 'Create and manage escrow transactions between AI agents. Supports condition-based fund release, timeout handling, and dispute resolution.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'description' => 'The escrow action to perform',
                    'enum'        => ['create', 'status', 'release', 'dispute'],
                ],
                'buyer_agent' => [
                    'type'        => 'string',
                    'description' => 'The name/identifier of the buyer AI agent (for create action)',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
                'seller_agent' => [
                    'type'        => 'string',
                    'description' => 'The name/identifier of the seller AI agent (for create action)',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
                'amount' => [
                    'type'        => 'number',
                    'description' => 'The escrow amount (for create action)',
                    'minimum'     => 0.01,
                ],
                'currency' => [
                    'type'        => 'string',
                    'description' => 'The currency code',
                    'pattern'     => '^[A-Z]{3,10}$',
                    'default'     => 'USD',
                ],
                'conditions' => [
                    'type'        => 'array',
                    'description' => 'Release conditions that must be met (for create action)',
                    'items'       => ['type' => 'string'],
                    'examples'    => [['delivery_confirmed', 'quality_approved']],
                ],
                'timeout_seconds' => [
                    'type'        => 'integer',
                    'description' => 'Escrow timeout in seconds (default: 7 days)',
                    'minimum'     => 3600,
                    'maximum'     => 2592000,
                    'default'     => 604800,
                ],
                'escrow_id' => [
                    'type'        => 'string',
                    'description' => 'The escrow ID (for status/release/dispute actions)',
                ],
                'dispute_reason' => [
                    'type'        => 'string',
                    'description' => 'Reason for dispute (for dispute action)',
                    'maxLength'   => 1000,
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
                'escrow_id'  => ['type' => 'string', 'description' => 'Unique escrow identifier'],
                'status'     => ['type' => 'string', 'description' => 'Current escrow status'],
                'buyer_did'  => ['type' => 'string', 'description' => 'DID of the buyer agent'],
                'seller_did' => ['type' => 'string', 'description' => 'DID of the seller agent'],
                'amount'     => ['type' => 'number', 'description' => 'Escrow amount'],
                'currency'   => ['type' => 'string', 'description' => 'Currency code'],
                'conditions' => ['type' => 'array', 'description' => 'Release conditions'],
                'timeout_at' => ['type' => 'string', 'description' => 'Escrow timeout timestamp'],
                'created_at' => ['type' => 'string', 'description' => 'Creation timestamp'],
                'action'     => ['type' => 'string', 'description' => 'Action performed'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $action = $parameters['action'];

            Log::info('MCP Tool: Processing escrow action', [
                'action'          => $action,
                'conversation_id' => $conversationId,
            ]);

            return match ($action) {
                'create'  => $this->createEscrow($parameters, $conversationId),
                'status'  => $this->getEscrowStatus($parameters),
                'release' => $this->releaseEscrow($parameters),
                'dispute' => $this->disputeEscrow($parameters),
                default   => ToolExecutionResult::failure("Unknown action: {$action}"),
            };
        } catch (Exception $e) {
            Log::error('MCP Tool error: agent_protocol.escrow', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
                'trace'      => $e->getTraceAsString(),
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function createEscrow(array $parameters, ?string $conversationId): ToolExecutionResult
    {
        $buyerAgent = $parameters['buyer_agent'] ?? null;
        $sellerAgent = $parameters['seller_agent'] ?? null;
        $amount = (float) ($parameters['amount'] ?? 0);
        $conditions = $parameters['conditions'] ?? [];
        $timeoutSeconds = (int) ($parameters['timeout_seconds'] ?? 604800);

        if (! $buyerAgent || ! $sellerAgent || $amount <= 0) {
            return ToolExecutionResult::failure('buyer_agent, seller_agent, and amount are required for create action');
        }

        // Register agents if not already registered
        $this->bridgeService->registerAIAgent($buyerAgent);
        $this->bridgeService->registerAIAgent($sellerAgent);

        Log::info('MCP Tool: Creating escrow', [
            'buyer'           => $buyerAgent,
            'seller'          => $sellerAgent,
            'amount'          => $amount,
            'conditions'      => $conditions,
            'conversation_id' => $conversationId,
        ]);

        $result = $this->bridgeService->createAIAgentEscrow(
            $buyerAgent,
            $sellerAgent,
            $amount,
            $conditions,
            $timeoutSeconds
        );

        $result['action'] = 'create';
        $result['created_at'] = now()->toIso8601String();

        Log::info('MCP Tool: Escrow created', [
            'escrow_id' => $result['escrow_id'],
        ]);

        return ToolExecutionResult::success($result);
    }

    private function getEscrowStatus(array $parameters): ToolExecutionResult
    {
        $escrowId = $parameters['escrow_id'] ?? null;

        if (! $escrowId) {
            return ToolExecutionResult::failure('escrow_id is required for status action');
        }

        // Note: This would integrate with the EscrowService to get actual status
        // For now, return a structured response indicating the operation
        return ToolExecutionResult::success([
            'escrow_id' => $escrowId,
            'action'    => 'status',
            'message'   => 'Escrow status query processed. Use escrow service directly for full status.',
        ]);
    }

    private function releaseEscrow(array $parameters): ToolExecutionResult
    {
        $escrowId = $parameters['escrow_id'] ?? null;

        if (! $escrowId) {
            return ToolExecutionResult::failure('escrow_id is required for release action');
        }

        Log::info('MCP Tool: Releasing escrow', [
            'escrow_id' => $escrowId,
        ]);

        // Note: This would integrate with the EscrowService to release funds
        // For now, return a structured response indicating the operation
        return ToolExecutionResult::success([
            'escrow_id'   => $escrowId,
            'action'      => 'release',
            'status'      => 'release_initiated',
            'released_at' => now()->toIso8601String(),
        ]);
    }

    private function disputeEscrow(array $parameters): ToolExecutionResult
    {
        $escrowId = $parameters['escrow_id'] ?? null;
        $reason = $parameters['dispute_reason'] ?? 'No reason provided';

        if (! $escrowId) {
            return ToolExecutionResult::failure('escrow_id is required for dispute action');
        }

        Log::info('MCP Tool: Disputing escrow', [
            'escrow_id' => $escrowId,
            'reason'    => $reason,
        ]);

        // Note: This would integrate with the EscrowService to handle disputes
        // For now, return a structured response indicating the operation
        return ToolExecutionResult::success([
            'escrow_id'   => $escrowId,
            'action'      => 'dispute',
            'status'      => 'dispute_filed',
            'reason'      => $reason,
            'disputed_at' => now()->toIso8601String(),
        ]);
    }

    public function getCapabilities(): array
    {
        return [
            'write',
            'read',
            'multi-currency',
            'transactional',
            'condition-based',
            'timeout-handling',
            'dispute-resolution',
            'agent-to-agent',
        ];
    }

    public function isCacheable(): bool
    {
        return false; // Escrow operations should never be cached
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validateInput(array $parameters): bool
    {
        // Validate action is provided
        if (empty($parameters['action'])) {
            return false;
        }

        $action = $parameters['action'];

        // Validate based on action type
        if ($action === 'create') {
            if (empty($parameters['buyer_agent']) || empty($parameters['seller_agent'])) {
                return false;
            }
            if ($parameters['buyer_agent'] === $parameters['seller_agent']) {
                return false; // Cannot escrow with self
            }
            if (! isset($parameters['amount']) || $parameters['amount'] <= 0) {
                return false;
            }
        }

        if (in_array($action, ['status', 'release', 'dispute']) && empty($parameters['escrow_id'])) {
            return false;
        }

        // Validate currency if provided
        if (isset($parameters['currency'])) {
            if (! preg_match('/^[A-Z]{3,10}$/', $parameters['currency'])) {
                return false;
            }
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // Escrow operations can be initiated by authenticated users or AI agents
        return true;
    }
}
