<?php

declare(strict_types=1);

namespace App\Domain\Batch\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Asset\Models\Asset;
use App\Domain\Batch\Aggregates\BatchAggregate;
use App\Domain\Batch\Exceptions\BatchItemRefused;
use App\Domain\Batch\Models\BatchJob;
use App\Support\DecimalString;
use Workflow\Activity;

/**
 * Processes one item of a batch.
 *
 * A transfer or payment item moves money with an asset transfer (the same
 * aggregate and projector as asset transfers and wallet conversions): the
 * debit, the credit and both history rows are written in one locked
 * transaction. Before it moves anything the activity checks the item again,
 * whatever the API checked: the source account must belong to the batch
 * owner, both accounts must exist and differ and not be frozen, the amount
 * must fit the asset's decimals, and the balance must cover it.
 *
 * The transfer id is derived from the batch and the item index, so a retried
 * activity or a re-run workflow finds the transfer already booked and does
 * not move the money a second time.
 */
class ProcessBatchItemActivity extends Activity
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function execute(string $batchJobUuid, int $itemIndex, array $item): array
    {
        /** @var BatchJob|null $batch */
        $batch = BatchJob::query()->where('uuid', $batchJobUuid)->first();
        if ($batch === null) {
            throw new BatchItemRefused("Batch job not found: {$batchJobUuid}");
        }

        try {
            $type = (string) ($item['type'] ?? $batch->type);

            $result = match ($type) {
                'transfer', 'payment' => $this->processTransfer($batch, $itemIndex, $item),
                'conversion'          => throw new BatchItemRefused('Batch conversions are not available.'),
                default               => throw new BatchItemRefused("Unknown item type: {$type}"),
            };
        } catch (BatchItemRefused $refused) {
            $this->record($batchJobUuid, $itemIndex, 'failed', [], $refused->getMessage());

            throw $refused;
        }

        $this->record($batchJobUuid, $itemIndex, 'completed', $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function processTransfer(BatchJob $batch, int $itemIndex, array $item): array
    {
        $transferId = self::transferId((string) $batch->uuid, $itemIndex);
        $booked = AssetTransferAggregate::retrieve($transferId);

        if ($booked->getStatus() === 'completed') {
            return $this->result($transferId, (int) $booked->getFromAmount()?->getAmount(), (string) $booked->getFromAssetCode());
        }

        // Checked when the item runs, not only when the batch was created: a
        // batch queued before the switch went off must not pay.
        if (! config('ledger.batch_processing_enabled')) {
            throw new BatchItemRefused('Batch processing is not available (BATCH_PROCESSING_ENABLED is off); no money was moved.');
        }

        $status = BatchAggregate::retrieve((string) $batch->uuid)->getStatus();
        if ($status !== 'processing') {
            throw new BatchItemRefused("The batch is {$status}; no more items are processed.");
        }

        $from = $this->account($item['from_account'] ?? null);
        $to = $this->account($item['to_account'] ?? null);

        if ($from === null || $to === null) {
            throw new BatchItemRefused('Invalid account specified');
        }

        $ownerUuid = (string) $batch->user_uuid;
        if ($ownerUuid === '' || ! hash_equals((string) $from->user_uuid, $ownerUuid)) {
            throw new BatchItemRefused('The source account does not belong to the owner of the batch');
        }

        if ($from->uuid === $to->uuid) {
            throw new BatchItemRefused('The source and destination accounts must be different');
        }

        if ($from->frozen) {
            throw new BatchItemRefused('Cannot transfer from frozen account');
        }

        if ($to->frozen) {
            throw new BatchItemRefused('Cannot transfer to frozen account');
        }

        $currency = (string) ($item['currency'] ?? '');
        /** @var Asset|null $asset */
        $asset = Asset::query()->where('code', $currency)->first();
        if ($asset === null) {
            throw new BatchItemRefused("Unknown asset: {$currency}");
        }

        $precision = (int) ($asset->precision ?? 2);
        $amount = DecimalString::toMinorUnitsInt($item['amount'] ?? null, $precision);
        if ($amount === null || $amount <= 0) {
            throw new BatchItemRefused("The amount must be positive and can have at most {$precision} decimal places for {$currency}");
        }

        $balance = (int) AccountBalance::query()
            ->where('account_uuid', (string) $from->uuid)
            ->where('asset_code', $currency)
            ->value('balance');

        if ($balance < $amount) {
            throw new BatchItemRefused('Insufficient balance');
        }

        $description = isset($item['description']) && $item['description'] !== ''
            ? (string) $item['description']
            : "Batch {$batch->name}";

        $booked
            ->initiate(
                fromAccountUuid: new AccountUuid((string) $from->uuid),
                toAccountUuid: new AccountUuid((string) $to->uuid),
                fromAssetCode: $currency,
                toAssetCode: $currency,
                fromAmount: new Money($amount),
                toAmount: new Money($amount),
                exchangeRate: 1.0,
                description: $description,
                metadata: [
                    'batch_job_uuid' => (string) $batch->uuid,
                    'item_index'     => $itemIndex,
                ]
            )
            ->complete($transferId)
            ->persist();

        return $this->result($transferId, $amount, $currency);
    }

    /**
     * Record the item's outcome once. A retried activity whose outcome is
     * already recorded, or a batch that is no longer processing, records
     * nothing more.
     *
     * @param  array<string, mixed>  $result
     */
    private function record(string $batchJobUuid, int $itemIndex, string $status, array $result, ?string $error = null): void
    {
        $aggregate = BatchAggregate::retrieve($batchJobUuid);

        if ($aggregate->getStatus() !== 'processing' || $aggregate->hasProcessedItem($itemIndex)) {
            return;
        }

        $aggregate->processBatchItem($itemIndex, $status, $result, $error)->persist();
    }

    private function account(mixed $uuid): ?Account
    {
        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        /** @var Account|null $account */
        $account = Account::query()->withoutGlobalScope('team')->where('uuid', $uuid)->first();

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(string $transferId, int $amount, string $currency): array
    {
        return [
            'transfer_id' => $transferId,
            'status'      => 'completed',
            'amount'      => $amount,
            'currency'    => $currency,
        ];
    }

    /**
     * A version 4 formatted UUID from the batch and the item index, the same
     * for every attempt at the same item.
     */
    public static function transferId(string $batchJobUuid, int $itemIndex): string
    {
        $hash = substr(hash('sha256', "batch_transfer:{$batchJobUuid}:{$itemIndex}"), 0, 32);
        $hash[12] = '4';
        $hash[16] = dechex(0x8 | (hexdec($hash[16]) & 0x3));

        return sprintf('%s-%s-%s-%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4), substr($hash, 16, 4), substr($hash, 20, 12));
    }
}
