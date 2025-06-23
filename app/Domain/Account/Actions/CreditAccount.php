<?php

namespace App\Domain\Account\Actions;

use App\Domain\Account\Events\AssetBalanceAdded;
use App\Models\Account;

class CreditAccount extends AccountAction
{
    /**
     * @param \App\Domain\Account\Events\AssetBalanceAdded $event
     *
     * @return \App\Models\Account
     */
    public function __invoke(AssetBalanceAdded $event): Account
    {
        $account = $this->accountRepository->findByUuid(
            $event->aggregateRootUuid()
        );
        
        // Update or create asset balance using event data
        $balance = \App\Models\AccountBalance::firstOrCreate(
            [
                'account_uuid' => $account->uuid,
                'asset_code' => $event->assetCode,
            ],
            [
                'balance' => 0,
            ]
        );
        
        // Add to balance amount (in smallest unit)
        $balance->balance += $event->amount;
        $balance->save();
        
        return $account->fresh();
    }
}
