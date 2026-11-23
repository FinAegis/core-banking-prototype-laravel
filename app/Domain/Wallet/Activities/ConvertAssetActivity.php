<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use Exception;
use InvalidArgumentException;
use Workflow\Activity;

class ConvertAssetActivity extends Activity
{
    /**
     * Convert part of an account's balance from one asset to another.
     *
     * Amounts are in each asset's minor units. A caller that quoted a price
     * (GCU buy and sell use the basket value less the fee) passes it as $rate,
     * so the booking matches the quote; otherwise the rate table decides. The
     * transfer id is derived from
     * the workflow and this activity's position in it, so a retry after the
     * conversion was booked returns the booked result instead of converting
     * again.
     *
     * @return array{success: true, transfer_id: string, from_asset: string, to_asset: string, original_amount: int, converted_amount: int, exchange_rate: float}
     */
    public function execute(
        AccountUuid $accountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        int $amount,
        ?string $rate = null
    ): array {
        $transferId = $this->transferId();
        $booked = AssetTransferAggregate::retrieve($transferId);

        if ($booked->getStatus() === 'completed') {
            return $this->result($transferId, $fromAssetCode, $toAssetCode, $amount, (int) $booked->getToAmount()?->getAmount(), (float) $booked->getExchangeRate());
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('The amount to convert must be positive.');
        }

        $rateUsed = $rate === null ? $this->tableRate($fromAssetCode, $toAssetCode) : $this->givenRate($rate);

        $convertedAmount = $this->convertMinorUnits($amount, $rateUsed, $fromAssetCode, $toAssetCode);
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

        $booked
            ->initiate(
                $accountUuid,
                $accountUuid, // Same account conversion
                $fromAssetCode,
                $toAssetCode,
                new Money($amount),
                new Money($convertedAmount),
                (float) $rateUsed,
                'Wallet currency conversion'
            )
            ->complete($transferId)
            ->persist();

        return $this->result($transferId, $fromAssetCode, $toAssetCode, $amount, $convertedAmount, (float) $rateUsed);
    }

    /**
     * @return numeric-string
     */
    private function tableRate(string $fromAssetCode, string $toAssetCode): string
    {
        $rate = ExchangeRate::getRate($fromAssetCode, $toAssetCode);
        if (! $rate) {
            throw new InvalidArgumentException("Exchange rate not available for {$fromAssetCode} to {$toAssetCode}");
        }

        return sprintf('%.10F', $rate);
    }

    /**
     * @return numeric-string
     */
    private function givenRate(string $rate): string
    {
        if (! is_numeric($rate) || preg_match('/^\d+(\.\d+)?$/', $rate) !== 1 || bccomp($rate, '0', 10) !== 1) {
            throw new InvalidArgumentException('The conversion rate must be a positive decimal number.');
        }

        return $rate;
    }

    /**
     * amount x rate, rescaled from the source asset's decimal places to the
     * target's, rounded down to a whole minor unit with bcmath (10 000 x 0.29
     * must give 2 900, not the 2 899 that floating point gives).
     */
    /**
     * @param  numeric-string  $rate
     */
    private function convertMinorUnits(int $amount, string $rate, string $fromAssetCode, string $toAssetCode): int
    {
        $major = bcdiv((string) $amount, bcpow('10', (string) $this->precisionOf($fromAssetCode)), 18);
        $converted = bcmul($major, $rate, 18);

        return (int) bcmul($converted, bcpow('10', (string) $this->precisionOf($toAssetCode)), 0);
    }

    private function precisionOf(string $assetCode): int
    {
        return (int) (Asset::query()->where('code', $assetCode)->value('precision') ?? 2);
    }

    /**
     * A version 4 formatted UUID from the workflow id and activity index.
     */
    private function transferId(): string
    {
        $hash = substr(hash('sha256', "wallet_convert:{$this->storedWorkflow->id}:{$this->index}"), 0, 32);
        $hash[12] = '4';
        $hash[16] = dechex(0x8 | (hexdec($hash[16]) & 0x3));

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }

    /**
     * @return array{success: true, transfer_id: string, from_asset: string, to_asset: string, original_amount: int, converted_amount: int, exchange_rate: float}
     */
    private function result(string $transferId, string $fromAssetCode, string $toAssetCode, int $amount, int $convertedAmount, float $exchangeRate): array
    {
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
