<?php

declare(strict_types=1);

namespace App\Domain\Asset\Projectors;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\Transaction;
use App\Domain\Asset\Events\AssetTransactionCreated;
use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class AssetTransactionProjector extends Projector
{
    /**
     * Handle asset transaction created event.
     */
    public function onAssetTransactionCreated(AssetTransactionCreated $event): void
    {
        /** @var Account|null $account */
        $account = null;
        try {
            // Find the account
            /** @var \Illuminate\Database\Eloquent\Model|null $$account */
            $$account = Account::where('uuid', (string) $event->accountUuid)->first();

            if (! $account) {
                Log::error(
                    'Account not found for asset transaction',
                    [
                        'account_uuid' => (string) $event->accountUuid,
                        'asset_code'   => $event->assetCode,
                    ]
                );

                return;
            }

            // Update account balance for the specific asset
            $accountBalance = AccountBalance::firstOrCreate(
                [
                    'account_uuid' => (string) $event->accountUuid,
                    'asset_code'   => $event->assetCode,
                ],
                [
                    'balance' => 0,
                ]
            );

            // Apply the transaction
            if ($event->isCredit()) {
                $accountBalance->credit($event->getAmount());
            } else {
                if (! $accountBalance->hasSufficientBalance($event->getAmount())) {
                    Log::error(
                        'Insufficient balance for asset transaction',
                        [
                            'account_uuid'     => (string) $event->accountUuid,
                            'asset_code'       => $event->assetCode,
                            'requested_amount' => $event->getAmount(),
                            'current_balance'  => $accountBalance->balance,
                        ]
                    );

                    return;
                }
                $accountBalance->debit($event->getAmount());
            }

            // Asset transactions are stored as events, balance projections are sufficient
            // No need to create separate transaction records

            Log::info(
                'Asset transaction processed successfully',
                [
                    'account_uuid' => (string) $event->accountUuid,
                    'asset_code'   => $event->assetCode,
                    'type'         => $event->type,
                    'amount'       => $event->getAmount(),
                    'new_balance'  => $accountBalance->balance,
                ]
            );
        } catch (Exception $e) {
            Log::error(
                'Error processing asset transaction',
                [
                    'account_uuid' => (string) $event->accountUuid,
                    'asset_code'   => $event->assetCode,
                    'type'         => $event->type,
                    'amount'       => $event->getAmount(),
                    'error'        => $e->getMessage(),
                    'trace'        => $e->getTraceAsString(),
                ]
            );

            throw $e;
        }
    }
}
