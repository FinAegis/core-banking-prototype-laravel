<?php

namespace App\Domain\Account\Actions;

use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Models\Account;

class DebitAccount extends AccountAction
{
    /**
     * @param \App\Domain\Account\Events\AssetBalanceSubtracted $event
     *
     * @return \App\Models\Account
     */
    public function __invoke(AssetBalanceSubtracted $event): Account
    {
        $account = $this->accountRepository->findByUuid(
            $event->aggregateRootUuid()
        );
        
        // Find existing asset balance
        $balance = \App\Models\AccountBalance::where([
            'account_uuid' => $account->uuid,
            'asset_code' => $event->assetCode,
        ])->first();
        
        if (!$balance) {
            throw new \Exception("Asset balance not found for {$event->assetCode}");
        }
        
        // Subtract from balance amount (in smallest unit)
        $balance->balance -= $event->amount;
        
        // Ensure balance doesn't go negative (should be validated in aggregate)
        if ($balance->balance < 0) {
            throw new \Exception("Insufficient balance for {$event->assetCode}");
        }
        
        $balance->save();
        
        return $account->fresh();
    }
}
