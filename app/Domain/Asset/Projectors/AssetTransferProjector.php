<?php

declare(strict_types=1);

namespace App\Domain\Asset\Projectors;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Asset\Events\AssetTransferCompleted;
use App\Domain\Asset\Events\AssetTransferFailed;
use App\Domain\Asset\Events\AssetTransferInitiated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

/**
 * Moves balances for asset transfers and conversions and writes their history.
 *
 * An initiated transfer changes nothing; the stored event is its record. On
 * completion the debit, the credit and both history rows are written in one
 * transaction on the balances' connection, so a failure leaves no half-booked
 * transfer. A completion that finds too little money throws instead of being
 * skipped, because a skipped completion would report a transfer that never
 * moved any money.
 */
class AssetTransferProjector extends Projector
{
    public function onAssetTransferInitiated(AssetTransferInitiated $event): void
    {
        Log::info('Asset transfer initiated', [
            'from_account'  => (string) $event->fromAccountUuid,
            'to_account'    => (string) $event->toAccountUuid,
            'from_asset'    => $event->fromAssetCode,
            'to_asset'      => $event->toAssetCode,
            'from_amount'   => $event->getFromAmount(),
            'to_amount'     => $event->getToAmount(),
            'exchange_rate' => $event->exchangeRate,
        ]);
    }

    public function onAssetTransferCompleted(AssetTransferCompleted $event): void
    {
        $fromUuid = (string) $event->fromAccountUuid;
        $toUuid = (string) $event->toAccountUuid;
        $fromAmount = $event->fromAmount->getAmount();
        $toAmount = $event->toAmount->getAmount();

        if (! Account::query()->where('uuid', $fromUuid)->exists() || ! Account::query()->where('uuid', $toUuid)->exists()) {
            throw new RuntimeException("Account not found for asset transfer from {$fromUuid} to {$toUuid}");
        }

        AccountBalance::query()->getConnection()->transaction(function () use ($event, $fromUuid, $toUuid, $fromAmount, $toAmount): void {
            $fromBalance = $this->lockedBalance($fromUuid, $event->fromAssetCode);

            if (! $fromBalance->hasSufficientBalance($fromAmount)) {
                throw new RuntimeException(
                    "Insufficient {$event->fromAssetCode} balance to complete the asset transfer. Required: {$fromAmount}, Available: {$fromBalance->balance}"
                );
            }

            $fromBalance->debit($fromAmount);
            $this->lockedBalance($toUuid, $event->toAssetCode)->credit($toAmount);

            $this->writeHistory($event, $fromUuid, $toUuid, $fromAmount, $toAmount);
        });

        Log::info('Asset transfer completed', [
            'from_account' => $fromUuid,
            'to_account'   => $toUuid,
            'from_asset'   => $event->fromAssetCode,
            'to_asset'     => $event->toAssetCode,
            'from_amount'  => $fromAmount,
            'to_amount'    => $toAmount,
        ]);
    }

    public function onAssetTransferFailed(AssetTransferFailed $event): void
    {
        Log::warning('Asset transfer failed', [
            'from_account' => (string) $event->fromAccountUuid,
            'to_account'   => (string) $event->toAccountUuid,
            'from_asset'   => $event->fromAssetCode,
            'to_asset'     => $event->toAssetCode,
            'reason'       => $event->reason,
        ]);
    }

    private function lockedBalance(string $accountUuid, string $assetCode): AccountBalance
    {
        $balance = AccountBalance::query()
            ->where('account_uuid', $accountUuid)
            ->where('asset_code', $assetCode)
            ->lockForUpdate()
            ->first();

        return $balance ?? AccountBalance::query()->create([
            'account_uuid' => $accountUuid,
            'asset_code'   => $assetCode,
            'balance'      => 0,
        ]);
    }

    private function writeHistory(AssetTransferCompleted $event, string $fromUuid, string $toUuid, int $fromAmount, int $toAmount): void
    {
        $sameAccount = $fromUuid === $toUuid;
        $subtype = $event->isCrossAssetTransfer() ? 'conversion' : null;
        $shared = [
            'reference'              => $event->transferId ?? $event->aggregateRootUuid(),
            'hash'                   => $event->hash->getHash(),
            'status'                 => 'completed',
            'subtype'                => $subtype,
            'transaction_group_uuid' => $event->aggregateRootUuid(),
        ];

        TransactionProjection::query()->create($shared + [
            'uuid'         => (string) Str::uuid(),
            'account_uuid' => $fromUuid,
            'type'         => 'transfer_out',
            'asset_code'   => $event->fromAssetCode,
            'amount'       => $fromAmount,
            'description'  => $sameAccount
                ? "Converted {$event->fromAssetCode} to {$event->toAssetCode}"
                : 'Transfer to ' . substr($toUuid, 0, 8),
            'related_account_uuid' => $sameAccount ? null : $toUuid,
            'metadata'             => ['event_type' => 'AssetTransferCompleted', 'to_account' => $toUuid, 'to_asset' => $event->toAssetCode],
        ]);

        TransactionProjection::query()->create($shared + [
            'uuid'         => (string) Str::uuid(),
            'account_uuid' => $toUuid,
            'type'         => 'transfer_in',
            'asset_code'   => $event->toAssetCode,
            'amount'       => $toAmount,
            'description'  => $sameAccount
                ? "Converted from {$event->fromAssetCode}"
                : 'Transfer from ' . substr($fromUuid, 0, 8),
            'related_account_uuid' => $sameAccount ? null : $fromUuid,
            'metadata'             => ['event_type' => 'AssetTransferCompleted', 'from_account' => $fromUuid, 'from_asset' => $event->fromAssetCode],
        ]);
    }
}
