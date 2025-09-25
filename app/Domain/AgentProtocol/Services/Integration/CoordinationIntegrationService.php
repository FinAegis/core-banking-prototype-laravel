<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services\Integration;

use App\Domain\AgentProtocol\Events\Integration\AgentGroupCreated;
use App\Domain\AgentProtocol\Events\Integration\CollaborationInitiated;
use App\Domain\AgentProtocol\Events\Integration\MultiPartyTransactionCompleted;
use App\Domain\AgentProtocol\Models\Agent;
use App\Domain\AgentProtocol\Services\AgentRegistryService;
use App\Domain\AgentProtocol\Services\EscrowService;
use App\Domain\AI\Services\MultiAgentCoordinationService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Integration service for multi-agent coordination.
 *
 * This service provides:
 * - Multi-agent collaboration patterns
 * - Complex multi-party transactions
 * - Agent orchestration workflows
 * - Distributed consensus mechanisms
 */
class CoordinationIntegrationService
{
    public function __construct(
        private readonly MultiAgentCoordinationService $coordinationService,
        private readonly AgentRegistryService $registryService,
        private readonly EscrowService $escrowService,
        private readonly WalletIntegrationService $walletService,
        private readonly AIIntegrationService $aiService
    ) {
    }

    /**
     * Create a coordinated agent group for collaborative tasks.
     */
    public function createAgentGroup(
        string $groupName,
        array $agentIds,
        array $configuration = []
    ): array {
        try {
            DB::beginTransaction();

            $groupId = 'group_' . Str::uuid()->toString();

            // Validate all agents exist and are active
            $agents = Agent::whereIn('agent_id', $agentIds)
                ->where('status', 'active')
                ->get();

            if ($agents->count() !== count($agentIds)) {
                throw new Exception('One or more agents are not available');
            }

            // Create group record
            $group = DB::table('agent_groups')->insertGetId([
                'group_id'      => $groupId,
                'name'          => $groupName,
                'configuration' => json_encode($configuration),
                'created_at'    => now(),
            ]);

            // Add agents to group
            foreach ($agents as $agent) {
                DB::table('agent_group_members')->insert([
                    'group_id'  => $groupId,
                    'agent_id'  => $agent->agent_id,
                    'role'      => $configuration['roles'][$agent->agent_id] ?? 'member',
                    'joined_at' => now(),
                ]);

                // Register with coordination service
                if ($agent->ai_agent_id) {
                    $this->coordinationService->registerAgent(
                        name: $agent->ai_agent_id,
                        workflowClass: 'GroupAgent',
                        capabilities: array_merge(
                            $agent->ai_capabilities ?? [],
                            ['group_collaboration']
                        ),
                        priority: 5
                    );
                }
            }

            // Set up group wallet if payment coordination is enabled
            if ($configuration['enable_group_wallet'] ?? false) {
                $this->setupGroupWallet($groupId, $agentIds);
            }

            // Emit group creation event
            Event::dispatch(new AgentGroupCreated(
                groupId: $groupId,
                groupName: $groupName,
                memberIds: $agentIds,
                configuration: $configuration
            ));

            DB::commit();

            Log::info('Agent group created', [
                'group_id' => $groupId,
                'name'     => $groupName,
                'members'  => count($agentIds),
            ]);

            return [
                'success'       => true,
                'group_id'      => $groupId,
                'name'          => $groupName,
                'members'       => $agentIds,
                'configuration' => $configuration,
                'created_at'    => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create agent group', [
                'error'      => $e->getMessage(),
                'group_name' => $groupName,
            ]);
            throw $e;
        }
    }

    /**
     * Initiate multi-agent collaboration for a task.
     */
    public function initiateCollaboration(
        string $taskId,
        array $participantAgentIds,
        string $taskType,
        array $taskData
    ): array {
        try {
            DB::beginTransaction();

            $collaborationId = 'collab_' . Str::uuid()->toString();

            // Validate participants
            $agents = Agent::whereIn('agent_id', $participantAgentIds)
                ->where('status', 'active')
                ->get();

            if ($agents->count() !== count($participantAgentIds)) {
                throw new Exception('One or more agents are not available');
            }

            // Create collaboration record
            DB::table('agent_collaborations')->insert([
                'collaboration_id' => $collaborationId,
                'task_id'          => $taskId,
                'task_type'        => $taskType,
                'task_data'        => json_encode($taskData),
                'status'           => 'initiated',
                'created_at'       => now(),
            ]);

            // Add participants
            foreach ($agents as $agent) {
                DB::table('collaboration_participants')->insert([
                    'collaboration_id' => $collaborationId,
                    'agent_id'         => $agent->agent_id,
                    'role'             => $this->determineAgentRole($agent, $taskType),
                    'status'           => 'pending',
                    'joined_at'        => now(),
                ]);
            }

            // Delegate task to coordination service
            $coordinationResult = $this->coordinationService->delegateTask(
                taskId: $taskId,
                taskType: $taskType,
                requiredCapabilities: $this->getRequiredCapabilities($taskType),
                context: [
                    'collaboration_id' => $collaborationId,
                    'participants'     => $participantAgentIds,
                    'task_data'        => $taskData,
                ]
            );

            // Set up escrow if payment coordination is needed
            if ($taskData['requires_payment'] ?? false) {
                $this->setupCollaborationEscrow(
                    $collaborationId,
                    $participantAgentIds,
                    $taskData['payment_amount'] ?? 0,
                    $taskData['payment_distribution'] ?? []
                );
            }

            // Emit collaboration event
            Event::dispatch(new CollaborationInitiated(
                collaborationId: $collaborationId,
                taskId: $taskId,
                taskType: $taskType,
                participants: $participantAgentIds,
                taskData: $taskData
            ));

            DB::commit();

            Log::info('Multi-agent collaboration initiated', [
                'collaboration_id' => $collaborationId,
                'task_id'          => $taskId,
                'participants'     => count($participantAgentIds),
            ]);

            return [
                'success'             => true,
                'collaboration_id'    => $collaborationId,
                'task_id'             => $taskId,
                'participants'        => $participantAgentIds,
                'coordination_result' => $coordinationResult,
                'status'              => 'initiated',
                'created_at'          => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to initiate collaboration', [
                'error'   => $e->getMessage(),
                'task_id' => $taskId,
            ]);
            throw $e;
        }
    }

    /**
     * Execute multi-party transaction with coordination.
     */
    public function executeMultiPartyTransaction(
        array $senders,
        array $recipients,
        float $totalAmount,
        array $distribution,
        array $metadata = []
    ): array {
        try {
            DB::beginTransaction();

            $transactionId = 'multi_tx_' . Str::uuid()->toString();
            $transactions = [];

            // Validate all parties
            $allAgentIds = array_merge(
                array_keys($senders),
                array_keys($recipients)
            );

            $agents = Agent::whereIn('agent_id', $allAgentIds)->get();
            if ($agents->count() !== count($allAgentIds)) {
                throw new Exception('One or more agents not found');
            }

            // Create escrow for multi-party transaction
            $escrowId = $this->escrowService->createEscrow(
                senderId: 'system',
                recipientId: 'multi_party',
                amount: $totalAmount,
                currency: $metadata['currency'] ?? 'USD',
                conditions: [
                    'type'         => 'multi_party',
                    'participants' => $allAgentIds,
                    'distribution' => $distribution,
                ],
                metadata: array_merge($metadata, [
                    'transaction_id' => $transactionId,
                ])
            );

            // Collect funds from senders
            foreach ($senders as $agentId => $amount) {
                $agent = $agents->firstWhere('agent_id', $agentId);
                if (! $agent || ! $agent->wallet_id) {
                    throw new Exception("Agent {$agentId} does not have a wallet");
                }

                $txResult = $this->walletService->processCrossDomainTransaction(
                    fromWalletId: $agent->wallet_id,
                    toAccountUuid: 'escrow_account',
                    amount: $amount,
                    currency: $metadata['currency'] ?? 'USD',
                    metadata: [
                        'multi_party_tx' => $transactionId,
                        'role'           => 'sender',
                    ]
                );

                $transactions[] = $txResult;
            }

            // Distribute funds to recipients
            foreach ($recipients as $agentId => $amount) {
                $agent = $agents->firstWhere('agent_id', $agentId);
                if (! $agent || ! $agent->wallet_id) {
                    throw new Exception("Agent {$agentId} does not have a wallet");
                }

                // Release from escrow to recipient
                $this->escrowService->releasePartial(
                    escrowId: $escrowId,
                    recipientId: $agentId,
                    amount: $amount,
                    metadata: [
                        'multi_party_tx' => $transactionId,
                        'role'           => 'recipient',
                    ]
                );

                $transactions[] = [
                    'agent_id' => $agentId,
                    'amount'   => $amount,
                    'type'     => 'receive',
                ];
            }

            // Complete escrow
            $this->escrowService->completeEscrow($escrowId);

            // Emit completion event
            Event::dispatch(new MultiPartyTransactionCompleted(
                transactionId: $transactionId,
                senders: $senders,
                recipients: $recipients,
                totalAmount: $totalAmount,
                transactions: $transactions
            ));

            DB::commit();

            Log::info('Multi-party transaction completed', [
                'transaction_id' => $transactionId,
                'senders'        => count($senders),
                'recipients'     => count($recipients),
                'total_amount'   => $totalAmount,
            ]);

            return [
                'success'        => true,
                'transaction_id' => $transactionId,
                'escrow_id'      => $escrowId,
                'senders'        => $senders,
                'recipients'     => $recipients,
                'total_amount'   => $totalAmount,
                'transactions'   => $transactions,
                'completed_at'   => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to execute multi-party transaction', [
                'error'        => $e->getMessage(),
                'total_amount' => $totalAmount,
            ]);
            throw $e;
        }
    }

    /**
     * Coordinate agent consensus for decision making.
     */
    public function coordinateConsensus(
        string $proposalId,
        array $votingAgentIds,
        string $proposalType,
        array $proposalData,
        array $consensusRules = []
    ): array {
        try {
            $consensusId = 'consensus_' . Str::uuid()->toString();

            // Default consensus rules
            $rules = array_merge([
                'threshold' => 0.67, // 2/3 majority
                'timeout'   => 3600, // 1 hour
                'quorum'    => 0.51, // 51% participation required
            ], $consensusRules);

            // Initialize voting
            DB::table('agent_consensus')->insert([
                'consensus_id'  => $consensusId,
                'proposal_id'   => $proposalId,
                'proposal_type' => $proposalType,
                'proposal_data' => json_encode($proposalData),
                'rules'         => json_encode($rules),
                'status'        => 'voting',
                'created_at'    => now(),
                'expires_at'    => now()->addSeconds($rules['timeout']),
            ]);

            // Register voters
            foreach ($votingAgentIds as $agentId) {
                DB::table('consensus_votes')->insert([
                    'consensus_id' => $consensusId,
                    'agent_id'     => $agentId,
                    'status'       => 'pending',
                    'created_at'   => now(),
                ]);

                // Notify agent to vote
                $this->notifyAgentForVote($agentId, $consensusId, $proposalData);
            }

            // Use coordination service for consensus building
            $consensusResult = $this->coordinationService->buildConsensus(
                context: [
                    'consensus_id' => $consensusId,
                    'proposal'     => $proposalData,
                    'voters'       => $votingAgentIds,
                ],
                agents: $votingAgentIds,
                votingWeight: $this->calculateVotingWeights($votingAgentIds)
            );

            Log::info('Agent consensus coordination initiated', [
                'consensus_id' => $consensusId,
                'proposal_id'  => $proposalId,
                'voters'       => count($votingAgentIds),
            ]);

            return [
                'success'      => true,
                'consensus_id' => $consensusId,
                'proposal_id'  => $proposalId,
                'voters'       => $votingAgentIds,
                'rules'        => $rules,
                'status'       => 'voting',
                'expires_at'   => now()->addSeconds($rules['timeout'])->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to coordinate consensus', [
                'error'       => $e->getMessage(),
                'proposal_id' => $proposalId,
            ]);
            throw $e;
        }
    }

    /**
     * Get collaboration status.
     */
    public function getCollaborationStatus(string $collaborationId): array
    {
        try {
            $collaboration = DB::table('agent_collaborations')
                ->where('collaboration_id', $collaborationId)
                ->first();

            if (! $collaboration) {
                throw new Exception('Collaboration not found');
            }

            $participants = DB::table('collaboration_participants')
                ->where('collaboration_id', $collaborationId)
                ->get();

            $taskStatus = $this->coordinationService->getTaskStatus(
                $collaboration->task_id
            );

            return [
                'collaboration_id' => $collaborationId,
                'task_id'          => $collaboration->task_id,
                'task_type'        => $collaboration->task_type,
                'status'           => $collaboration->status,
                'participants'     => $participants->map(function ($p) {
                    return [
                        'agent_id' => $p->agent_id,
                        'role'     => $p->role,
                        'status'   => $p->status,
                    ];
                })->toArray(),
                'task_status' => $taskStatus,
                'created_at'  => $collaboration->created_at,
                'updated_at'  => $collaboration->updated_at,
            ];
        } catch (Exception $e) {
            Log::error('Failed to get collaboration status', [
                'error'            => $e->getMessage(),
                'collaboration_id' => $collaborationId,
            ]);
            throw $e;
        }
    }

    /**
     * Setup group wallet for collaborative payments.
     */
    private function setupGroupWallet(string $groupId, array $agentIds): void
    {
        try {
            // Create a shared wallet for the group
            $walletId = 'group_wallet_' . Str::uuid()->toString();

            DB::table('group_wallets')->insert([
                'wallet_id'  => $walletId,
                'group_id'   => $groupId,
                'balance'    => 0,
                'currency'   => 'USD',
                'created_at' => now(),
            ]);

            // Grant access to all group members
            foreach ($agentIds as $agentId) {
                DB::table('wallet_permissions')->insert([
                    'wallet_id'   => $walletId,
                    'agent_id'    => $agentId,
                    'permissions' => json_encode(['view', 'deposit', 'withdraw']),
                    'granted_at'  => now(),
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to setup group wallet', [
                'error'    => $e->getMessage(),
                'group_id' => $groupId,
            ]);
        }
    }

    /**
     * Setup collaboration escrow for payment coordination.
     */
    private function setupCollaborationEscrow(
        string $collaborationId,
        array $participantIds,
        float $amount,
        array $distribution
    ): void {
        try {
            $escrowId = $this->escrowService->createEscrow(
                senderId: 'collaboration_pool',
                recipientId: 'collaboration_participants',
                amount: $amount,
                currency: 'USD',
                conditions: [
                    'type'                  => 'collaboration',
                    'collaboration_id'      => $collaborationId,
                    'participants'          => $participantIds,
                    'distribution'          => $distribution,
                    'release_on_completion' => true,
                ],
                metadata: [
                    'collaboration_id' => $collaborationId,
                ]
            );

            DB::table('agent_collaborations')
                ->where('collaboration_id', $collaborationId)
                ->update(['escrow_id' => $escrowId]);
        } catch (Exception $e) {
            Log::error('Failed to setup collaboration escrow', [
                'error'            => $e->getMessage(),
                'collaboration_id' => $collaborationId,
            ]);
        }
    }

    /**
     * Determine agent role based on capabilities and task type.
     */
    private function determineAgentRole(Agent $agent, string $taskType): string
    {
        $capabilities = $agent->ai_capabilities ?? [];

        return match ($taskType) {
            'payment_processing' => in_array('payment', $capabilities) ? 'processor' : 'observer',
            'data_analysis'      => in_array('analytics', $capabilities) ? 'analyst' : 'contributor',
            'negotiation'        => in_array('negotiation', $capabilities) ? 'negotiator' : 'witness',
            'execution'          => in_array('execution', $capabilities) ? 'executor' : 'validator',
            default              => 'participant',
        };
    }

    /**
     * Get required capabilities for task type.
     */
    private function getRequiredCapabilities(string $taskType): array
    {
        return match ($taskType) {
            'payment_processing' => ['payment', 'wallet', 'transaction'],
            'data_analysis'      => ['analytics', 'computation', 'reporting'],
            'negotiation'        => ['negotiation', 'communication', 'decision'],
            'execution'          => ['execution', 'automation', 'verification'],
            default              => [],
        };
    }

    /**
     * Notify agent for voting.
     */
    private function notifyAgentForVote(
        string $agentId,
        string $consensusId,
        array $proposalData
    ): void {
        try {
            $agent = Agent::where('agent_id', $agentId)->first();
            if (! $agent) {
                return;
            }

            // Send notification through appropriate channel
            // This would integrate with the notification service
            Log::info('Agent notified for vote', [
                'agent_id'     => $agentId,
                'consensus_id' => $consensusId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to notify agent for vote', [
                'error'    => $e->getMessage(),
                'agent_id' => $agentId,
            ]);
        }
    }

    /**
     * Calculate voting weights for agents.
     */
    private function calculateVotingWeights(array $agentIds): array
    {
        $weights = [];

        foreach ($agentIds as $agentId) {
            $agent = Agent::where('agent_id', $agentId)->first();
            if ($agent) {
                // Weight based on reputation score and other factors
                $weights[$agentId] = $agent->reputation_score / 100;
            } else {
                $weights[$agentId] = 1.0;
            }
        }

        return $weights;
    }
}
