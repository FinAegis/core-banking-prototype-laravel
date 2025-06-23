<?php

namespace App\Domain\Wallet\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Asset\Models\ExchangeRate;
use Workflow\Activity;

class ConvertAssetActivity extends Activity
{
    /**
     * @param AccountUuid $accountUuid
     * @param string $fromAssetCode
     * @param string $toAssetCode
     * @param int $amount
     * @param AssetTransferAggregate $assetTransfer
     *
     * @return bool
     */
    public function execute(
        AccountUuid $accountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        int $amount,
        AssetTransferAggregate $assetTransfer
    ): bool {
        $fromMoney = new Money($amount);
        
        // Get exchange rate
        $exchangeRate = ExchangeRate::getRate($fromAssetCode, $toAssetCode);
        if (!$exchangeRate) {
            throw new \InvalidArgumentException("Exchange rate not available for {$fromAssetCode} to {$toAssetCode}");
        }
        
        // Calculate converted amount
        $convertedAmount = intval($amount * $exchangeRate);
        $toMoney = new Money($convertedAmount);
        
        $transferId = uniqid('convert_', true);
        $assetTransfer->retrieve($transferId)
            ->initiate(
                $accountUuid,
                $accountUuid, // Same account conversion
                $fromAssetCode,
                $toAssetCode,
                $fromMoney,
                $toMoney,
                $exchangeRate,
                'Wallet currency conversion'
            )
            ->complete()
            ->persist();

        return true;
    }
}