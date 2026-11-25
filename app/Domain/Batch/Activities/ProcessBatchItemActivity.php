<?php

namespace App\Domain\Batch\Activities;

use App\Domain\Account\Aggregates\TransferAggregate;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Support\DecimalString;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Workflow\Activity;

class ProcessBatchItemActivity extends Activity
{
    public function execute(string $batchJobUuid, int $itemIndex, array $item): array
    {
        try {
            $result = match ($item['type']) {
                'transfer'   => $this->processTransfer($item),
                'payment'    => $this->processPayment($item),
                'conversion' => $this->processConversion($item),
                default      => throw new InvalidArgumentException("Unknown item type: {$item['type']}")
            };

            // Record success
            BatchAggregate::retrieve($batchJobUuid)
                ->processBatchItem($itemIndex, 'completed', $result)
                ->persist();

            return $result;
        } catch (Throwable $e) {
            // Record failure
            BatchAggregate::retrieve($batchJobUuid)
                ->processBatchItem($itemIndex, 'failed', [], $e->getMessage())
                ->persist();

            throw $e;
        }
    }

    private function processTransfer(array $item): array
    {
        /** @var Account|null $toAccount */
        $toAccount = null;
        /** @var Account|null $fromAccount */
        $fromAccount = null;
        // Validate accounts
        $fromAccount = Account::where('uuid', $item['from_account'])->first();
        $toAccount = Account::where('uuid', $item['to_account'])->first();

        if (! $fromAccount || ! $toAccount) {
            throw new InvalidArgumentException('Invalid account specified');
        }

        // Check balance
        $balance = $fromAccount->getBalance($item['currency']);
        $amount = $this->toMinorUnits($item['amount'], $item['currency']);

        if ($balance < $amount) {
            throw new InvalidArgumentException('Insufficient balance');
        }

        // Execute transfer using the TransferAggregate
        $transferUuid = (string) Str::uuid();

        TransferAggregate::retrieve($transferUuid)
            ->transfer(
                from: AccountUuid::fromString($fromAccount->uuid),
                to: AccountUuid::fromString($toAccount->uuid),
                money: new Money(
                    amount: $amount,
                    currency: $item['currency']
                )
            )
            ->persist();

        return [
            'transfer_id' => $transferUuid,
            'status'      => 'completed',
        ];
    }

    private function processPayment(array $item): array
    {
        // For now, payments are handled similarly to transfers
        return $this->processTransfer($item);
    }

    private function processConversion(array $item): array
    {
        /** @var Account|null $account */
        $account = null;
        // Get account
        $account = Account::where('uuid', $item['from_account'])->first();

        if (! $account) {
            throw new InvalidArgumentException('Invalid account specified');
        }

        // Check balance for from currency
        $balance = $account->getBalance($item['from_currency']);
        $amount = $this->toMinorUnits($item['amount'], $item['from_currency']);

        if ($balance < $amount) {
            throw new InvalidArgumentException('Insufficient balance');
        }

        // Calculate conversion (simplified)
        $rates = [
            'USD' => ['EUR' => '0.92', 'GBP' => '0.79', 'PHP' => '56.25'],
            'EUR' => ['USD' => '1.09', 'GBP' => '0.86', 'PHP' => '61.20'],
            'GBP' => ['USD' => '1.27', 'EUR' => '1.16', 'PHP' => '71.15'],
            'PHP' => ['USD' => '0.018', 'EUR' => '0.016', 'GBP' => '0.014'],
        ];

        $rate = $rates[$item['from_currency']][$item['to_currency']] ?? '1';

        // bcmath in each currency's own minor units, rounded down.
        $fromPrecision = $this->precisionOf($item['from_currency']);
        $toPrecision = $this->precisionOf($item['to_currency']);
        $major = bcdiv((string) $amount, bcpow('10', (string) $fromPrecision), $fromPrecision);
        $convertedAmount = (int) bcmul(bcmul($major, $rate, 18), bcpow('10', (string) $toPrecision), 0);

        // Execute conversion using events
        // In production, this would use a proper ExchangeAggregate
        $conversionUuid = (string) Str::uuid();

        return [
            'conversion_id'    => $conversionUuid,
            'converted_amount' => $convertedAmount,
            'rate'             => (float) $rate,
        ];
    }

    /**
     * Exact minor units of the currency. An amount with more decimals than the
     * currency has is refused, not rounded.
     */
    private function toMinorUnits(mixed $amount, string $currency): int
    {
        $precision = $this->precisionOf($currency);
        $minor = DecimalString::toMinorUnitsInt($amount, $precision);

        if ($minor === null) {
            throw new InvalidArgumentException("The amount can have at most {$precision} decimal places for {$currency}");
        }

        return $minor;
    }

    private function precisionOf(string $currency): int
    {
        return (int) (Asset::query()->where('code', $currency)->value('precision') ?? 2);
    }
}
