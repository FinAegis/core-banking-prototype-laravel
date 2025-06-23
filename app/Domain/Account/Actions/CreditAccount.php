<?php

namespace App\Domain\Account\Actions;

use App\Domain\Account\Events\MoneyAdded;
use App\Models\Account;

class CreditAccount extends AccountAction
{
    /**
     * @param \App\Domain\Account\Events\MoneyAdded $event
     *
     * @return \App\Models\Account
     */
    public function __invoke(MoneyAdded $event): Account
    {
        $account = $this->accountRepository->findByUuid(
            $event->aggregateRootUuid()
        );
        
        // Update or create asset balance using event data
        $balance = \App\Models\AccountBalance::firstOrCreate(
            [
                'account_uuid' => $account->uuid,
                'asset_code' => $event->money->assetCode,
            ],
            [
                'balance' => 0,
            ]
        );
        
        // Add to balance amount (in smallest unit)
        $balance->balance += $event->money->amount;
        $balance->save();
        
        return $account->fresh();
    }
}
