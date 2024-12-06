<?php

namespace App\Domain\Account\Actions;

use App\Domain\Account\Events\MoneySubtracted;
use App\Models\Account;

class DebitAccount extends AccountAction
{
    /**
     * @param \App\Domain\Account\Events\MoneySubtracted $event
     *
     * @return \App\Models\Account
     */
    public function __invoke(MoneySubtracted $event): Account
    {
        return $this->accountRepository->findByUuid(
            $event->aggregateRootUuid()
        )->subtractMoney(
            $event->money->getAmount()
        );
    }
}
