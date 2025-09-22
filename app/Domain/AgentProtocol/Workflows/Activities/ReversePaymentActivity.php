<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\DataObjects\PaymentResult;
use DB;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Reverses a payment transaction as part of compensation.
 */
class ReversePaymentActivity extends Activity
{
    public function execute(
        AgentPaymentRequest $originalRequest,
        PaymentResult $paymentResult
    ): stdClass {
        $result = new stdClass();
        $result->success = false;

        DB::beginTransaction();

        try {
            // Reverse by having the original recipient send back to sender
            $recipientWallet = AgentWalletAggregate::retrieve($originalRequest->toAgentDid);
            $recipientWallet->initiatePayment(
                transactionId: 'reversal-' . $originalRequest->transactionId,
                toAgentId: $originalRequest->fromAgentDid,
                amount: $originalRequest->amount,
                type: 'reversal',
                metadata: [
                    'original_transaction' => $originalRequest->transactionId,
                    'reversal_reason'      => $paymentResult->errorMessage ?? 'compensation',
                    'currency'             => $originalRequest->currency,
                ]
            );
            $recipientWallet->persist();

            // Credit back to original sender
            $senderWallet = AgentWalletAggregate::retrieve($originalRequest->fromAgentDid);
            $senderWallet->receivePayment(
                transactionId: 'reversal-' . $originalRequest->transactionId,
                fromAgentId: $originalRequest->toAgentDid,
                amount: $originalRequest->amount,
                metadata: [
                    'original_transaction' => $originalRequest->transactionId,
                    'reversal_reason'      => $paymentResult->errorMessage ?? 'compensation',
                    'currency'             => $originalRequest->currency,
                ]
            );
            $senderWallet->persist();

            // Reverse splits if any
            if (! empty($originalRequest->splits)) {
                foreach ($originalRequest->splits as $split) {
                    $splitWallet = AgentWalletAggregate::retrieve($split['agentDid']);
                    // Split recipient sends back their portion
                    $splitWallet->initiatePayment(
                        transactionId: 'reversal-split-' . $originalRequest->transactionId . '-' . $split['agentDid'],
                        toAgentId: $originalRequest->toAgentDid,
                        amount: $split['amount'],
                        type: 'split_reversal',
                        metadata: [
                            'original_transaction' => $originalRequest->transactionId,
                            'split_reversal'       => true,
                        ]
                    );
                    $splitWallet->persist();

                    // Original recipient receives back the split amount
                    $recipientWallet = AgentWalletAggregate::retrieve($originalRequest->toAgentDid);
                    $recipientWallet->receivePayment(
                        transactionId: 'reversal-split-' . $originalRequest->transactionId . '-' . $split['agentDid'],
                        fromAgentId: $split['agentDid'],
                        amount: $split['amount'],
                        metadata: [
                            'original_transaction' => $originalRequest->transactionId,
                            'split_reversal'       => true,
                        ]
                    );
                    $recipientWallet->persist();
                }
            }

            DB::commit();

            $result->success = true;
            $result->reversalId = 'reversal-' . $originalRequest->transactionId;
            $result->originalTransactionId = $originalRequest->transactionId;
            $result->amount = $originalRequest->amount;
            $result->reversedAt = now()->toIso8601String();
            $result->status = 'reversed';
        } catch (Exception $e) {
            DB::rollBack();

            $result->success = false;
            $result->errorMessage = $e->getMessage();
            $result->status = 'reversal_failed';

            logger()->error('Payment reversal failed', [
                'original_transaction' => $originalRequest->transactionId,
                'error'                => $e->getMessage(),
            ]);

            throw $e;
        }

        return $result;
    }
}
