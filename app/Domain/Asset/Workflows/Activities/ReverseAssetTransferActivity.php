<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use Workflow\Activity;

class ReverseAssetTransferActivity extends Activity
{
    /**
     * Reverse an initiated asset transfer as part of compensation logic
     */
    public function execute(
        string $transferId,
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        Money $fromAmount
    ): void {
        // Retrieve the transfer aggregate
        $aggregate = AssetTransferAggregate::retrieve($transferId);
        
        // Mark the transfer as reversed/cancelled
        $aggregate->reverse(
            reason: 'Workflow compensation: Transfer reversed due to subsequent step failure'
        );
        
        // Persist the aggregate state
        $aggregate->persist();
        
        // Log the reversal for audit purposes
        logger()->info('Asset transfer reversed', [
            'transfer_id' => $transferId,
            'from_account' => $fromAccountUuid->toString(),
            'to_account' => $toAccountUuid->toString(),
            'from_asset' => $fromAssetCode,
            'to_asset' => $toAssetCode,
            'amount' => $fromAmount->getAmount(),
            'reason' => 'Workflow compensation',
        ]);
    }
}