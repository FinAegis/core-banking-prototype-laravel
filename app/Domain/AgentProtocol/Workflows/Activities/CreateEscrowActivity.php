<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Creates an escrow for secure payment holding.
 */
class CreateEscrowActivity extends Activity
{
    private const DEFAULT_TIMEOUT = 3600; // 1 hour default

    private const MAX_TIMEOUT = 86400; // 24 hours max

    /**
     * Execute escrow creation.
     *
     * @param string $escrowId The escrow ID
     * @param AgentPaymentRequest $request The payment request
     * @param stdClass $paymentResult The payment result
     * @return stdClass Escrow creation result
     */
    public function execute(
        string $escrowId,
        AgentPaymentRequest $request,
        stdClass $paymentResult
    ): stdClass {
        $result = new stdClass();
        $result->success = false;

        try {
            // Determine escrow timeout
            $timeout = self::DEFAULT_TIMEOUT;
            if (isset($request->escrowConditions)) {
                foreach ($request->escrowConditions as $condition) {
                    if ($condition['type'] === 'time_based' && isset($condition['timeout'])) {
                        $timeout = min((int) $condition['timeout'], self::MAX_TIMEOUT);
                        break;
                    }
                }
            }

            // Calculate expiration time
            $expiresAt = now()->addSeconds($timeout)->toIso8601String();

            // Create escrow aggregate
            $escrow = EscrowAggregate::create(
                escrowId: $escrowId,
                transactionId: $request->transactionId,
                senderAgentId: $request->fromAgentDid,
                receiverAgentId: $request->toAgentDid,
                amount: $request->amount,
                currency: $request->currency,
                conditions: $request->escrowConditions ?? [],
                expiresAt: $expiresAt,
                metadata: [
                    'payment_id'   => $paymentResult->paymentId ?? null,
                    'payment_type' => $request->paymentType,
                    'created_by'   => 'payment_workflow',
                    'created_at'   => now()->toIso8601String(),
                ]
            );

            // Fund the escrow
            $escrow->deposit(
                amount: $request->amount,
                depositedBy: $request->fromAgentDid,
                depositDetails: [
                    'source'         => 'payment_workflow',
                    'payment_result' => $paymentResult,
                ]
            );

            $escrow->persist();

            $result->success = true;
            $result->escrowId = $escrowId;
            $result->status = 'funded';
            $result->amount = $request->amount;
            $result->expiresAt = $expiresAt;
            $result->timeout = $timeout;
            $result->conditions = $request->escrowConditions ?? [];

            logger()->info('Escrow created successfully', [
                'escrow_id'      => $escrowId,
                'transaction_id' => $request->transactionId,
                'amount'         => $request->amount,
                'expires_at'     => $expiresAt,
            ]);
        } catch (Exception $e) {
            $result->success = false;
            $result->errorMessage = $e->getMessage();

            logger()->error('Escrow creation failed', [
                'escrow_id'      => $escrowId,
                'transaction_id' => $request->transactionId,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }

        return $result;
    }
}
