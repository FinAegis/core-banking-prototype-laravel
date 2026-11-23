<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Asset\Models\ExchangeRate;
use Exception;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Workflow\Activity;

class ConvertAssetActivity extends Activity
{
    /**
     * Convert part of an account's balance from one asset to another.
     *
     * @return array{success: true, transfer_id: string, from_asset: string, to_asset: string, original_amount: int, converted_amount: int, exchange_rate: float}
     */
    public function execute(
        AccountUuid $accountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        int $amount
    ): array {
        if ($amount <= 0) {
            throw new InvalidArgumentException('The amount to convert must be positive.');
        }

        $exchangeRate = ExchangeRate::getRate($fromAssetCode, $toAssetCode);
        if (! $exchangeRate) {
            throw new InvalidArgumentException("Exchange rate not available for {$fromAssetCode} to {$toAssetCode}");
        }

        // Rates are stored with up to 10 decimals. bcmath truncates like the
        // old intval() did, without the floating-point error (10 000 x 0.29
        // must give 2 900, not 2 899).
        $convertedAmount = (int) bcmul((string) $amount, sprintf('%.10F', $exchangeRate), 0);
        if ($convertedAmount <= 0) {
            throw new InvalidArgumentException("The amount is too small to convert {$fromAssetCode} to {$toAssetCode}.");
        }

        $available = (int) AccountBalance::query()
            ->where('account_uuid', (string) $accountUuid)
            ->where('asset_code', $fromAssetCode)
            ->value('balance');

        if ($available < $amount) {
            throw new Exception("Insufficient {$fromAssetCode} balance. Required: {$amount}, Available: {$available}");
        }

        $transferId = (string) Str::uuid();

        AssetTransferAggregate::retrieve($transferId)
            ->initiate(
                $accountUuid,
                $accountUuid, // Same account conversion
                $fromAssetCode,
                $toAssetCode,
                new Money($amount),
                new Money($convertedAmount),
                $exchangeRate,
                'Wallet currency conversion'
            )
            ->complete($transferId)
            ->persist();

        return [
            'success'          => true,
            'transfer_id'      => $transferId,
            'from_asset'       => $fromAssetCode,
            'to_asset'         => $toAssetCode,
            'original_amount'  => $amount,
            'converted_amount' => $convertedAmount,
            'exchange_rate'    => $exchangeRate,
        ];
    }
}
