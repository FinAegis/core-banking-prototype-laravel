<?php

declare(strict_types=1);

namespace App\Domain\Asset\Projectors;

use App\Domain\Asset\Events\AssetTransactionCreated;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Transaction;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;
use Illuminate\Support\Facades\Log;

class AssetTransactionProjector extends Projector
{
    /**
     * Handle asset transaction created event
     */
    public function onAssetTransactionCreated(AssetTransactionCreated $event): void
    {
        try {
            // Find the account
            $account = Account::where('uuid', (string) $event->accountUuid)->first();
            
            if (!$account) {
                Log::error('Account not found for asset transaction', [
                    'account_uuid' => (string) $event->accountUuid,
                    'asset_code' => $event->assetCode,
                ]);
                return;
            }
            
            // Update account balance for the specific asset
            $accountBalance = AccountBalance::firstOrCreate(
                [
                    'account_uuid' => (string) $event->accountUuid,
                    'asset_code' => $event->assetCode,
                ],
                [
                    'balance' => 0,
                ]
            );
            
            // Apply the transaction
            if ($event->isCredit()) {
                $accountBalance->credit($event->getAmount());
            } else {
                if (!$accountBalance->hasSufficientBalance($event->getAmount())) {
                    Log::error('Insufficient balance for asset transaction', [
                        'account_uuid' => (string) $event->accountUuid,
                        'asset_code' => $event->assetCode,
                        'requested_amount' => $event->getAmount(),
                        'current_balance' => $accountBalance->balance,
                    ]);
                    return;
                }
                $accountBalance->debit($event->getAmount());
            }
            
            // Asset transactions are stored as events, balance projections are sufficient
            // No need to create separate transaction records
            
            Log::info('Asset transaction processed successfully', [
                'account_uuid' => (string) $event->accountUuid,
                'asset_code' => $event->assetCode,
                'type' => $event->type,
                'amount' => $event->getAmount(),
                'new_balance' => $accountBalance->balance,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error processing asset transaction', [
                'account_uuid' => (string) $event->accountUuid,
                'asset_code' => $event->assetCode,
                'type' => $event->type,
                'amount' => $event->getAmount(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
}