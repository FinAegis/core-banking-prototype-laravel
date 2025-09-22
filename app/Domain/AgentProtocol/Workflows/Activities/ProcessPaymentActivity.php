<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use DB;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Processes the actual payment transfer between agents.
 */
class ProcessPaymentActivity extends Activity
{
    public function execute(AgentPaymentRequest $request): stdClass
    {
        $result = new stdClass();
        $result->success = false;

        DB::beginTransaction();

        try {
            // Process payment using initiatePayment from sender
            $senderWallet = AgentWalletAggregate::retrieve($request->fromAgentDid);
            $senderWallet->initiatePayment(
                transactionId: $request->transactionId,
                toAgentId: $request->toAgentDid,
                amount: $request->amount,
                type: $request->paymentType,
                metadata: array_merge($request->metadata, [
                    'payment_type' => $request->paymentType,
                    'currency'     => $request->currency,
                ])
            );
            $senderWallet->persist();

            // Credit to recipient using receivePayment
            $recipientWallet = AgentWalletAggregate::retrieve($request->toAgentDid);
            $recipientWallet->receivePayment(
                transactionId: $request->transactionId,
                fromAgentId: $request->fromAgentDid,
                amount: $request->amount,
                metadata: array_merge($request->metadata, [
                    'payment_type' => $request->paymentType,
                    'currency'     => $request->currency,
                ])
            );
            $recipientWallet->persist();

            // Process splits if any
            if (! empty($request->splits)) {
                foreach ($request->splits as $split) {
                    $splitWallet = AgentWalletAggregate::retrieve($split['agentDid']);
                    $splitWallet->receivePayment(
                        transactionId: $request->transactionId . '-split-' . $split['agentDid'],
                        fromAgentId: $request->toAgentDid,
                        amount: $split['amount'],
                        metadata: [
                            'split_type'         => $split['type'] ?? 'revenue_share',
                            'parent_transaction' => $request->transactionId,
                        ]
                    );
                    $splitWallet->persist();
                }
            }

            DB::commit();

            $result->success = true;
            $result->transactionId = $request->transactionId;
            $result->amount = $request->amount;
            $result->currency = $request->currency;
            $result->processedAt = now()->toIso8601String();
            $result->status = 'completed';
        } catch (Exception $e) {
            DB::rollBack();

            $result->success = false;
            $result->errorMessage = $e->getMessage();
            $result->status = 'failed';

            logger()->error('Payment processing failed', [
                'transaction_id' => $request->transactionId,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }

        return $result;
    }
}
