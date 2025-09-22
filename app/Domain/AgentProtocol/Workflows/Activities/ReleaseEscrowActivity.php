<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Aggregates\AgentWalletAggregate;
use App\Domain\AgentProtocol\Aggregates\EscrowAggregate;
use DB;
use Exception;
use stdClass;
use Workflow\Activity;

/**
 * Releases funds from escrow to a specified recipient.
 */
class ReleaseEscrowActivity extends Activity
{
    /**
     * Execute escrow release.
     *
     * @param string $escrowId The escrow ID
     * @param string $recipientDid The recipient agent DID
     * @param float $amount The amount to release
     * @param array $options Additional options
     * @return stdClass Release result
     */
    public function execute(
        string $escrowId,
        string $recipientDid,
        float $amount,
        array $options = []
    ): stdClass {
        $result = new stdClass();
        $result->success = false;

        try {
            DB::beginTransaction();

            // Load escrow aggregate
            $escrow = EscrowAggregate::retrieve($escrowId);

            // Verify escrow state
            if (! $escrow->isFunded()) {
                throw new Exception('Escrow is not funded');
            }

            if ($escrow->getFundedAmount() < $amount) {
                throw new Exception('Insufficient funds in escrow');
            }

            $reason = $options['reason'] ?? 'conditions_met';
            $releaseDetails = [
                'released_at' => now()->toIso8601String(),
                'recipient'   => $recipientDid,
                'amount'      => $amount,
                'reason'      => $reason,
                'workflow'    => 'payment_orchestration',
            ];

            // Release funds from escrow
            $escrow->release(
                releasedBy: 'system',
                reason: $reason,
                releaseDetails: $releaseDetails
            );
            $escrow->persist();

            // Credit recipient's wallet using receivePayment
            $recipientWallet = AgentWalletAggregate::retrieve($recipientDid);
            $recipientWallet->receivePayment(
                transactionId: 'escrow-release-' . $escrowId,
                fromAgentId: $escrow->getSenderAgentId(),
                amount: $amount,
                metadata: [
                    'escrow_id'      => $escrowId,
                    'release_reason' => $reason,
                    'original_tx'    => $escrow->getTransactionId(),
                    'description'    => 'Escrow release from ' . $escrow->getSenderAgentId(),
                ]
            );
            $recipientWallet->persist();

            DB::commit();

            $result->success = true;
            $result->escrowId = $escrowId;
            $result->recipientDid = $recipientDid;
            $result->amount = $amount;
            $result->reason = $reason;
            $result->releasedAt = now()->toIso8601String();
            $result->newBalance = $recipientWallet->getBalance();

            logger()->info('Escrow released successfully', [
                'escrow_id' => $escrowId,
                'recipient' => $recipientDid,
                'amount'    => $amount,
                'reason'    => $reason,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            $result->success = false;
            $result->errorMessage = $e->getMessage();

            logger()->error('Escrow release failed', [
                'escrow_id' => $escrowId,
                'recipient' => $recipientDid,
                'amount'    => $amount,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }

        return $result;
    }
}
