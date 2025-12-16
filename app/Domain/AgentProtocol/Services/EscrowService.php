<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use App\Domain\AgentProtocol\Aggregates\AgentTransactionAggregate;
use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use App\Domain\AgentProtocol\Exceptions\WalletNotFoundException;
use App\Domain\AgentProtocol\Models\AgentWallet;
use App\Domain\AgentProtocol\Models\Escrow;
use App\Domain\AgentProtocol\Models\EscrowDispute;
use App\Domain\Compliance\Services\ComplianceService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for managing escrow transactions with dispute resolution.
 *
 * Configuration is loaded from config/agent_protocol.php:
 * - escrow.default_expiration_days: Default expiration period in days
 * - escrow.voting_threshold: Amount threshold for voting vs arbitration
 * - escrow.resolution_methods: Enabled resolution methods
 * - escrow.types: Available escrow types with descriptions
 * - escrow.minimum_amount: Minimum escrow amount
 * - escrow.maximum_amount: Maximum escrow amount
 */
class EscrowService
{
    // Escrow types - used internally, but config overrides descriptions
    private const TYPE_STANDARD = 'standard';

    private const TYPE_MILESTONE = 'milestone';

    private const TYPE_TIMED = 'timed';

    private const TYPE_CONDITIONAL = 'conditional';

    // Dispute resolution methods
    private const RESOLUTION_AUTOMATED = 'automated';

    private const RESOLUTION_ARBITRATION = 'arbitration';

    private const RESOLUTION_VOTING = 'voting';

    /**
     * Get default expiration days from configuration.
     */
    private function getDefaultExpirationDays(): int
    {
        return (int) config('agent_protocol.escrow.default_expiration_days', 30);
    }

    /**
     * Get the voting threshold for dispute resolution.
     */
    private function getVotingThreshold(): float
    {
        return (float) config('agent_protocol.escrow.voting_threshold', 10000.0);
    }

    /**
     * Get minimum escrow amount from configuration.
     */
    private function getMinimumAmount(): float
    {
        return (float) config('agent_protocol.escrow.minimum_amount', 10.0);
    }

    /**
     * Get maximum escrow amount from configuration.
     */
    private function getMaximumAmount(): float
    {
        return (float) config('agent_protocol.escrow.maximum_amount', 1000000.0);
    }

    public function __construct(
        private readonly AgentWalletService $walletService,
        private readonly ComplianceService $complianceService
    ) {
    }

    /**
     * Create a new escrow.
     *
     * @param string $transactionId Associated transaction ID
     * @param string $senderAgentId Sender agent's DID
     * @param string $receiverAgentId Receiver agent's DID
     * @param float $amount Escrow amount
     * @param string $currency Currency code (default: USD)
     * @param array<string, mixed> $conditions Release conditions
     * @param string|null $expiresAt Expiration timestamp (ISO 8601)
     * @param array<string, mixed> $metadata Additional metadata
     * @param string $type Escrow type (standard, milestone, timed, conditional)
     * @return Escrow Created escrow record
     * @throws InvalidArgumentException If validation fails
     */
    public function createEscrow(
        string $transactionId,
        string $senderAgentId,
        string $receiverAgentId,
        float $amount,
        string $currency = 'USD',
        array $conditions = [],
        ?string $expiresAt = null,
        array $metadata = [],
        string $type = self::TYPE_STANDARD
    ): Escrow {
        // Validate amount against config limits
        $minAmount = $this->getMinimumAmount();
        $maxAmount = $this->getMaximumAmount();

        if ($amount <= 0) {
            throw new InvalidArgumentException('Escrow amount must be greater than zero');
        }

        if ($amount < $minAmount) {
            throw new InvalidArgumentException("Escrow amount must be at least {$minAmount}");
        }

        if ($amount > $maxAmount) {
            throw new InvalidArgumentException("Escrow amount cannot exceed {$maxAmount}");
        }

        // Set default expiration if not provided using config
        if ($expiresAt === null) {
            $expirationDays = $this->getDefaultExpirationDays();
            $expiresAt = now()->addDays($expirationDays)->toIso8601String();
        }

        // Run compliance checks
        $complianceCheck = $this->complianceService->checkTransaction([
            'sender'   => $senderAgentId,
            'receiver' => $receiverAgentId,
            'amount'   => $amount,
            'currency' => $currency,
            'type'     => 'escrow',
        ]);

        if (! $complianceCheck['approved']) {
            throw new InvalidArgumentException('Escrow failed compliance check: ' . $complianceCheck['reason']);
        }

        $escrowId = 'escrow_' . Str::uuid()->toString();

        DB::beginTransaction();

        try {
            // Create escrow aggregate
            $aggregate = EscrowAggregate::create(
                escrowId: $escrowId,
                transactionId: $transactionId,
                senderAgentId: $senderAgentId,
                receiverAgentId: $receiverAgentId,
                amount: $amount,
                currency: $currency,
                conditions: $conditions,
                expiresAt: $expiresAt,
                metadata: array_merge($metadata, [
                    'compliance_check' => $complianceCheck['reference'],
                    'type'             => $type,
                ])
            );
            $aggregate->persist();

            // Create read model
            $escrow = Escrow::create([
                'escrow_id'         => $escrowId,
                'transaction_id'    => $transactionId,
                'sender_agent_id'   => $senderAgentId,
                'receiver_agent_id' => $receiverAgentId,
                'amount'            => $amount,
                'currency'          => $currency,
                'conditions'        => $conditions,
                'expires_at'        => $expiresAt,
                'status'            => 'created',
                'funded_amount'     => 0.0,
                'is_disputed'       => false,
                'metadata'          => array_merge($metadata, ['type' => $type]),
            ]);

            DB::commit();

            Log::info('Escrow created', [
                'escrow_id'      => $escrowId,
                'transaction_id' => $transactionId,
                'amount'         => $amount,
            ]);

            return $escrow;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Escrow creation failed', [
                'error'          => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);
            throw $e;
        }
    }

    /**
     * Fund an escrow.
     */
    public function fundEscrow(string $escrowId, string $walletId, float $amount): void
    {
        DB::beginTransaction();

        try {
            $escrow = Escrow::where('escrow_id', $escrowId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($escrow->status !== 'created') {
                throw new InvalidArgumentException("Cannot fund escrow in status: {$escrow->status}");
            }

            // Hold funds in wallet with new interface
            $holdResult = $this->walletService->holdFunds(
                walletId: $walletId,
                amount: $amount,
                currency: $escrow->currency,
                reason: 'escrow_deposit'
            );

            // Update escrow aggregate
            $aggregate = EscrowAggregate::retrieve($escrowId);
            $aggregate->deposit($amount, $walletId);
            $aggregate->persist();

            // Update read model with hold_id for later release
            $newFundedAmount = $escrow->funded_amount + $amount;
            $escrow->update([
                'funded_amount' => $newFundedAmount,
                'hold_id'       => $holdResult['hold_id'],
                'status'        => $newFundedAmount >= $escrow->amount ? 'funded' : 'created',
            ]);

            DB::commit();

            Log::info('Escrow funded', [
                'escrow_id'    => $escrowId,
                'amount'       => $amount,
                'total_funded' => $newFundedAmount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Escrow funding failed', [
                'error'     => $e->getMessage(),
                'escrow_id' => $escrowId,
            ]);
            throw $e;
        }
    }

    /**
     * Release escrow funds.
     */
    public function releaseEscrow(string $escrowId, string $releasedBy, string $reason = 'conditions_met'): void
    {
        DB::beginTransaction();

        try {
            $escrow = Escrow::where('escrow_id', $escrowId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($escrow->status, ['funded', 'resolved'], true)) {
                throw new InvalidArgumentException("Cannot release escrow in status: {$escrow->status}");
            }

            // Get wallet IDs
            $senderWallet = $this->getSenderWallet($escrow->sender_agent_id);
            $receiverWallet = $this->getReceiverWallet($escrow->receiver_agent_id);

            // Release held funds using the stored hold_id
            if ($escrow->hold_id) {
                $this->walletService->releaseFunds(
                    holdId: $escrow->hold_id
                );
            }

            // Transfer funds to receiver
            $this->walletService->transfer(
                fromWalletId: $senderWallet,
                toWalletId: $receiverWallet,
                amount: $escrow->funded_amount,
                currency: $escrow->currency,
                metadata: [
                    'type'      => 'escrow_release',
                    'escrow_id' => $escrowId,
                    'reason'    => $reason,
                ]
            );

            // Update escrow aggregate
            $aggregate = EscrowAggregate::retrieve($escrowId);
            $aggregate->release($releasedBy, $reason);
            $aggregate->persist();

            // Update read model
            $escrow->update([
                'status'      => 'released',
                'released_at' => now(),
                'released_by' => $releasedBy,
            ]);

            // Complete the associated transaction
            $this->completeTransaction($escrow->transaction_id, 'success');

            DB::commit();

            Log::info('Escrow released', [
                'escrow_id'   => $escrowId,
                'released_by' => $releasedBy,
                'amount'      => $escrow->funded_amount,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Escrow release failed', [
                'error'     => $e->getMessage(),
                'escrow_id' => $escrowId,
            ]);
            throw $e;
        }
    }

    /**
     * Dispute an escrow.
     */
    public function disputeEscrow(
        string $escrowId,
        string $disputedBy,
        string $reason,
        array $evidence = []
    ): EscrowDispute {
        DB::beginTransaction();

        try {
            $escrow = Escrow::where('escrow_id', $escrowId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($escrow->status !== 'funded') {
                throw new InvalidArgumentException("Cannot dispute escrow in status: {$escrow->status}");
            }

            // Update escrow aggregate
            $aggregate = EscrowAggregate::retrieve($escrowId);
            $aggregate->dispute($disputedBy, $reason, $evidence);
            $aggregate->persist();

            // Create dispute record
            $dispute = EscrowDispute::create([
                'dispute_id'        => 'dispute_' . Str::uuid()->toString(),
                'escrow_id'         => $escrowId,
                'disputed_by'       => $disputedBy,
                'reason'            => $reason,
                'evidence'          => $evidence,
                'status'            => 'open',
                'resolution_method' => $this->determineResolutionMethod($escrow),
            ]);

            // Update escrow read model
            $escrow->update([
                'status'      => 'disputed',
                'is_disputed' => true,
            ]);

            DB::commit();

            Log::info('Escrow disputed', [
                'escrow_id'   => $escrowId,
                'disputed_by' => $disputedBy,
                'dispute_id'  => $dispute->dispute_id,
            ]);

            // Trigger dispute resolution workflow
            $this->initiateDisputeResolution($dispute);

            return $dispute;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Escrow dispute failed', [
                'error'     => $e->getMessage(),
                'escrow_id' => $escrowId,
            ]);
            throw $e;
        }
    }

    /**
     * Resolve an escrow dispute.
     */
    public function resolveDispute(
        string $escrowId,
        string $resolvedBy,
        string $resolutionType,
        array $resolutionAllocation = [],
        array $resolutionDetails = []
    ): void {
        DB::beginTransaction();

        try {
            $escrow = Escrow::where('escrow_id', $escrowId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($escrow->status !== 'disputed') {
                throw new InvalidArgumentException('No dispute to resolve');
            }

            $dispute = EscrowDispute::where('escrow_id', $escrowId)
                ->where('status', 'open')
                ->firstOrFail();

            // Update escrow aggregate
            $aggregate = EscrowAggregate::retrieve($escrowId);
            $aggregate->resolveDispute($resolvedBy, $resolutionType, $resolutionAllocation, $resolutionDetails);
            $aggregate->persist();

            // Update dispute record
            $dispute->update([
                'status'                => 'resolved',
                'resolved_by'           => $resolvedBy,
                'resolved_at'           => now(),
                'resolution_type'       => $resolutionType,
                'resolution_allocation' => $resolutionAllocation,
                'resolution_details'    => $resolutionDetails,
            ]);

            // Update escrow status
            $escrow->update([
                'status'      => 'resolved',
                'is_disputed' => false,
            ]);

            // Handle fund distribution based on resolution
            $this->distributeFundsBasedOnResolution(
                $escrow,
                $resolutionType,
                $resolutionAllocation
            );

            DB::commit();

            Log::info('Dispute resolved', [
                'escrow_id'       => $escrowId,
                'dispute_id'      => $dispute->dispute_id,
                'resolution_type' => $resolutionType,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Dispute resolution failed', [
                'error'     => $e->getMessage(),
                'escrow_id' => $escrowId,
            ]);
            throw $e;
        }
    }

    /**
     * Check and expire old escrows.
     */
    public function checkAndExpireEscrows(): void
    {
        $expiredEscrows = Escrow::where('status', 'created')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredEscrows as $escrow) {
            try {
                $aggregate = EscrowAggregate::retrieve($escrow->escrow_id);
                $aggregate->expire();
                $aggregate->persist();

                // Return funds to sender using stored hold_id
                if ($escrow->funded_amount > 0 && $escrow->hold_id) {
                    $this->walletService->releaseFunds(
                        holdId: $escrow->hold_id
                    );
                }

                $escrow->update(['status' => 'expired']);

                Log::info('Escrow expired', ['escrow_id' => $escrow->escrow_id]);
            } catch (Exception $e) {
                Log::error('Failed to expire escrow', [
                    'escrow_id' => $escrow->escrow_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Determine dispute resolution method based on escrow type and amount.
     *
     * Resolution priority:
     * 1. Automated - if conditions support automatic verification
     * 2. Voting - for amounts below the voting threshold
     * 3. Arbitration - for high-value disputes
     *
     * @param Escrow $escrow The escrow being disputed
     * @return string Resolution method (automated, voting, arbitration)
     */
    private function determineResolutionMethod(Escrow $escrow): string
    {
        // Check if automated resolution is possible
        if ($this->canResolveAutomatically($escrow)) {
            return self::RESOLUTION_AUTOMATED;
        }

        // Check if community voting is available for lower amounts
        $votingThreshold = $this->getVotingThreshold();
        if ($escrow->amount < $votingThreshold) {
            return self::RESOLUTION_VOTING;
        }

        // Default to arbitration for high-value disputes
        return self::RESOLUTION_ARBITRATION;
    }

    /**
     * Check if dispute can be resolved automatically.
     */
    private function canResolveAutomatically(Escrow $escrow): bool
    {
        // Check if conditions have clear, verifiable criteria
        if (empty($escrow->conditions)) {
            return false;
        }

        // Check for automated condition types
        $automatedTypes = ['delivery_confirmed', 'payment_received', 'time_based'];
        foreach ($escrow->conditions as $condition) {
            if (isset($condition['type']) && in_array($condition['type'], $automatedTypes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initiate dispute resolution workflow.
     */
    private function initiateDisputeResolution(EscrowDispute $dispute): void
    {
        // This would trigger a Laravel Workflow for dispute resolution
        // For now, we'll log the action
        Log::info('Dispute resolution workflow initiated', [
            'dispute_id'        => $dispute->dispute_id,
            'resolution_method' => $dispute->resolution_method,
        ]);
    }

    /**
     * Distribute funds based on dispute resolution.
     */
    private function distributeFundsBasedOnResolution(
        Escrow $escrow,
        string $resolutionType,
        array $resolutionAllocation
    ): void {
        $senderWallet = $this->getSenderWallet($escrow->sender_agent_id);
        $receiverWallet = $this->getReceiverWallet($escrow->receiver_agent_id);

        // First, release the held funds back to sender's available balance
        if ($escrow->hold_id) {
            $this->walletService->releaseFunds(holdId: $escrow->hold_id);
        }

        switch ($resolutionType) {
            case 'release_to_receiver':
                // Transfer full amount to receiver
                $this->walletService->transfer(
                    fromWalletId: $senderWallet,
                    toWalletId: $receiverWallet,
                    amount: $escrow->funded_amount,
                    currency: $escrow->currency,
                    metadata: [
                        'type'            => 'dispute_resolution',
                        'resolution_type' => $resolutionType,
                    ]
                );
                break;

            case 'return_to_sender':
                // Funds already released to sender's available balance, nothing more to do
                break;

            case 'split':
                if (isset($resolutionAllocation['sender']) && isset($resolutionAllocation['receiver'])) {
                    // Funds already released to sender; transfer receiver's portion
                    $this->walletService->transfer(
                        fromWalletId: $senderWallet,
                        toWalletId: $receiverWallet,
                        amount: $resolutionAllocation['receiver'],
                        currency: $escrow->currency,
                        metadata: [
                            'type'            => 'dispute_resolution_split',
                            'resolution_type' => $resolutionType,
                        ]
                    );
                }
                break;
        }
    }

    /**
     * Get sender's wallet ID.
     *
     * @param string $agentId The agent's DID
     * @return string The wallet ID for the sender
     * @throws WalletNotFoundException If no active wallet found for agent
     */
    private function getSenderWallet(string $agentId): string
    {
        return $this->getAgentWalletId($agentId);
    }

    /**
     * Get receiver's wallet ID.
     *
     * @param string $agentId The agent's DID
     * @return string The wallet ID for the receiver
     * @throws WalletNotFoundException If no active wallet found for agent
     */
    private function getReceiverWallet(string $agentId): string
    {
        return $this->getAgentWalletId($agentId);
    }

    /**
     * Get wallet ID for an agent.
     *
     * Looks up the agent's primary active wallet from the database.
     *
     * @param string $agentId The agent's DID
     * @return string The wallet ID
     * @throws WalletNotFoundException If no active wallet found for agent
     */
    private function getAgentWalletId(string $agentId): string
    {
        $wallet = AgentWallet::where('agent_id', $agentId)
            ->where('is_active', true)
            ->first();

        if (! $wallet) {
            throw WalletNotFoundException::forAgent($agentId);
        }

        return $wallet->wallet_id;
    }

    /**
     * Complete associated transaction.
     */
    private function completeTransaction(string $transactionId, string $status): void
    {
        try {
            $aggregate = AgentTransactionAggregate::retrieve($transactionId);
            $aggregate->complete($status);
            $aggregate->persist();
        } catch (Exception $e) {
            Log::warning('Could not complete transaction', [
                'transaction_id' => $transactionId,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get available escrow types with descriptions.
     *
     * @return array<string, string> Map of type codes to descriptions
     */
    public function getEscrowTypes(): array
    {
        return config('agent_protocol.escrow.types', [
            self::TYPE_STANDARD    => 'Standard escrow with basic release conditions',
            self::TYPE_MILESTONE   => 'Milestone-based escrow with phased releases',
            self::TYPE_TIMED       => 'Time-based escrow with automatic release',
            self::TYPE_CONDITIONAL => 'Conditional escrow with complex criteria',
        ]);
    }

    /**
     * Validate escrow type against configured types.
     *
     * @param string $type Type code to validate
     * @return bool True if type is valid
     */
    public function isValidEscrowType(string $type): bool
    {
        $validTypes = array_keys($this->getEscrowTypes());

        return in_array($type, $validTypes, true);
    }
}
