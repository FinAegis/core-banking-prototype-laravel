<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows;

use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\DataObjects\EscrowResult;
use App\Domain\AgentProtocol\DataObjects\PaymentResult;
use App\Domain\AgentProtocol\Workflows\Activities\CreateEscrowActivity;
use App\Domain\AgentProtocol\Workflows\Activities\NotifyEscrowStatusActivity;
use App\Domain\AgentProtocol\Workflows\Activities\ReleaseEscrowActivity;
use App\Domain\AgentProtocol\Workflows\Activities\ValidateEscrowActivity;
use Exception;
use Generator;
use Illuminate\Support\Str;
use stdClass;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

/**
 * Manages escrow lifecycle for agent-to-agent payments.
 *
 * This workflow handles:
 * - Escrow creation and funding
 * - Condition monitoring
 * - Fund release or return
 * - Dispute handling
 * - Timeout management
 */
class EscrowWorkflow extends Workflow
{
    private EscrowResult $result;

    private string $escrowId;

    /**
     * Create and manage an escrow for a payment.
     *
     * @param AgentPaymentRequest $request The payment request
     * @param PaymentResult $paymentResult The payment processing result
     * @param array $options Additional options (e.g., ['action' => 'cancel'])
     * @return Generator The escrow result generator
     */
    public function execute(
        AgentPaymentRequest $request,
        PaymentResult $paymentResult,
        array $options = []
    ): Generator {
        $this->escrowId = Str::uuid()->toString();
        $this->result = new EscrowResult(
            escrowId: $this->escrowId,
            status: 'pending',
            createdAt: now()
        );

        try {
            // Handle cancellation request
            if (($options['action'] ?? null) === 'cancel') {
                return yield from $this->cancelEscrow($request, $paymentResult);
            }

            // Step 1: Validate escrow requirements
            /** @var stdClass $validationResult */
            $validationResult = yield ActivityStub::make(
                ValidateEscrowActivity::class,
                $request,
                $paymentResult
            );

            if (! $validationResult->isValid) {
                $this->result->status = 'rejected';
                $this->result->errorMessage = $validationResult->errorMessage;

                return $this->result;
            }

            // Step 2: Create the escrow
            /** @var stdClass $escrowCreation */
            $escrowCreation = yield ActivityStub::make(
                CreateEscrowActivity::class,
                $this->escrowId,
                $request,
                $paymentResult
            );

            $this->result->fundedAt = now();
            $this->result->status = 'funded';

            // Step 3: Monitor escrow conditions
            /** @var bool $releaseConditionsMet */
            $releaseConditionsMet = yield from $this->monitorConditions(
                $request,
                $escrowCreation->timeout
            );

            if ($releaseConditionsMet) {
                // Step 4a: Release funds to recipient
                yield from $this->releaseFunds($request, $paymentResult);
            } else {
                // Step 4b: Handle timeout or dispute
                yield from $this->handleEscrowFailure($request, $paymentResult);
            }
        } catch (Throwable $exception) {
            $this->result->status = 'error';
            $this->result->errorMessage = $exception->getMessage();
            $this->result->failedAt = now();

            // Attempt to return funds on error
            yield from $this->returnFundsToSender($request, $paymentResult);

            throw $exception;
        }

        return $this->result;
    }

    /**
     * Monitor escrow release conditions.
     *
     * @param AgentPaymentRequest $request The payment request
     * @param int $timeoutSeconds Timeout in seconds
     * @return Generator Whether conditions were met
     */
    private function monitorConditions(
        AgentPaymentRequest $request,
        int $timeoutSeconds
    ): Generator {
        $startTime = now();
        $checkInterval = 10; // Check every 10 seconds

        while (true) {
            // Check if timeout exceeded
            if (now()->diffInSeconds($startTime) > $timeoutSeconds) {
                return false;
            }

            // Check release conditions
            $aggregate = EscrowAggregate::retrieve($this->escrowId);

            if ($aggregate->isReadyForRelease()) {
                return true;
            }

            if ($aggregate->isDisputed()) {
                // Handle dispute resolution
                yield from $this->resolveDispute($request);

                return $aggregate->isResolvedInFavorOfRecipient();
            }

            // Wait before next check
            // Laravel Workflow doesn't have Activity::timer yet, use workaround
            yield from $this->waitInterval($checkInterval);
        }
    }

    /**
     * Release escrowed funds to recipient.
     */
    private function releaseFunds(
        AgentPaymentRequest $request,
        PaymentResult $paymentResult
    ): Generator {
        /** @var stdClass $releaseResult */
        $releaseResult = yield ActivityStub::make(
            ReleaseEscrowActivity::class,
            $this->escrowId,
            $request->toAgentDid,
            $paymentResult->amount
        );

        $this->result->status = 'released';
        $this->result->releasedAt = now();
        $this->result->releasedTo = $request->toAgentDid;

        // Notify parties
        yield ActivityStub::make(
            NotifyEscrowStatusActivity::class,
            $this->escrowId,
            'released',
            [$request->fromAgentDid, $request->toAgentDid]
        );
    }

    /**
     * Handle escrow failure (timeout or unmet conditions).
     */
    private function handleEscrowFailure(
        AgentPaymentRequest $request,
        PaymentResult $paymentResult
    ): Generator {
        $this->result->status = 'expired';
        $this->result->expiredAt = now();

        // Return funds to sender
        yield from $this->returnFundsToSender($request, $paymentResult);

        // Notify parties
        yield ActivityStub::make(
            NotifyEscrowStatusActivity::class,
            $this->escrowId,
            'expired',
            [$request->fromAgentDid, $request->toAgentDid]
        );
    }

    /**
     * Return funds to sender.
     */
    private function returnFundsToSender(
        AgentPaymentRequest $request,
        PaymentResult $paymentResult
    ): Generator {
        try {
            /** @var stdClass $returnResult */
            $returnResult = yield ActivityStub::make(
                ReleaseEscrowActivity::class,
                $this->escrowId,
                $request->fromAgentDid,
                $paymentResult->amount,
                ['reason' => 'return_to_sender']
            );

            $this->result->fundsReturned = true;
            $this->result->returnedAt = now();

            // Notify sender
            yield ActivityStub::make(
                NotifyEscrowStatusActivity::class,
                $this->escrowId,
                'returned',
                [$request->fromAgentDid]
            );
        } catch (Throwable $exception) {
            // Log critical error - funds may be stuck
            logger()->critical('Failed to return escrow funds', [
                'escrow_id' => $this->escrowId,
                'sender'    => $request->fromAgentDid,
                'amount'    => $paymentResult->amount,
                'error'     => $exception->getMessage(),
            ]);

            throw new Exception(
                'Critical: Failed to return escrow funds',
                0,
                $exception
            );
        }
    }

    /**
     * Cancel an escrow.
     */
    private function cancelEscrow(
        AgentPaymentRequest $request,
        PaymentResult $paymentResult
    ): Generator {
        // Update escrow aggregate
        $aggregate = EscrowAggregate::retrieve($this->escrowId);
        $aggregate->cancel($request->fromAgentDid, 'Workflow cancellation');
        $aggregate->persist();

        // Return funds if already deposited
        if ($aggregate->isFunded()) {
            yield from $this->returnFundsToSender($request, $paymentResult);
        }

        $this->result->status = 'cancelled';
        $this->result->cancelledAt = now();

        // Notify parties
        yield ActivityStub::make(
            NotifyEscrowStatusActivity::class,
            $this->escrowId,
            'cancelled',
            [$request->fromAgentDid, $request->toAgentDid]
        );

        return $this->result;
    }

    /**
     * Resolve a dispute through arbitration.
     */
    private function resolveDispute(AgentPaymentRequest $request): Generator
    {
        // This would integrate with a dispute resolution service
        // For now, we'll implement a simple timeout-based resolution

        $disputeTimeout = 3600; // 1 hour for dispute resolution
        $startTime = now();

        while (true) {
            $aggregate = EscrowAggregate::retrieve($this->escrowId);

            if ($aggregate->isDisputeResolved()) {
                return;
            }

            if (now()->diffInSeconds($startTime) > $disputeTimeout) {
                // Auto-resolve in favor of sender after timeout
                $aggregate->resolveDispute(
                    resolvedBy: 'system',
                    resolutionType: 'return_to_sender',
                    resolutionAllocation: ['sender' => 100.0, 'receiver' => 0.0],
                    resolutionDetails: ['reason' => 'Dispute timeout - funds returned to sender']
                );
                $aggregate->persist();

                return;
            }

            // Check every minute for resolution
            yield from $this->waitInterval(60);
        }
    }

    /**
     * Wait for a specified interval.
     * Workaround for missing Activity::timer in Laravel Workflow.
     *
     * @param int $seconds Seconds to wait
     * @return Generator
     */
    private function waitInterval(int $seconds): Generator
    {
        // In production, this would use a proper timer activity
        // For now, we'll yield control back to the workflow engine
        // The workflow engine should handle the timing
        yield null; // Yield control to workflow engine

        // This is a placeholder - in actual Laravel Workflow implementation,
        // timing would be handled by the workflow engine itself
    }
}
