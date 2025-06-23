<?php

namespace App\Domain\Wallet\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Aggregates\AssetTransactionAggregate;
use Workflow\Activity;

class WithdrawAssetActivity extends Activity
{
    /**
     * @param AccountUuid $accountUuid
     * @param string $assetCode
     * @param int $amount
     * @param AssetTransactionAggregate $assetTransaction
     *
     * @return bool
     */
    public function execute(
        AccountUuid $accountUuid,
        string $assetCode,
        int $amount,
        AssetTransactionAggregate $assetTransaction
    ): bool {
        $money = new Money($amount);
        
        $transactionId = uniqid('withdraw_', true);
        $assetTransaction->retrieve($transactionId)
            ->debit($accountUuid, $assetCode, $money, 'Wallet withdrawal')
            ->persist();

        return true;
    }
}