<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services\Integration;

use App\Domain\AgentProtocol\Events\Integration\AIAgentLinked;
use App\Domain\AgentProtocol\Events\Integration\AICapabilityEnabled;
use App\Domain\AgentProtocol\Events\Integration\AIConversationInitiated;
use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AI\Services\AIAgentService;
use App\Domain\AI\Services\ConversationService;
use App\Domain\AI\Services\MultiAgentCoordinationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Integration service to connect Agent Protocol with AI agent framework.
 *
 * This service provides:
 * - AI agent authentication and authorization
 * - Payment capability integration for AI agents
 * - Conversation flow with payment support
 * - Multi-agent coordination with protocol compliance
 */
class AIIntegrationService
{
    public function __construct(
        private readonly AIAgentService $aiAgentService,
        private readonly ConversationService $conversationService,
        private readonly MultiAgentCoordinationService $coordinationService,
        private readonly AgentRegistryService $agentRegistryService
    ) {
    }

    /**
     * Link an AI agent with Agent Protocol capabilities.
     */
    public function linkAIAgent(
        string $aiAgentId,
        string $protocolAgentId,
        array $capabilities = []
    ): array {
        try {
            DB::beginTransaction();

            // Get protocol agent
            $protocolAgent = Agent::where('agent_id', $protocolAgentId)->firstOrFail();

            // Update agent with AI link in metadata
            $metadata = $protocolAgent->metadata ?? [];
            $metadata['ai_agent_id'] = $aiAgentId;
            $metadata['ai_capabilities'] = array_merge(
                $metadata['ai_capabilities'] ?? [],
                $capabilities
            );
            $metadata['ai_linked_at'] = now()->toIso8601String();
            $protocolAgent->metadata = $metadata;
            $protocolAgent->save();

            // Register AI agent with coordination service
            $this->coordinationService->registerAgent(
                name: 'agent_' . $aiAgentId,
                workflowClass: get_class($this->aiAgentService),
                capabilities: array_merge(['payment', 'escrow', 'wallet'], $capabilities),
                priority: 5
            );

            // Enable payment capabilities for AI agent
            foreach ($capabilities as $capability) {
                $this->enableCapability($aiAgentId, $protocolAgentId, $capability);
            }

            // Emit integration event
            Event::dispatch(new AIAgentLinked(
                aiAgentId: $aiAgentId,
                protocolAgentId: $protocolAgentId,
                capabilities: $capabilities,
                linkedAt: now()
            ));

            DB::commit();

            Log::info('AI agent linked with protocol capabilities', [
                'ai_agent_id'       => $aiAgentId,
                'protocol_agent_id' => $protocolAgentId,
                'capabilities'      => $capabilities,
            ]);

            return [
                'success'           => true,
                'ai_agent_id'       => $aiAgentId,
                'protocol_agent_id' => $protocolAgentId,
                'capabilities'      => $capabilities,
                'linked_at'         => $metadata['ai_linked_at'],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to link AI agent', [
                'error'             => $e->getMessage(),
                'ai_agent_id'       => $aiAgentId,
                'protocol_agent_id' => $protocolAgentId,
            ]);
            throw $e;
        }
    }

    /**
     * Initialize payment-enabled conversation with AI agent.
     */
    public function initializePaymentConversation(
        string $aiAgentId,
        string $userId,
        array $context = []
    ): array {
        try {
            $conversationId = 'conv_' . Str::uuid()->toString();

            // Get protocol agent linked to AI agent (using metadata field)
            $protocolAgent = Agent::whereJsonContains('metadata->ai_agent_id', $aiAgentId)->first();
            if (! $protocolAgent) {
                throw new Exception('AI agent not linked to protocol');
            }

            // Initialize conversation with payment context
            // TODO: ConversationService needs createConversation method
            $conversation = [
                'conversation_id' => $conversationId,
                'user_id'         => $userId,
                'agent_id'        => $aiAgentId,
                'context'         => array_merge($context, [
                    'payment_enabled'   => true,
                    'protocol_agent_id' => $protocolAgent->agent_id,
                    'wallet_id'         => $protocolAgent->metadata['wallet_id'] ?? null,
                    'capabilities'      => $protocolAgent->metadata['ai_capabilities'] ?? [],
                ]),
                'started_at' => now(),
            ];

            // Set up payment tools for conversation
            $this->setupPaymentTools($conversationId, $protocolAgent->agent_id);

            // Emit conversation event
            Event::dispatch(new AIConversationInitiated(
                conversationId: $conversationId,
                aiAgentId: $aiAgentId,
                userId: $userId,
                paymentEnabled: true,
                context: $context
            ));

            Log::info('Payment-enabled conversation initialized', [
                'conversation_id' => $conversationId,
                'ai_agent_id'     => $aiAgentId,
                'user_id'         => $userId,
            ]);

            return [
                'success'         => true,
                'conversation_id' => $conversationId,
                'agent_id'        => $aiAgentId,
                'payment_enabled' => true,
                'available_tools' => $this->getAvailableTools($protocolAgent->agent_id),
                'wallet_balance'  => $protocolAgent->metadata['wallet_balance'] ?? 0,
            ];
        } catch (Exception $e) {
            Log::error('Failed to initialize payment conversation', [
                'error'       => $e->getMessage(),
                'ai_agent_id' => $aiAgentId,
                'user_id'     => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Process AI agent payment request.
     */
    public function processAgentPaymentRequest(
        string $conversationId,
        string $paymentType,
        array $paymentData
    ): array {
        try {
            DB::beginTransaction();

            // Validate conversation has payment capability
            // TODO: ConversationService needs getConversation method
            $conversation = ['conversation_id' => $conversationId, 'context' => ['payment_enabled' => true, 'protocol_agent_id' => 'temp']];
            if (! $conversation || ! ($conversation['context']['payment_enabled'] ?? false)) {
                throw new Exception('Payment not enabled for this conversation');
            }

            $protocolAgentId = $conversation['context']['protocol_agent_id'];
            $result = [];

            switch ($paymentType) {
                case 'transfer':
                    $result = $this->processTransferRequest(
                        $protocolAgentId,
                        $paymentData['recipient'],
                        $paymentData['amount'],
                        $paymentData['currency'] ?? 'USD',
                        $paymentData['metadata'] ?? []
                    );
                    break;

                case 'escrow':
                    $result = $this->processEscrowRequest(
                        $protocolAgentId,
                        $paymentData['counterparty'],
                        $paymentData['amount'],
                        $paymentData['conditions'] ?? [],
                        $paymentData['metadata'] ?? []
                    );
                    break;

                case 'invoice':
                    $result = $this->processInvoiceRequest(
                        $protocolAgentId,
                        $paymentData['payer'],
                        $paymentData['amount'],
                        $paymentData['description'] ?? '',
                        $paymentData['metadata'] ?? []
                    );
                    break;

                default:
                    throw new Exception("Unsupported payment type: {$paymentType}");
            }

            // Update conversation with payment result
            // TODO: ConversationService needs addMessage method
            // $this->conversationService->addMessage($conversationId, [
            //     'role'           => 'system',
            //     'content'        => "Payment processed: {$paymentType}",
            //     'payment_result' => $result,
            // ]);

            DB::commit();

            return [
                'success'         => true,
                'conversation_id' => $conversationId,
                'payment_type'    => $paymentType,
                'result'          => $result,
                'timestamp'       => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to process agent payment request', [
                'error'           => $e->getMessage(),
                'conversation_id' => $conversationId,
                'payment_type'    => $paymentType,
            ]);
            throw $e;
        }
    }

    /**
     * Authenticate AI agent for protocol operations.
     */
    public function authenticateAIAgent(
        string $aiAgentId,
        array $credentials
    ): array {
        try {
            // Get protocol agent using metadata field
            $protocolAgent = Agent::whereJsonContains('metadata->ai_agent_id', $aiAgentId)->first();
            if (! $protocolAgent) {
                throw new Exception('AI agent not registered in protocol');
            }

            // Verify agent credentials
            $isValid = $this->agentRegistryService->verifyAgent(
                $protocolAgent->agent_id,
                $credentials['signature'] ?? '',
                $credentials['nonce'] ?? ''
            );

            if (! $isValid) {
                throw new Exception('Invalid agent credentials');
            }

            // Generate session token
            $sessionToken = Str::random(64);
            $expiresAt = now()->addHours(24);

            // Store session
            DB::table('agent_sessions')->insert([
                'session_id'  => Str::uuid()->toString(),
                'agent_id'    => $protocolAgent->agent_id,
                'ai_agent_id' => $aiAgentId,
                'token'       => hash('sha256', $sessionToken),
                'expires_at'  => $expiresAt,
                'created_at'  => now(),
            ]);

            Log::info('AI agent authenticated', [
                'ai_agent_id'       => $aiAgentId,
                'protocol_agent_id' => $protocolAgent->agent_id,
                'expires_at'        => $expiresAt->toIso8601String(),
            ]);

            return [
                'success'      => true,
                'token'        => $sessionToken,
                'expires_at'   => $expiresAt->toIso8601String(),
                'agent_id'     => $protocolAgent->agent_id,
                'capabilities' => $protocolAgent->metadata['ai_capabilities'] ?? [],
            ];
        } catch (Exception $e) {
            Log::error('Failed to authenticate AI agent', [
                'error'       => $e->getMessage(),
                'ai_agent_id' => $aiAgentId,
            ]);
            throw $e;
        }
    }

    /**
     * Get AI agent protocol status.
     */
    public function getAgentStatus(string $aiAgentId): array
    {
        try {
            $protocolAgent = Agent::whereJsonContains('metadata->ai_agent_id', $aiAgentId)->first();

            if (! $protocolAgent) {
                return [
                    'registered'  => false,
                    'ai_agent_id' => $aiAgentId,
                ];
            }

            return [
                'registered'        => true,
                'ai_agent_id'       => $aiAgentId,
                'protocol_agent_id' => $protocolAgent->agent_id,
                'capabilities'      => $protocolAgent->metadata['ai_capabilities'] ?? [],
                'wallet_id'         => $protocolAgent->metadata['wallet_id'] ?? null,
                'wallet_balance'    => $protocolAgent->metadata['wallet_balance'] ?? 0,
                'linked_at'         => $protocolAgent->metadata['ai_linked_at'] ?? null,
                'status'            => $protocolAgent->status,
            ];
        } catch (Exception $e) {
            Log::error('Failed to get AI agent status', [
                'error'       => $e->getMessage(),
                'ai_agent_id' => $aiAgentId,
            ]);
            throw $e;
        }
    }

    /**
     * Enable specific capability for AI agent.
     */
    private function enableCapability(
        string $aiAgentId,
        string $protocolAgentId,
        string $capability
    ): void {
        try {
            // Configure capability-specific settings
            $settings = match ($capability) {
                'payment' => [
                    'daily_limit'        => 10000,
                    'transaction_limit'  => 1000,
                    'allowed_currencies' => ['USD', 'EUR'],
                ],
                'escrow' => [
                    'max_escrow_amount' => 50000,
                    'allowed_durations' => [7, 14, 30],
                ],
                'trading' => [
                    'allowed_pairs'  => ['BTC/USD', 'ETH/USD'],
                    'max_order_size' => 10000,
                ],
                default => [],
            };

            // Store capability settings
            DB::table('agent_capabilities')->insert([
                'id'          => Str::uuid()->toString(),
                'agent_id'    => $protocolAgentId,
                'ai_agent_id' => $aiAgentId,
                'capability'  => $capability,
                'settings'    => json_encode($settings),
                'enabled_at'  => now(),
            ]);

            // Emit capability event
            Event::dispatch(new AICapabilityEnabled(
                aiAgentId: $aiAgentId,
                protocolAgentId: $protocolAgentId,
                capability: $capability,
                settings: $settings
            ));
        } catch (Exception $e) {
            Log::error('Failed to enable capability', [
                'error'       => $e->getMessage(),
                'ai_agent_id' => $aiAgentId,
                'capability'  => $capability,
            ]);
        }
    }

    /**
     * Setup payment tools for conversation.
     */
    private function setupPaymentTools(string $conversationId, string $protocolAgentId): void
    {
        // This would integrate with the conversation's tool system
        // to make payment functions available to the AI agent
        $tools = [
            'check_balance' => [
                'description' => 'Check wallet balance',
                'parameters'  => [],
            ],
            'send_payment' => [
                'description' => 'Send payment to another agent or user',
                'parameters'  => ['recipient', 'amount', 'currency'],
            ],
            'create_invoice' => [
                'description' => 'Create an invoice for payment',
                'parameters'  => ['amount', 'description', 'due_date'],
            ],
            'create_escrow' => [
                'description' => 'Create an escrow transaction',
                'parameters'  => ['counterparty', 'amount', 'conditions'],
            ],
        ];

        // Store tools for conversation
        DB::table('conversation_tools')->insert([
            'conversation_id' => $conversationId,
            'agent_id'        => $protocolAgentId,
            'tools'           => json_encode($tools),
            'created_at'      => now(),
        ]);
    }

    /**
     * Get available tools for protocol agent.
     */
    private function getAvailableTools(string $protocolAgentId): array
    {
        $agent = Agent::where('agent_id', $protocolAgentId)->first();
        if (! $agent) {
            return [];
        }

        $tools = [];
        $capabilities = $agent->metadata['ai_capabilities'] ?? [];

        if (in_array('payment', $capabilities)) {
            $tools[] = 'check_balance';
            $tools[] = 'send_payment';
            $tools[] = 'receive_payment';
        }

        if (in_array('escrow', $capabilities)) {
            $tools[] = 'create_escrow';
            $tools[] = 'release_escrow';
            $tools[] = 'cancel_escrow';
        }

        if (in_array('trading', $capabilities)) {
            $tools[] = 'place_order';
            $tools[] = 'cancel_order';
            $tools[] = 'check_order_status';
        }

        return $tools;
    }

    /**
     * Process transfer request.
     */
    private function processTransferRequest(
        string $agentId,
        string $recipient,
        float $amount,
        string $currency,
        array $metadata
    ): array {
        // This would integrate with the wallet service
        // For now, returning mock result
        return [
            'transaction_id' => 'tx_' . Str::uuid()->toString(),
            'from'           => $agentId,
            'to'             => $recipient,
            'amount'         => $amount,
            'currency'       => $currency,
            'status'         => 'completed',
            'timestamp'      => now()->toIso8601String(),
        ];
    }

    /**
     * Process escrow request.
     */
    private function processEscrowRequest(
        string $agentId,
        string $counterparty,
        float $amount,
        array $conditions,
        array $metadata
    ): array {
        // This would integrate with the escrow service
        // For now, returning mock result
        return [
            'escrow_id'    => 'escrow_' . Str::uuid()->toString(),
            'initiator'    => $agentId,
            'counterparty' => $counterparty,
            'amount'       => $amount,
            'conditions'   => $conditions,
            'status'       => 'pending',
            'created_at'   => now()->toIso8601String(),
        ];
    }

    /**
     * Process invoice request.
     */
    private function processInvoiceRequest(
        string $agentId,
        string $payer,
        float $amount,
        string $description,
        array $metadata
    ): array {
        // This would integrate with the payment service
        // For now, returning mock result
        return [
            'invoice_id'  => 'inv_' . Str::uuid()->toString(),
            'issuer'      => $agentId,
            'payer'       => $payer,
            'amount'      => $amount,
            'description' => $description,
            'status'      => 'pending',
            'created_at'  => now()->toIso8601String(),
        ];
    }
}
