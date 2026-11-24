<?php

namespace App\Domain\Account\Actions;

use App\Domain\Account\Events\AccountCreated;
use App\Domain\Account\Models\Account;

class CreateAccount extends AccountAction
{
    /**
     * Project AccountCreated. A row that is already there stays unchanged, so
     * the queued projector can run after the endpoint wrote the row, or twice.
     */
    public function __invoke(AccountCreated $event): Account
    {
        return $this->accountRepository->createOrFirst(
            $event->account->withUuid(
                $event->aggregateRootUuid()
            )
        );
    }
}
