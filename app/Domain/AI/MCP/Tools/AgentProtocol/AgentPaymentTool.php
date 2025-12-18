<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\AgentProtocol;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\Services\AIAgentProtocolBridgeService;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MCP Tool for AI Agent-to-Agent Payments.
 *
 * Enables AI agents to initiate payments to other agents using
 * the Agent Protocol infrastructure for secure, verified transactions.
 */
class AgentPaymentTool implements MCPToolInterface
{
    public function __construct(
        private readonly AIAgentProtocolBridgeService $bridgeService
    ) {
    }

    public function getName(): string
    {
        return 'agent_protocol.payment';
    }

    public function getCategory(): string
    {
        return 'agent_protocol';
    }

    public function getDescription(): string
    {
        return 'Initiate a payment between AI agents using the Agent Protocol. Supports multi-currency transactions with automatic fee calculation and reputation tracking.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'from_agent' => [
                    'type'        => 'string',
                    'description' => 'The name/identifier of the sending AI agent',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
                'to_agent' => [
                    'type'        => 'string',
                    'description' => 'The name/identifier of the receiving AI agent',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
                'amount' => [
                    'type'        => 'number',
                    'description' => 'The amount to transfer',
                    'minimum'     => 0.01,
                ],
                'currency' => [
                    'type'        => 'string',
                    'description' => 'The currency code (e.g., USD, EUR, BTC)',
                    'pattern'     => '^[A-Z]{3,10}$',
                    'default'     => 'USD',
                ],
                'purpose' => [
                    'type'        => 'string',
                    'description' => 'The purpose of the payment (e.g., service_payment, data_purchase)',
                    'maxLength'   => 255,
                ],
                'metadata' => [
                    'type'        => 'object',
                    'description' => 'Optional metadata for the payment',
                    'properties'  => [
                        'reference'   => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'tags'        => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            'required' => ['from_agent', 'to_agent', 'amount'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'transaction_id' => ['type' => 'string', 'description' => 'Unique transaction identifier'],
                'status'         => ['type' => 'string', 'description' => 'Transaction status'],
                'from_did'       => ['type' => 'string', 'description' => 'DID of the sending agent'],
                'to_did'         => ['type' => 'string', 'description' => 'DID of the receiving agent'],
                'amount'         => ['type' => 'number', 'description' => 'Transaction amount'],
                'currency'       => ['type' => 'string', 'description' => 'Currency code'],
                'fees'           => ['type' => 'number', 'description' => 'Transaction fees'],
                'total_amount'   => ['type' => 'number', 'description' => 'Total amount including fees'],
                'timestamp'      => ['type' => 'string', 'description' => 'ISO 8601 timestamp'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $fromAgent = $parameters['from_agent'];
            $toAgent = $parameters['to_agent'];
            $amount = (float) $parameters['amount'];
            $currency = $parameters['currency'] ?? 'USD';
            $purpose = $parameters['purpose'] ?? 'payment';

            Log::info('MCP Tool: Initiating agent payment', [
                'from_agent'      => $fromAgent,
                'to_agent'        => $toAgent,
                'amount'          => $amount,
                'currency'        => $currency,
                'purpose'         => $purpose,
                'conversation_id' => $conversationId,
            ]);

            // Register agents if not already registered
            $this->bridgeService->registerAIAgent($fromAgent);
            $this->bridgeService->registerAIAgent($toAgent);

            // Initiate the payment through the bridge service
            $result = $this->bridgeService->initiateAIAgentPayment(
                $fromAgent,
                $toAgent,
                $amount,
                $currency,
                $purpose
            );

            // Add timestamp and total amount
            $result['timestamp'] = now()->toIso8601String();
            $result['total_amount'] = $amount + ($result['fees'] ?? 0);

            Log::info('MCP Tool: Agent payment completed', [
                'transaction_id' => $result['transaction_id'],
                'status'         => $result['status'],
            ]);

            return ToolExecutionResult::success($result);
        } catch (Exception $e) {
            Log::error('MCP Tool error: agent_protocol.payment', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
                'trace'      => $e->getTraceAsString(),
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    public function getCapabilities(): array
    {
        return [
            'write',
            'multi-currency',
            'transactional',
            'reputation-tracked',
            'fee-calculated',
            'agent-to-agent',
        ];
    }

    public function isCacheable(): bool
    {
        return false; // Payments should never be cached
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validateInput(array $parameters): bool
    {
        // Validate required fields
        if (empty($parameters['from_agent']) || empty($parameters['to_agent'])) {
            return false;
        }

        // Prevent self-payment
        if ($parameters['from_agent'] === $parameters['to_agent']) {
            return false;
        }

        // Validate amount
        if (! isset($parameters['amount']) || $parameters['amount'] <= 0) {
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
        // Agent payments can be initiated by authenticated users or AI agents
        // In AI-to-AI scenarios, the conversation context provides authorization
        return true;
    }
}
