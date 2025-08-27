<?php

namespace App\Domain\Account\Workflows;

use App\Domain\Account\Aggregates\TransactionAggregate;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use InvalidArgumentException;
use Workflow\Activity;

class TransactionReversalActivity extends Activity
{
    public function execute(
        AccountUuid $accountUuid,
        Money $originalAmount,
        string $transactionType,
        string $reversalReason,
        ?string $authorizedBy,
        TransactionAggregate $transaction
    ): array {
        $aggregate = $transaction->retrieve($accountUuid->getUuid());

        // Reverse the transaction by doing the opposite operation
        if ($transactionType === 'debit') {
            // Original was debit, so we credit to reverse
            $aggregate->credit($originalAmount);
        } elseif ($transactionType === 'credit') {
            // Original was credit, so we debit to reverse
            $aggregate->debit($originalAmount);
        } else {
            throw new InvalidArgumentException("Invalid transaction type: {$transactionType}");
        }

        $aggregate->persist();

        // Log the reversal for audit purposes
        $this->logReversal($accountUuid, $originalAmount, $transactionType, $reversalReason, $authorizedBy);

        return [
            'account_uuid'    => $accountUuid->getUuid(),
            'reversed_amount' => $originalAmount->getAmount(),
            'original_type'   => $transactionType,
            'reversal_reason' => $reversalReason,
            'authorized_by'   => $authorizedBy,
            'reversed_at'     => now()->toISOString(),
        ];
    }

    private function logReversal(
        AccountUuid $accountUuid,
        Money $amount,
        string $transactionType,
        string $reason,
        ?string $authorizedBy
    ): void {
        logger()->info(
            'Transaction reversed',
            [
                'account_uuid'  => $accountUuid->getUuid(),
                'amount'        => $amount->getAmount(),
                'original_type' => $transactionType,
                'reason'        => $reason,
                'authorized_by' => $authorizedBy,
                'timestamp'     => now()->toISOString(),
            ]
        );
    }
}
