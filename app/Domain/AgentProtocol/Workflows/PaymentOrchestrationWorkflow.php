<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows;

use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\DataObjects\PaymentResult;
use App\Domain\AgentProtocol\Workflows\Activities\ApplyFeesActivity;
use App\Domain\AgentProtocol\Workflows\Activities\CheckTransactionLimitActivity;
use App\Domain\AgentProtocol\Workflows\Activities\NotifyAgentsActivity;
use App\Domain\AgentProtocol\Workflows\Activities\ProcessPaymentActivity;
use App\Domain\AgentProtocol\Workflows\Activities\RecordPaymentActivity;
use App\Domain\AgentProtocol\Workflows\Activities\ReversePaymentActivity;
use App\Domain\AgentProtocol\Workflows\Activities\ValidatePaymentActivity;
use Exception;
use Generator;
use stdClass;
use Throwable;
use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

/**
 * Orchestrates agent-to-agent payment processing with compensation support.
 *
 * This workflow handles the complete lifecycle of an agent payment including:
 * - Validation of payment request
 * - Fee calculation and application
 * - Payment processing with escrow support
 * - Notification of involved parties
 * - Automatic compensation on failure
 */
class PaymentOrchestrationWorkflow extends Workflow
{
    private PaymentResult $result;

    /**
     * Execute the payment orchestration workflow.
     *
     * @param AgentPaymentRequest $request The payment request details
     * @return Generator The payment result generator
     * @throws Throwable If payment processing fails
     */
    public function execute(AgentPaymentRequest $request): Generator
    {
        $this->result = new PaymentResult(
            transactionId: $request->transactionId,
            status: 'pending',
            timestamp: now()
        );

        try {
            // Step 1: Validate the payment request
            $validationResult = yield ActivityStub::make(
                ValidatePaymentActivity::class,
                $request
            );

            if (! $validationResult->isValid) {
                $this->result->status = 'failed';
                $this->result->errorMessage = $validationResult->errorMessage;

                return $this->result;
            }

            // Step 2: Check transaction limits for sender
            $limitCheckResult = yield ActivityStub::make(
                CheckTransactionLimitActivity::class,
                $request->fromAgentDid,
                $request->amount,
                $request->currency
            );

            if (! $limitCheckResult->allowed) {
                $this->result->status = 'failed';
                $this->result->errorMessage = $limitCheckResult->reason;
                $this->result->limitDetails = [
                    'period'          => $limitCheckResult->period ?? null,
                    'limit'           => $limitCheckResult->limit ?? null,
                    'currentTotal'    => $limitCheckResult->currentTotal ?? null,
                    'requestedAmount' => $request->amount,
                ];

                return $this->result;
            }

            // Step 3: Apply fees if applicable
            if ($request->requiresFees()) {
                /** @var stdClass $feeResult */
                $feeResult = yield ActivityStub::make(
                    ApplyFeesActivity::class,
                    $request
                );

                // Add compensation for fee reversal
                $this->addCompensation(
                    fn () => ActivityStub::make(
                        ApplyFeesActivity::class,
                        $request,
                        ['reverse' => true]
                    )
                );

                $this->result->fees = $feeResult->totalFees;
            }

            // Step 4: Process the main payment
            /** @var stdClass $paymentResult */
            $paymentResult = yield ActivityStub::make(
                ProcessPaymentActivity::class,
                $request
            );

            // Add compensation for payment reversal
            $this->addCompensation(
                fn () => ActivityStub::make(
                    ReversePaymentActivity::class,
                    $request,
                    $paymentResult
                )
            );

            $this->result->paymentId = $paymentResult->paymentId;
            $this->result->status = 'processing';

            // Step 5: Handle escrow if required
            if ($request->requiresEscrow()) {
                $escrowResult = yield ChildWorkflowStub::make(
                    EscrowWorkflow::class,
                    $request,
                    $paymentResult
                );

                $this->result->escrowId = $escrowResult->escrowId;

                // Add compensation for escrow cancellation
                $this->addCompensation(
                    fn () => ChildWorkflowStub::make(
                        EscrowWorkflow::class,
                        $request,
                        $escrowResult,
                        ['action' => 'cancel']
                    )
                );
            }

            // Step 6: Record the payment in event store
            yield ActivityStub::make(
                RecordPaymentActivity::class,
                $request,
                $this->result
            );

            // Step 7: Notify involved agents
            yield ActivityStub::make(
                NotifyAgentsActivity::class,
                $request,
                $this->result
            );

            // Mark as completed
            $this->result->status = 'completed';
            $this->result->completedAt = now();
        } catch (Throwable $exception) {
            // Execute compensation activities
            yield from $this->compensate();

            // Update result with failure details
            $this->result->status = 'failed';
            $this->result->errorMessage = $exception->getMessage();
            $this->result->failedAt = now();

            // Log the failure for monitoring
            $this->logFailure($exception, $request);

            throw $exception;
        }

        return $this->result;
    }

    /**
     * Handle split payments for multiple recipients.
     *
     * @param AgentPaymentRequest $request The payment request with splits
     * @return Generator The aggregated payment result generator
     */
    public function executeSplitPayment(AgentPaymentRequest $request): Generator
    {
        if (! $request->hasSplits()) {
            throw new Exception('Payment request does not contain split information');
        }

        $splitResults = [];

        try {
            // Validate the split configuration
            $validationResult = yield ActivityStub::make(
                ValidatePaymentActivity::class,
                $request,
                ['validateSplits' => true]
            );

            if (! $validationResult->isValid) {
                $this->result->status = 'failed';
                $this->result->errorMessage = $validationResult->errorMessage;

                return $this->result;
            }

            // Process each split payment
            foreach ($request->splits as $split) {
                $splitRequest = $request->createSplitRequest($split);

                $splitResult = yield ChildWorkflowStub::make(
                    self::class,
                    $splitRequest
                );

                $splitResults[] = $splitResult;

                // Add compensation for each split
                $this->addCompensation(
                    fn () => ActivityStub::make(
                        ReversePaymentActivity::class,
                        $splitRequest,
                        $splitResult
                    )
                );
            }

            // Aggregate results
            $this->result->status = 'completed';
            $this->result->splitResults = $splitResults;
            $this->result->completedAt = now();
        } catch (Throwable $exception) {
            // Execute compensation for all processed splits
            yield from $this->compensate();

            $this->result->status = 'failed';
            $this->result->errorMessage = 'Split payment failed: ' . $exception->getMessage();
            $this->result->failedAt = now();

            throw $exception;
        }

        return $this->result;
    }

    /**
     * Handle retry logic for failed payments.
     *
     * @param AgentPaymentRequest $request The payment request
     * @param int $maxRetries Maximum retry attempts
     * @return Generator The payment result generator
     */
    public function executeWithRetry(
        AgentPaymentRequest $request,
        int $maxRetries = 3
    ): Generator {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxRetries) {
            try {
                $attempts++;

                // Add exponential backoff for retries
                if ($attempts > 1) {
                    // In production, this would use a proper timer activity
                    // For now, we'll continue without delay
                }

                // Attempt the payment
                $result = yield from $this->execute($request);

                // Success - return the result
                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;

                // Check if error is retryable
                if (! $this->isRetryableError($exception)) {
                    throw $exception;
                }

                // Log retry attempt
                $this->logRetryAttempt($attempts, $exception, $request);
            }
        }

        // All retries exhausted
        $this->result->status = 'failed';
        $this->result->errorMessage = 'Payment failed after ' . $maxRetries . ' attempts';
        $this->result->lastError = $lastException?->getMessage();
        $this->result->failedAt = now();

        throw new Exception(
            'Payment failed after maximum retry attempts',
            0,
            $lastException
        );
    }

    /**
     * Determine if an error is retryable.
     */
    private function isRetryableError(Throwable $exception): bool
    {
        // Network errors, timeouts, and temporary failures are retryable
        $retryableErrors = [
            'TimeoutException',
            'NetworkException',
            'TemporaryFailureException',
            'RateLimitException',
        ];

        foreach ($retryableErrors as $errorType) {
            if (str_contains(get_class($exception), $errorType)) {
                return true;
            }
        }

        // Check error message for retryable patterns
        $retryableMessages = [
            'timeout',
            'temporary',
            'rate limit',
            'try again',
        ];

        $message = strtolower($exception->getMessage());
        foreach ($retryableMessages as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log payment failure for monitoring.
     */
    private function logFailure(Throwable $exception, AgentPaymentRequest $request): void
    {
        // Log to monitoring system
        logger()->error('Agent payment failed', [
            'transaction_id' => $request->transactionId,
            'from_agent'     => $request->fromAgentDid,
            'to_agent'       => $request->toAgentDid,
            'amount'         => $request->amount,
            'error'          => $exception->getMessage(),
            'trace'          => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Log retry attempt for monitoring.
     */
    private function logRetryAttempt(
        int $attempt,
        Throwable $exception,
        AgentPaymentRequest $request
    ): void {
        logger()->warning('Agent payment retry attempt', [
            'attempt'        => $attempt,
            'transaction_id' => $request->transactionId,
            'error'          => $exception->getMessage(),
        ]);
    }
}
