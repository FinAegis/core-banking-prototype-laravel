<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\PaymentHistoryAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\DataObjects\PaymentResult;
use App\Models\AgentPayment;
use Cache;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Records payment in the event store and read models.
 */
class RecordPaymentActivity extends Activity
{
    /**
     * Execute payment recording.
     *
     * @param AgentPaymentRequest $request The payment request
     * @param PaymentResult $result The payment result
     * @return stdClass Recording result
     */
    public function execute(AgentPaymentRequest $request, PaymentResult $result): stdClass
    {
        $recordResult = new stdClass();
        $recordResult->success = false;

        try {
            // Step 1: Record in event store using aggregate
            $historyAggregate = PaymentHistoryAggregate::retrieve($request->transactionId);
            $historyAggregate->recordPayment(
                paymentId: $result->paymentId ?? $request->transactionId,
                fromAgent: $request->fromAgentDid,
                toAgent: $request->toAgentDid,
                amount: $request->amount,
                currency: $request->currency,
                status: $result->status,
                fees: $result->fees ?? 0.0,
                escrowId: $result->escrowId ?? null,
                metadata: array_merge(
                    $request->metadata,
                    [
                        'completed_at'  => $result->completedAt?->toIso8601String(),
                        'payment_type'  => $request->paymentType,
                        'has_escrow'    => $request->requiresEscrow(),
                        'has_splits'    => $request->hasSplits(),
                        'error_message' => $result->errorMessage ?? null,
                    ]
                )
            );
            $historyAggregate->persist();

            // Step 2: Create read model for quick queries
            $paymentModel = AgentPayment::create([
                'transaction_id' => $request->transactionId,
                'payment_id'     => $result->paymentId ?? $request->transactionId,
                'from_agent_did' => $request->fromAgentDid,
                'to_agent_did'   => $request->toAgentDid,
                'amount'         => $request->amount,
                'currency'       => $request->currency,
                'status'         => $result->status,
                'payment_type'   => $request->paymentType,
                'fees'           => $result->fees ?? 0.0,
                'escrow_id'      => $result->escrowId ?? null,
                'metadata'       => json_encode($request->metadata),
                'completed_at'   => $result->completedAt ?? null,
                'failed_at'      => $result->failedAt ?? null,
                'error_message'  => $result->errorMessage ?? null,
            ]);

            // Step 3: Handle split payment records
            if ($request->hasSplits() && isset($result->splitResults)) {
                foreach ($result->splitResults as $splitResult) {
                    if ($splitResult instanceof PaymentResult) {
                        $splitModel = AgentPayment::create([
                            'parent_payment_id' => $paymentModel->id,
                            'transaction_id'    => $splitResult->transactionId,
                            'payment_id'        => $splitResult->paymentId ?? $splitResult->transactionId,
                            'from_agent_did'    => $request->fromAgentDid,
                            'to_agent_did'      => $splitResult->toAgentDid ?? 'unknown',
                            'amount'            => $splitResult->amount ?? 0,
                            'currency'          => $request->currency,
                            'status'            => $splitResult->status,
                            'payment_type'      => 'split',
                            'fees'              => $splitResult->fees ?? 0.0,
                            'completed_at'      => $splitResult->completedAt ?? null,
                        ]);
                    }
                }
            }

            // Step 4: Update analytics and metrics
            $this->updatePaymentMetrics($request, $result);

            $recordResult->success = true;
            $recordResult->recordId = $paymentModel->id;
            $recordResult->timestamp = now()->toIso8601String();

            logger()->info('Payment recorded successfully', [
                'record_id'      => $recordResult->recordId,
                'transaction_id' => $request->transactionId,
                'payment_id'     => $result->paymentId,
                'status'         => $result->status,
            ]);
        } catch (Exception $e) {
            $recordResult->success = false;
            $recordResult->errorMessage = $e->getMessage();

            logger()->error('Payment recording failed', [
                'transaction_id' => $request->transactionId,
                'error'          => $e->getMessage(),
            ]);

            // Recording failures should not fail the payment
            // Just log and continue
        }

        return $recordResult;
    }

    /**
     * Update payment metrics for analytics.
     */
    private function updatePaymentMetrics(AgentPaymentRequest $request, PaymentResult $result): void
    {
        try {
            // Update daily volume metrics
            Cache::increment('metrics:daily:payment_count:' . now()->format('Y-m-d'));
            Cache::increment(
                'metrics:daily:payment_volume:' . now()->format('Y-m-d'),
                (int) ($request->amount * 100) // Store in cents
            );

            // Update agent-specific metrics
            Cache::increment('metrics:agent:payments_sent:' . $request->fromAgentDid);
            Cache::increment('metrics:agent:payments_received:' . $request->toAgentDid);

            // Update success/failure rates
            if ($result->status === 'completed') {
                Cache::increment('metrics:payment:success_count');
            } else {
                Cache::increment('metrics:payment:failure_count');
            }

            // Store payment type distribution
            Cache::increment('metrics:payment:type:' . $request->paymentType);
        } catch (Exception $e) {
            // Metrics updates are non-critical
            logger()->warning('Failed to update payment metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
