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
 * Applies fees to a payment transaction.
 */
class ApplyFeesActivity extends Activity
{
    public function execute(AgentPaymentRequest $request, array $options = []): stdClass
    {
        $result = new stdClass();
        $result->success = false;

        try {
            $isReversal = $options['reverse'] ?? false;

            // Get fee configuration
            $feeRate = config('agent_protocol.fees.standard_rate', 0.025);
            $minFee = config('agent_protocol.fees.minimum_fee', 0.50);
            $maxFee = config('agent_protocol.fees.maximum_fee', 100.00);

            // Calculate fees
            $calculatedFee = $request->amount * $feeRate;
            $appliedFee = max($minFee, min($calculatedFee, $maxFee));

            // Apply any custom fee overrides
            if (isset($request->metadata['custom_fee_rate'])) {
                $customRate = (float) $request->metadata['custom_fee_rate'];
                if ($customRate >= 0 && $customRate <= 0.10) { // Max 10% fee
                    $appliedFee = $request->amount * $customRate;
                }
            }

            // Fee exemptions
            if ($this->isExemptFromFees($request)) {
                $appliedFee = 0.0;
            }

            $result->calculatedFee = $calculatedFee;
            $result->appliedFee = $appliedFee;
            $result->feeRate = $appliedFee > 0 ? ($appliedFee / $request->amount) : 0.0;
            $result->totalAmount = $request->amount + $appliedFee;

            if ($appliedFee > 0) {
                DB::beginTransaction();

                try {
                    if (! $isReversal) {
                        // Charge fee from sender using initiatePayment
                        $feeCollectorDid = config('agent_protocol.fees.fee_collector_did', 'did:agent:finaegis:fee-collector');
                        $senderWallet = AgentWalletAggregate::retrieve($request->fromAgentDid);
                        $senderWallet->initiatePayment(
                            transactionId: 'fee-' . $request->transactionId,
                            toAgentId: $feeCollectorDid,
                            amount: $appliedFee,
                            type: 'fee',
                            metadata: [
                                'fee_type'       => 'transaction',
                                'fee_rate'       => $result->feeRate,
                                'payment_amount' => $request->amount,
                                'description'    => 'Transaction fee for payment to ' . $request->toAgentDid,
                            ]
                        );
                        $senderWallet->persist();

                        // Credit fee to collector using receivePayment
                        $feeCollector = AgentWalletAggregate::retrieve($feeCollectorDid);
                        $feeCollector->receivePayment(
                            transactionId: 'fee-' . $request->transactionId,
                            fromAgentId: $request->fromAgentDid,
                            amount: $appliedFee,
                            metadata: [
                                'fee_type'       => 'transaction',
                                'sender'         => $request->fromAgentDid,
                                'payment_amount' => $request->amount,
                            ]
                        );
                        $feeCollector->persist();
                    } else {
                        // Reverse fee: return to sender using receivePayment
                        $feeCollectorDid = config('agent_protocol.fees.fee_collector_did', 'did:agent:finaegis:fee-collector');
                        $senderWallet = AgentWalletAggregate::retrieve($request->fromAgentDid);
                        $senderWallet->receivePayment(
                            transactionId: 'fee-reversal-' . $request->transactionId,
                            fromAgentId: $feeCollectorDid,
                            amount: $appliedFee,
                            metadata: [
                                'fee_type'       => 'reversal',
                                'original_fee'   => $appliedFee,
                                'payment_amount' => $request->amount,
                                'description'    => 'Fee reversal for cancelled payment',
                            ]
                        );
                        $senderWallet->persist();

                        // Debit from fee collector using initiatePayment (as a refund)
                        $feeCollector = AgentWalletAggregate::retrieve($feeCollectorDid);
                        $feeCollector->initiatePayment(
                            transactionId: 'fee-reversal-' . $request->transactionId,
                            toAgentId: $request->fromAgentDid,
                            amount: $appliedFee,
                            type: 'fee_refund',
                            metadata: [
                                'fee_type'       => 'reversal',
                                'sender'         => $request->fromAgentDid,
                                'payment_amount' => $request->amount,
                                'description'    => 'Fee reversal to ' . $request->fromAgentDid,
                            ]
                        );
                        $feeCollector->persist();
                    }

                    DB::commit();
                    $result->success = true;
                    $result->status = $isReversal ? 'reversed' : 'applied';
                } catch (Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } else {
                // No fee to apply
                $result->success = true;
                $result->status = 'exempt';
            }

            $result->totalFees = $appliedFee;
            $result->netAmount = $request->amount;
        } catch (Exception $e) {
            $result->success = false;
            $result->errorMessage = $e->getMessage();

            logger()->error('Fee application failed', [
                'transaction_id' => $request->transactionId,
                'error'          => $e->getMessage(),
            ]);

            throw $e;
        }

        return $result;
    }

    /**
     * Check if a payment is exempt from fees.
     */
    private function isExemptFromFees(AgentPaymentRequest $request): bool
    {
        // System accounts are exempt
        $systemAccounts = [
            config('agent_protocol.system_agents.system_did'),
            config('agent_protocol.system_agents.treasury_did'),
            config('agent_protocol.system_agents.reserve_did'),
        ];

        if (
            in_array($request->fromAgentDid, $systemAccounts) ||
            in_array($request->toAgentDid, $systemAccounts)
        ) {
            return true;
        }

        // Check for exemption flag in metadata
        if (
            isset($request->metadata['fee_exempt']) &&
            $request->metadata['fee_exempt'] === true
        ) {
            return true;
        }

        // Micro-transactions below a threshold
        $exemptionThreshold = config('agent_protocol.fees.exemption_threshold', 1.00);
        if ($request->amount < $exemptionThreshold) {
            return true;
        }

        return false;
    }
}
